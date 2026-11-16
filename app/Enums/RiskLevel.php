<?php

namespace App\Enums;

enum RiskLevel: string
{
    case Low = 'LOW';
    case Medium = 'MEDIUM';
    case High = 'HIGH';
    case Critical = 'CRITICAL';

    public function label(): string
    {
        return match ($this) {
            self::Low => 'Low',
            self::Medium => 'Medium',
            self::High => 'High',
            self::Critical => 'Critical',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Low => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
            self::Medium => 'bg-amber-50 text-amber-700 ring-amber-600/20',
            self::High => 'bg-orange-50 text-orange-700 ring-orange-600/20',
            self::Critical => 'bg-rose-50 text-rose-700 ring-rose-600/20',
        };
    }

    public function dotColor(): string
    {
        return match ($this) {
            self::Low => 'bg-emerald-500',
            self::Medium => 'bg-amber-500',
            self::High => 'bg-orange-500',
            self::Critical => 'bg-rose-500',
        };
    }

    public function isWarning(): bool
    {
        return in_array($this, [self::High, self::Critical], true);
    }
}
