<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

/**
 * @property int $id
 * @property string $group
 * @property string $key
 * @property string|null $value
 * @property bool $is_encrypted
 */
#[Fillable([
    'group',
    'key',
    'value',
    'is_encrypted',
])]
final class Setting extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_encrypted' => 'boolean',
        ];
    }

    public function decodedValue(): mixed
    {
        if ($this->value === null || $this->value === '') {
            return null;
        }

        $raw = $this->is_encrypted ? Crypt::decryptString($this->value) : $this->value;
        $decoded = json_decode($raw, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $raw;
    }

    /**
     * @return array<string, mixed>
     */
    public static function groupValues(string $group, bool $includeSecrets = false): array
    {
        $rows = self::query()->where('group', $group)->get();
        $result = [];

        foreach ($rows as $row) {
            if ($row->is_encrypted && ! $includeSecrets) {
                $result['has_'.$row->key] = $row->value !== null && $row->value !== '';

                continue;
            }

            $result[$row->key] = $row->decodedValue();
        }

        return $result;
    }

    public static function putValue(string $group, string $key, mixed $value, bool $encrypt = false): self
    {
        if ($value === null || $value === '') {
            $encoded = null;
        } else {
            $serialized = is_string($value) ? $value : (string) json_encode($value);
            $encoded = $encrypt ? Crypt::encryptString($serialized) : $serialized;
        }

        return self::query()->updateOrCreate(
            ['group' => $group, 'key' => $key],
            ['value' => $encoded, 'is_encrypted' => $encrypt],
        );
    }
}
