<?php

namespace App;

enum UserRole: string
{
    case User = 'user';
    case Operator = 'operator';
    case Admin = 'admin';

    public function label(): string
    {
        return match ($this) {
            self::User => 'User',
            self::Operator => 'Operator',
            self::Admin => 'Admin',
        };
    }

    /**
     * Class badge Bootstrap untuk menampilkan role.
     */
    public function badgeClass(): string
    {
        return match ($this) {
            self::User => 'text-bg-secondary',
            self::Operator => 'text-bg-info',
            self::Admin => 'text-bg-primary',
        };
    }
}
