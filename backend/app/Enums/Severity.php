<?php

declare(strict_types=1);

namespace App\Enums;

enum Severity: string
{
    case Conflict = 'conflict';
    case Risk = 'risk';
    case Info = 'info';

    public function label(): string
    {
        return match ($this) {
            self::Conflict => 'KONFLIK',
            self::Risk => 'RAWAN',
            self::Info => 'INFO',
        };
    }
}
