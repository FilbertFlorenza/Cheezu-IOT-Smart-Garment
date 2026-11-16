<?php

namespace App\Enums;

enum ForecastSource: string
{
    case Cloud = 'CLOUD';
    case Network = 'NETWORK';

    public function label(): string
    {
        return match ($this) {
            self::Cloud => 'Cloud Resources',
            self::Network => 'Network Traffic',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::Cloud => 'Cloud',
            self::Network => 'Network',
        };
    }

    public function route(): string
    {
        return match ($this) {
            self::Cloud => 'forecasts.cloud',
            self::Network => 'forecasts.network',
        };
    }

    public function metricConfigKey(): string
    {
        return strtolower($this->value);
    }
}
