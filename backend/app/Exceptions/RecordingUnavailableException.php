<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

final class RecordingUnavailableException extends RuntimeException
{
    public function __construct(string $message = 'Rekaman kamera belum tersedia atau kamera sedang tidak dapat dihubungi.')
    {
        parent::__construct($message);
    }
}
