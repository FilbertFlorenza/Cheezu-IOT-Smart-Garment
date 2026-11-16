<?php

namespace App\Models;

use App\Enums\ForecastSource;
use App\Enums\ForecastStatus;
use Database\Factories\ForecastFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Forecast extends Model
{
    /** @use HasFactory<ForecastFactory> */
    use HasFactory;

    protected $fillable = [
        'external_id',
        'source_type',
        'model_name',
        'model_version',
        'forecast_horizon_minutes',
        'generated_at',
        'received_at',
        'status',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'source_type' => ForecastSource::class,
            'status' => ForecastStatus::class,
            'generated_at' => 'datetime',
            'received_at' => 'datetime',
            'forecast_horizon_minutes' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function predictions(): HasMany
    {
        return $this->hasMany(ForecastPrediction::class)->orderBy('predicted_at');
    }

    public function scopeForSource(Builder $query, ForecastSource $source): Builder
    {
        return $query->where('source_type', $source);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', ForecastStatus::Active);
    }

    /**
     * The current forecast for a source: newest generated, active record.
     */
    public static function latestActiveFor(ForecastSource $source): ?self
    {
        return static::query()
            ->forSource($source)
            ->active()
            ->with(['predictions' => fn ($query) => $query->orderBy('predicted_at')])
            ->latest('generated_at')
            ->first();
    }

    public function warningCount(): int
    {
        return $this->predictions
            ->filter(fn (ForecastPrediction $prediction) => $prediction->risk_level?->isWarning())
            ->count();
    }
}
