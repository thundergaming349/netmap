<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Str;

final class NetworkFormat
{
    public static function normalizeMac(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        $trimmed = trim($raw);
        if ($trimmed === '') {
            return null;
        }

        $hex = (string) preg_replace('/[^0-9a-fA-F]/', '', $trimmed);
        if (strlen($hex) !== 12) {
            return Str::lower($trimmed);
        }

        return implode(':', str_split(Str::lower($hex), 2));
    }

    public static function isValidMac(?string $mac): bool
    {
        if ($mac === null || $mac === '') {
            return false;
        }

        return preg_match('/^([0-9a-f]{2}:){5}[0-9a-f]{2}$/', $mac) === 1;
    }

    public static function isValidIpv4NoLeadingZeros(string $ip): bool
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
            return false;
        }

        foreach (explode('.', $ip) as $part) {
            if (strlen($part) > 1 && str_starts_with($part, '0')) {
                return false;
            }
        }

        return true;
    }
}
