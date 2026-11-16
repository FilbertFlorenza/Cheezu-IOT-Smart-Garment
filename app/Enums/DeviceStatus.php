<?php

namespace App\Enums;

enum DeviceStatus: string
{
    case Active = 'ACTIVE';
    case Inactive = 'INACTIVE';
    case Maintenance = 'MAINTENANCE';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Inactive => 'Inactive',
            self::Maintenance => 'Maintenance',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Active => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
            self::Inactive => 'bg-slate-100 text-slate-600 ring-slate-500/20',
            self::Maintenance => 'bg-amber-50 text-amber-700 ring-amber-600/20',
        };
    }

    public function dotColor(): string
    {
        return match ($this) {
            self::Active => 'bg-emerald-500',
            self::Inactive => 'bg-slate-400',
            self::Maintenance => 'bg-amber-500',
        };
    }
}
