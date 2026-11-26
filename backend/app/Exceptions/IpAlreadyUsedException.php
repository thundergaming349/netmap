<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;

final class IpAlreadyUsedException extends NetmapException
{
    public function __construct(
        string $message,
        public readonly ?string $suggestion = null,
    ) {
        parent::__construct($message);
    }

    public function errorCode(): string
    {
        return 'ip_already_used';
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'code' => $this->errorCode(),
            'suggestion' => $this->suggestion,
            'errors' => [
                'ip' => [$this->getMessage()],
            ],
        ], $this->status());
    }
}
