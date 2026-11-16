<?php

namespace Database\Seeders;

use App\Enums\DeviceStatus;
use App\Enums\ForecastSource;
use App\Enums\ForecastStatus;
use App\Enums\InspectionStatus;
use App\Enums\RiskLevel;
use App\Models\Device;
use App\Models\Forecast;
use App\Models\Inspection;
use App\Models\InspectionDetection;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DemoDataSeeder extends Seeder
{
    private const DEFECT_RATE = 0.27;

    public function run(): void
    {
        if (Inspection::query()->exists()) {
            $this->command?->warn('Demo data already present; skipping inspection seeding.');

            return;
        }

        $devices = $this->seedDevices();

        $this->seedInspections($devices);
        $this->seedForecasts();
    }

    /**
     * @return array<int, Device>
     */
    private function seedDevices(): array
    {
        $definitions = [
            ['DEVICE-001', 'Line A — Inspection Camera 01', 'Line A', DeviceStatus::Active],
            ['DEVICE-002', 'Line A — Inspection Camera 02', 'Line A', DeviceStatus::Active],
            ['DEVICE-003', 'Line B — Inspection Camera 01', 'Line B', DeviceStatus::Active],
            ['DEVICE-004', 'Line B — Inspection Camera 02', 'Line B', DeviceStatus::Active],
            ['DEVICE-005', 'Line C — Inspection Camera 01', 'Line C', DeviceStatus::Maintenance],
            ['DEVICE-006', 'Cutting Room — Camera 01', 'Cutting Room', DeviceStatus::Active],
            ['DEVICE-007', 'Finishing — Camera 01', 'Finishing', DeviceStatus::Inactive],
            ['DEVICE-008', 'Fabric Store — Camera 01', 'Fabric Store', DeviceStatus::Active],
        ];

        return collect($definitions)->map(function (array $definition) {
            [$code, $name, $location, $status] = $definition;

            return Device::query()->firstOrCreate(
                ['device_code' => $code],
                [
                    'device_name' => $name,
                    'description' => 'ESP32-CAM fabric inspection unit at '.$location.'.',
                    'location' => $location,
                    'status' => $status,
                    'last_seen_at' => now()->subMinutes(random_int(1, 240)),
                ],
            );
        })->all();
    }

    /**
     * @param  array<int, Device>  $devices
     */
    private function seedInspections(array $devices): void
    {
        $modelVersions = ['v1.0', 'v1.1'];
        $defectClasses = config('inspection.defect_classes');
        $lastSeen = [];

        for ($day = 29; $day >= 0; $day--) {
            $date = CarbonImmutable::now()->subDays($day);
            $perDay = $day === 0 ? random_int(6, 14) : random_int(10, 20);

            for ($i = 0; $i < $perDay; $i++) {
                $device = $devices[array_rand($devices)];
                $isDefect = random_int(1, 100) <= (int) (self::DEFECT_RATE * 100);
                $defectCount = $isDefect ? random_int(1, 3) : 0;
                $inspectedAt = $date->setTime(random_int(6, 20), random_int(0, 59), random_int(0, 59));

                if ($inspectedAt->isFuture()) {
                    $inspectedAt = CarbonImmutable::now()->subMinutes(random_int(1, 30));
                }

                $inspection = Inspection::query()->create([
                    'inspection_id' => (string) Str::uuid(),
                    'device_id' => $device->id,
                    'inspected_at' => $inspectedAt,
                    'received_at' => $inspectedAt->addSeconds(random_int(1, 4)),
                    'overall_status' => $isDefect ? InspectionStatus::Defect : InspectionStatus::Pass,
                    'defect_count' => $defectCount,
                    'processing_time_ms' => random_int(380, 1650),
                    'model_version' => $modelVersions[array_rand($modelVersions)],
                    'metadata' => ['seed' => true],
                ]);

                if ($defectCount > 0) {
                    $rows = [];
                    $selectedClasses = (array) array_rand(array_flip($defectClasses), min($defectCount, count($defectClasses)));

                    for ($d = 0; $d < $defectCount; $d++) {
                        $rows[] = [
                            'inspection_id' => $inspection->id,
                            'detection_index' => $d,
                            'defect_class' => $selectedClasses[$d] ?? $defectClasses[array_rand($defectClasses)],
                            'confidence' => round(random_int(6000, 9900) / 10000, 4),
                            'bbox_x' => random_int(20, 1000),
                            'bbox_y' => random_int(20, 700),
                            'bbox_width' => random_int(25, 160),
                            'bbox_height' => random_int(25, 160),
                            'metadata' => null,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }

                    InspectionDetection::query()->insert($rows);
                }

                $lastSeen[$device->id] = max($lastSeen[$device->id] ?? null, $inspectedAt->getTimestamp());
            }
        }

        foreach ($devices as $device) {
            if ($device->status === DeviceStatus::Active) {
                $device->forceFill([
                    'last_seen_at' => isset($lastSeen[$device->id])
                        ? CarbonImmutable::createFromTimestamp($lastSeen[$device->id])
                        : now()->subMinutes(random_int(1, 20)),
                ])->save();
            }
        }
    }

    private function seedForecasts(): void
    {
        $this->createCloudForecast(now()->subHours(3), ForecastStatus::Superseded);
        $this->createCloudForecast(now()->subMinutes(40), ForecastStatus::Active);

        $this->createNetworkForecast(now()->subHours(3), ForecastStatus::Superseded);
        $this->createNetworkForecast(now()->subMinutes(25), ForecastStatus::Active);
    }

    private function createCloudForecast(\DateTimeInterface $generatedAt, ForecastStatus $status): void
    {
        $forecast = Forecast::query()->create([
            'external_id' => (string) Str::uuid(),
            'source_type' => ForecastSource::Cloud,
            'model_name' => 'cloud-lstm',
            'model_version' => 'v1.2',
            'forecast_horizon_minutes' => 240,
            'generated_at' => $generatedAt,
            'received_at' => $generatedAt,
            'status' => $status,
            'metadata' => ['environment' => 'production', 'region' => 'ap-southeast-1'],
        ]);

        $metrics = [
            'cpu_utilization' => ['unit' => '%', 'base' => 46, 'amplitude' => 22, 'risk' => RiskLevel::High],
            'memory_utilization' => ['unit' => '%', 'base' => 63, 'amplitude' => 12, 'risk' => RiskLevel::Critical],
            'disk_io_read' => ['unit' => 'MB/s', 'base' => 28, 'amplitude' => 10, 'risk' => RiskLevel::Low],
            'disk_io_write' => ['unit' => 'MB/s', 'base' => 18, 'amplitude' => 7, 'risk' => RiskLevel::Medium],
            'network_in' => ['unit' => 'Mbps', 'base' => 120, 'amplitude' => 35, 'risk' => RiskLevel::Low],
            'network_out' => ['unit' => 'Mbps', 'base' => 80, 'amplitude' => 25, 'risk' => RiskLevel::Low],
        ];

        $this->insertPredictions($forecast, $metrics, $generatedAt, isCloud: true);
    }

    private function createNetworkForecast(\DateTimeInterface $generatedAt, ForecastStatus $status): void
    {
        $forecast = Forecast::query()->create([
            'external_id' => (string) Str::uuid(),
            'source_type' => ForecastSource::Network,
            'model_name' => 'network-prophet',
            'model_version' => 'v1.1',
            'forecast_horizon_minutes' => 180,
            'generated_at' => $generatedAt,
            'received_at' => $generatedAt,
            'status' => $status,
            'metadata' => ['link' => 'garment-plant-wan', 'capture_point' => 'core-switch'],
        ]);

        $metrics = [
            'network_throughput' => ['unit' => 'Mbps', 'base' => 165, 'amplitude' => 55, 'risk' => RiskLevel::High],
            'packet_count' => ['unit' => 'pps', 'base' => 4200, 'amplitude' => 900, 'risk' => RiskLevel::Medium],
            'traffic_volume' => ['unit' => 'MB', 'base' => 540, 'amplitude' => 120, 'risk' => RiskLevel::Low],
            'latency' => ['unit' => 'ms', 'base' => 24, 'amplitude' => 8, 'risk' => RiskLevel::Low],
        ];

        $this->insertPredictions($forecast, $metrics, $generatedAt, isCloud: false);
    }

    /**
     * @param  array<string, array{unit:string,base:int,amplitude:int,risk:RiskLevel}>  $metrics
     */
    private function insertPredictions(Forecast $forecast, array $metrics, \DateTimeInterface $generatedAt, bool $isCloud): void
    {
        $start = CarbonImmutable::parse($generatedAt)->subHours(2);
        $rows = [];
        $now = now();

        foreach ($metrics as $metric => $definition) {
            for ($step = 0; $step < 8; $step++) {
                $predictedAt = $start->addMinutes($step * 60);
                $wave = sin(($step + ($isCloud ? 1 : 2)) / 1.8) * $definition['amplitude'];
                $predicted = max(1, round($definition['base'] + $wave + random_int(-4, 4), 2));

                $actual = $predictedAt->lessThanOrEqualTo($now)
                    ? max(1, round($predicted + random_int(-6, 6), 2))
                    : null;

                $risk = null;
                $anomalyRisk = null;

                if ($predictedAt->greaterThan($now) && $step >= 5) {
                    $risk = $definition['risk'];
                    $anomalyRisk = $this->riskScore($risk);
                } elseif ($predictedAt->lessThanOrEqualTo($now) && random_int(1, 100) <= 20) {
                    $risk = RiskLevel::Low;
                    $anomalyRisk = 0.15;
                }

                $rows[] = [
                    'forecast_id' => $forecast->id,
                    'metric_name' => $metric,
                    'metric_unit' => $definition['unit'],
                    'predicted_value' => $predicted,
                    'actual_value' => $actual,
                    'predicted_at' => $predictedAt,
                    'anomaly_risk' => $anomalyRisk,
                    'risk_level' => $risk?->value,
                    'metadata' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        $forecast->predictions()->insert($rows);
    }

    private function riskScore(RiskLevel $level): float
    {
        return match ($level) {
            RiskLevel::Low => 0.2,
            RiskLevel::Medium => 0.5,
            RiskLevel::High => 0.78,
            RiskLevel::Critical => 0.93,
        };
    }
}
