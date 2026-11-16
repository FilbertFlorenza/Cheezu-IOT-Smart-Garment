<?php

namespace App\Services;

use App\Enums\DeviceStatus;
use App\Enums\ForecastSource;
use App\Enums\ForecastStatus;
use App\Enums\InspectionStatus;
use App\Enums\RiskLevel;
use App\Models\Device;
use App\Models\Forecast;
use App\Models\ForecastPrediction;
use App\Models\Inspection;
use App\Models\InspectionDetection;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DashboardMetricsService
{
    /**
     * Aggregate figures for the dashboard statistic cards.
     *
     * @return array{total:int,pass:int,defect:int,defect_rate:float,today:int,last_24h:int}
     */
    public function summary(): array
    {
        $total = Inspection::query()->count();
        $pass = Inspection::query()->where('overall_status', InspectionStatus::Pass)->count();
        $defect = $total - $pass;

        return [
            'total' => $total,
            'pass' => $pass,
            'defect' => $defect,
            'defect_rate' => $total > 0 ? round($defect / $total * 100, 1) : 0.0,
            'today' => Inspection::query()->where('inspected_at', '>=', now()->startOfDay())->count(),
            'last_24h' => Inspection::query()->where('inspected_at', '>=', now()->subDay())->count(),
        ];
    }

    /**
     * Daily PASS/DEFECT counts, including zero-value days.
     *
     * @return array{labels:array<int,string>,pass:array<int,int>,defect:array<int,int>,total:array<int,int>}
     */
    public function trend(int $days = 14): array
    {
        $start = now()->subDays($days - 1)->startOfDay();

        $rows = Inspection::query()
            ->where('inspected_at', '>=', $start)
            ->selectRaw('DATE(inspected_at) as day, overall_status, COUNT(*) as aggregate')
            ->groupBy('day', 'overall_status')
            ->get();

        $byDay = $rows->groupBy('day');

        $labels = [];
        $pass = [];
        $defect = [];
        $total = [];

        for ($i = 0; $i < $days; $i++) {
            $date = CarbonImmutable::now()->subDays($days - 1 - $i);
            $key = $date->toDateString();
            $dayRows = $byDay->get($key, collect());

            $passCount = (int) $dayRows->firstWhere('overall_status', InspectionStatus::Pass->value)?->aggregate;
            $defectCount = (int) $dayRows->firstWhere('overall_status', InspectionStatus::Defect->value)?->aggregate;

            $labels[] = $date->format('M j');
            $pass[] = $passCount;
            $defect[] = $defectCount;
            $total[] = $passCount + $defectCount;
        }

        return compact('labels', 'pass', 'defect', 'total');
    }

    /**
     * @return Collection<int, InspectionDetection>
     */
    public function defectDistribution(int $limit = 8): Collection
    {
        return InspectionDetection::query()
            ->select('defect_class', DB::raw('COUNT(*) as aggregate'))
            ->groupBy('defect_class')
            ->orderByDesc('aggregate')
            ->limit($limit)
            ->get();
    }

    /**
     * @return Collection<int, Inspection>
     */
    public function recentInspections(int $limit = 8): Collection
    {
        return Inspection::query()
            ->with(['device:id,device_code,device_name', 'detections'])
            ->latest('inspected_at')
            ->limit($limit)
            ->get();
    }

    /**
     * @return array{total:int,active:int,inactive:int,maintenance:int,online:int}
     */
    public function deviceStats(): array
    {
        $counts = Device::query()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $online = Device::query()
            ->where('status', DeviceStatus::Active)
            ->where('last_seen_at', '>=', now()->subMinutes(config('inspection.online_threshold_minutes')))
            ->count();

        return [
            'total' => (int) $counts->sum(),
            'active' => (int) $counts->get(DeviceStatus::Active->value, 0),
            'inactive' => (int) $counts->get(DeviceStatus::Inactive->value, 0),
            'maintenance' => (int) $counts->get(DeviceStatus::Maintenance->value, 0),
            'online' => $online,
        ];
    }

    /**
     * @return Collection<int, Device>
     */
    public function deviceOverview(int $limit = 6): Collection
    {
        return Device::query()
            ->with('latestInspection')
            ->withCount('inspections')
            ->orderByDesc('last_seen_at')
            ->limit($limit)
            ->get();
    }

    public function latestForecast(ForecastSource $source): ?Forecast
    {
        return Forecast::query()
            ->forSource($source)
            ->active()
            ->with(['predictions' => fn ($query) => $query->orderBy('predicted_at')])
            ->latest('generated_at')
            ->first();
    }

    /**
     * High/critical warnings belonging to active forecasts only.
     *
     * @return Collection<int, ForecastPrediction>
     */
    public function warnings(int $limit = 6): Collection
    {
        return ForecastPrediction::query()
            ->with('forecast')
            ->whereIn('risk_level', [RiskLevel::High, RiskLevel::Critical])
            ->whereHas('forecast', fn ($query) => $query->where('status', ForecastStatus::Active))
            ->orderByDesc('predicted_at')
            ->limit($limit)
            ->get();
    }

    public function totalWarnings(): int
    {
        return ForecastPrediction::query()
            ->whereIn('risk_level', [RiskLevel::High, RiskLevel::Critical])
            ->whereHas('forecast', fn ($query) => $query->where('status', ForecastStatus::Active))
            ->count();
    }
}
