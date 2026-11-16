<?php

namespace App\Enums;

enum ForecastStatus: string
{
    case Active = 'ACTIVE';
    case Superseded = 'SUPERSEDED';
    case Expired = 'EXPIRED';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Superseded => 'Superseded',
            self::Expired => 'Expired',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Active => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
            self::Superseded => 'bg-slate-100 text-slate-600 ring-slate-500/20',
            self::Expired => 'bg-amber-50 text-amber-700 ring-amber-600/20',
        };
    }
}
