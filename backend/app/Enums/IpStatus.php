<?php

declare(strict_types=1);

namespace App\Enums;

enum IpStatus: string
{
    case Gateway = 'gateway';
    case Static = 'static';
    case Dhcp = 'dhcp';
    case Reserved = 'reserved';
    case Conflict = 'conflict';
    case Risk = 'risk';
    case Offline = 'offline';
    case Free = 'free';
    case Network = 'network';

    public function label(): string
    {
        return match ($this) {
            self::Gateway => 'Gateway',
            self::Static => 'Static',
            self::Dhcp => 'DHCP lease',
            self::Reserved => 'Reservasi',
            self::Conflict => 'Konflik',
            self::Risk => 'Static di pool DHCP',
            self::Offline => 'Offline',
            self::Free => 'Kosong',
            self::Network => 'Network / Broadcast',
        };
    }
}
