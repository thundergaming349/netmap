<?php

declare(strict_types=1);

namespace App\Exceptions;

final class WriteActionsDisabledException extends NetmapException
{
    public function __construct(
        string $message = 'Fitur tulis ke MikroTik sedang dimatikan (MIKROTIK_WRITE_ENABLED=false).',
    ) {
        parent::__construct($message);
    }

    public function errorCode(): string
    {
        return 'write_disabled';
    }

    public function status(): int
    {
        return 403;
    }
}
