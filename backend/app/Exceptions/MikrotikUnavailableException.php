<?php

declare(strict_types=1);

namespace App\Exceptions;

final class MikrotikUnavailableException extends NetmapException
{
    public function __construct(
        string $message = 'MikroTik tidak bisa dihubungi. Periksa koneksi jaringan atau pengaturan RouterOS.',
    ) {
        parent::__construct($message);
    }

    public function errorCode(): string
    {
        return 'mikrotik_unavailable';
    }

    public function status(): int
    {
        return 503;
    }
}
