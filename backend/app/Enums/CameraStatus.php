<?php

declare(strict_types=1);

namespace App\Enums;

enum CameraStatus: string
{
    case Online = 'online';
    case Unstable = 'unstable';
    case Offline = 'offline';

    public function label(): string
    {
        return match ($this) {
            self::Online => 'LIVE',
            self::Unstable => 'TAK STABIL',
            self::Offline => 'OFFLINE',
        };
    }
}
