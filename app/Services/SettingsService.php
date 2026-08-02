<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Crypt;

class SettingsService
{
    public function get(string $group, string $key, mixed $default = null): mixed
    {
        $setting = $this->all()->first(
            fn (Setting $s) => $s->group === $group && $s->key === $key
        );

        if (! $setting) {
            return $default;
        }

        return $this->castValue($setting);
    }

    public function set(
        string $group,
        string $key,
        mixed $value,
        string $type = 'string',
        bool $encrypted = false,
        bool $public = false,
        ?int $userId = null,
    ): Setting {
        $stored = $encrypted
            ? Crypt::encryptString(is_string($value) ? $value : json_encode($value))
            : (is_array($value) || is_object($value) ? json_encode($value) : (string) $value);

        $setting = Setting::query()->updateOrCreate(
            ['group' => $group, 'key' => $key],
            [
                'value' => $stored,
                'value_type' => $type,
                'is_encrypted' => $encrypted,
                'is_public' => $public,
                'updated_by' => $userId,
            ]
        );

        return $setting;
    }

    public function publicMap(): array
    {
        return $this->all()
            ->where('is_public', true)
            ->mapWithKeys(fn (Setting $s) => ["{$s->group}.{$s->key}" => $this->castValue($s)])
            ->all();
    }

    public function group(string $group, bool $includeSecrets = false): array
    {
        return $this->all()
            ->where('group', $group)
            ->when(! $includeSecrets, fn ($c) => $c->where('is_encrypted', false))
            ->mapWithKeys(fn (Setting $s) => [$s->key => $this->castValue($s)])
            ->all();
    }

    /** @return \Illuminate\Support\Collection<int, Setting> */
    public function all()
    {
        return Setting::query()->get();
    }

    protected function castValue(Setting $setting): mixed
    {
        $raw = $setting->value;

        if ($setting->is_encrypted && $raw) {
            try {
                $raw = Crypt::decryptString($raw);
            } catch (\Throwable) {
                return null;
            }
        }

        return match ($setting->value_type) {
            'bool', 'boolean' => filter_var($raw, FILTER_VALIDATE_BOOLEAN),
            'int', 'integer' => (int) $raw,
            'decimal', 'float' => (float) $raw,
            'json' => json_decode((string) $raw, true),
            default => $raw,
        };
    }
}
