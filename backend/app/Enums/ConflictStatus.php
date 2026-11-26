<?php

declare(strict_types=1);

namespace App\Enums;

enum ConflictStatus: string
{
    case Active = 'active';
    case Resolved = 'resolved';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Aktif',
            self::Resolved => 'Selesai',
        };
    }
}
