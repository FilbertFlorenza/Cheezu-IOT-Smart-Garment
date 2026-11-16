<?php

namespace App\Enums;

enum Role: string
{
    case Administrator = 'ADMINISTRATOR';
    case Operator = 'OPERATOR';
    case Service = 'SERVICE';

    public function label(): string
    {
        return match ($this) {
            self::Administrator => 'Administrator',
            self::Operator => 'Operator',
            self::Service => 'Service Account',
        };
    }

    /**
     * Roles that can be assigned through the user management screens.
     *
     * @return array<int, self>
     */
    public static function assignable(): array
    {
        return [self::Administrator, self::Operator];
    }
}
