<?php

namespace App\Enums;

enum InspectionStatus: string
{
    case Pass = 'PASS';
    case Defect = 'DEFECT';

    public function label(): string
    {
        return match ($this) {
            self::Pass => 'Pass',
            self::Defect => 'Defect',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pass => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
            self::Defect => 'bg-rose-50 text-rose-700 ring-rose-600/20',
        };
    }

    public function dotColor(): string
    {
        return match ($this) {
            self::Pass => 'bg-emerald-500',
            self::Defect => 'bg-rose-500',
        };
    }
}
