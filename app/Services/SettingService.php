<?php

namespace App\Services;

use App\Models\Setting;

/** Small key/value store so admins can change business parameters without redeploying. */
class SettingService
{
    public function get(string $key, mixed $default = null): mixed
    {
        $setting = Setting::query()->find($key);

        return $setting?->value ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        Setting::query()->updateOrCreate(['key' => $key], ['value' => (string) $value]);
    }
}
