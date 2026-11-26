<?php

declare(strict_types=1);

namespace App\Services\Mikrotik;

use RuntimeException;

/**
 * Native client for the MikroTik RouterOS Binary API (port 8728),
 * supporting both RouterOS v6.43+ and v7.x.
 */
final class RouterOsApiClient
{
    /**
     * Execute a RouterOS API command and return parsed associative rows.
     *
     * @param  list<string>  $words
     * @return list<array<string, string>>
     */
    public function command(
        string $host,
        string $username,
        string $password,
        array $words,
        int $port = 8728,
        float $timeout = 3.0,
    ): array {
        $errno = 0;
        $errstr = '';
        $socket = @fsockopen($host, $port, $errno, $errstr, $timeout);

        if ($socket === false) {
            throw new RuntimeException("Tidak dapat terhubung ke API RouterOS {$host}:{$port} ({$errstr})");
        }

        stream_set_timeout($socket, (int) ceil($timeout));

        try {
            $loginReply = $this->sendSentence($socket, [
                '/login',
                '=name='.$username,
                '=password='.$password,
            ]);

            if (! $this->isDone($loginReply)) {
                throw new RuntimeException('Autentikasi API MikroTik gagal. Periksa username/password.');
            }

            $sentences = $this->sendSentence($socket, $words);
            $rows = [];

            foreach ($sentences as $sentence) {
                $type = $sentence[0] ?? '';
                if ($type !== '!re') {
                    continue;
                }

                $row = [];
                $count = count($sentence);
                for ($i = 1; $i < $count; $i++) {
                    $word = $sentence[$i];
                    if (! str_starts_with($word, '=')) {
                        continue;
                    }
                    $eqPos = strpos($word, '=', 1);
                    if ($eqPos === false) {
                        continue;
                    }
                    $key = substr($word, 1, $eqPos - 1);
                    $val = substr($word, $eqPos + 1);
                    $row[$key] = $val;
                }
                $rows[] = $row;
            }

            return $rows;
        } finally {
            @fclose($socket);
        }
    }

    /**
     * @param  resource  $socket
     * @param  list<string>  $words
     * @return list<list<string>>
     */
    private function sendSentence($socket, array $words): array
    {
        foreach ($words as $word) {
            $this->writeWord($socket, $word);
        }
        $this->writeWord($socket, '');

        $sentences = [];
        while (true) {
            $sentence = [];
            while (true) {
                $word = $this->readWord($socket);
                if ($word === '') {
                    break;
                }
                $sentence[] = $word;
            }

            if (empty($sentence)) {
                break;
            }

            $sentences[] = $sentence;
            $first = $sentence[0];
            if ($first === '!done' || $first === '!fatal') {
                break;
            }
        }

        return $sentences;
    }

    /**
     * @param  list<list<string>>  $sentences
     */
    private function isDone(array $sentences): bool
    {
        foreach ($sentences as $sentence) {
            if (($sentence[0] ?? '') === '!trap' || ($sentence[0] ?? '') === '!fatal') {
                return false;
            }
            if (($sentence[0] ?? '') === '!done') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  resource  $socket
     */
    private function writeWord($socket, string $word): void
    {
        $len = strlen($word);
        if ($len < 0x80) {
            fwrite($socket, chr($len));
        } elseif ($len < 0x4000) {
            fwrite($socket, chr(($len >> 8) | 0x80).chr($len & 0xFF));
        } elseif ($len < 0x200000) {
            fwrite($socket, chr(($len >> 16) | 0xC0).chr(($len >> 8) & 0xFF).chr($len & 0xFF));
        } else {
            fwrite($socket, chr(($len >> 24) | 0xE0).chr(($len >> 16) & 0xFF).chr(($len >> 8) & 0xFF).chr($len & 0xFF));
        }

        if ($len > 0) {
            fwrite($socket, $word);
        }
    }

    /**
     * @param  resource  $socket
     */
    private function readWord($socket): string
    {
        $first = fread($socket, 1);
        if ($first === false || $first === '') {
            return '';
        }

        $b = ord($first);
        if (($b & 0x80) === 0) {
            $len = $b;
        } elseif (($b & 0xC0) === 0x80) {
            $second = fread($socket, 1);
            $len = (($b & ~0xC0) << 8) + ($second !== false && $second !== '' ? ord($second) : 0);
        } elseif (($b & 0xE0) === 0xC0) {
            $next = fread($socket, 2);
            $len = (($b & ~0xE0) << 16)
                + (isset($next[0]) ? (ord($next[0]) << 8) : 0)
                + (isset($next[1]) ? ord($next[1]) : 0);
        } else {
            $len = 0;
        }

        if ($len <= 0) {
            return '';
        }

        $result = '';
        while (strlen($result) < $len) {
            $chunk = fread($socket, $len - strlen($result));
            if ($chunk === false || $chunk === '') {
                break;
            }
            $result .= $chunk;
        }

        return $result;
    }
}
