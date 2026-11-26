<?php

declare(strict_types=1);

namespace App\Enums;

enum DeviceType: string
{
    case Camera = 'camera';
    case Nvr = 'nvr';
    case Switch = 'switch';
    case AccessPoint = 'access_point';
    case Server = 'server';
    case Pc = 'pc';
    case Printer = 'printer';
    case Router = 'router';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Camera => 'Kamera CCTV',
            self::Nvr => 'NVR',
            self::Switch => 'Switch',
            self::AccessPoint => 'Access Point',
            self::Server => 'Server',
            self::Pc => 'PC / Laptop',
            self::Printer => 'Printer',
            self::Router => 'Router',
            self::Other => 'Lainnya',
        };
    }
}
