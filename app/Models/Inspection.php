<?php

namespace App\Models;

use App\Enums\InspectionStatus;
use Database\Factories\InspectionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Inspection extends Model
{
    /** @use HasFactory<InspectionFactory> */
    use HasFactory;

    protected $fillable = [
        'inspection_id',
        'device_id',
        'inspected_at',
        'received_at',
        'overall_status',
        'defect_count',
        'processing_time_ms',
        'model_version',
        'image_path',
        'image_mime',
        'image_size_bytes',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'inspected_at' => 'datetime',
            'received_at' => 'datetime',
            'overall_status' => InspectionStatus::class,
            'defect_count' => 'integer',
            'processing_time_ms' => 'integer',
            'image_size_bytes' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'inspection_id';
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function detections(): HasMany
    {
        return $this->hasMany(InspectionDetection::class)->orderBy('detection_index');
    }

    public function scopeFilter(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['search'] ?? null, function (Builder $query, string $search) {
                $query->where(function (Builder $query) use ($search) {
                    $query->where('inspection_id', 'like', "%{$search}%")
                        ->orWhere('model_version', 'like', "%{$search}%")
                        ->orWhereHas('device', function (Builder $query) use ($search) {
                            $query->where('device_code', 'like', "%{$search}%")
                                ->orWhere('device_name', 'like', "%{$search}%");
                        });
                });
            })
            ->when($filters['device'] ?? null, function (Builder $query, string $deviceCode) {
                $query->whereHas('device', fn (Builder $query) => $query->where('device_code', $deviceCode));
            })
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('overall_status', $status))
            ->when($filters['defect_class'] ?? null, function (Builder $query, string $class) {
                $query->whereHas('detections', fn (Builder $query) => $query->where('defect_class', $class));
            })
            ->when($filters['from'] ?? null, fn (Builder $query, string $from) => $query->where('inspected_at', '>=', $from.' 00:00:00'))
            ->when($filters['to'] ?? null, fn (Builder $query, string $to) => $query->where('inspected_at', '<=', $to.' 23:59:59'));
    }
}
