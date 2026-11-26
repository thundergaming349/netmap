<?php

declare(strict_types=1);

namespace App\Exceptions;

final class ReservationOverlapException extends NetmapException
{
    public function errorCode(): string
    {
        return 'reservation_overlap';
    }
}
