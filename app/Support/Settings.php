<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class Settings
{
    private const CACHE_KEY = 'app_settings';

    private static ?array $loaded = null;

    public static function all(): array
    {
        if (self::$loaded !== null) {
            return self::$loaded;
        }

        $defaults = array_map(fn ($d) => $d[3], SettingDefinitions::all());

        try {
            $stored = Cache::rememberForever(self::CACHE_KEY, fn () => Setting::pluck('value', 'key')->all());
        } catch (\Throwable $e) {
            $stored = []; // database not migrated yet
        }

        return self::$loaded = array_merge($defaults, $stored);
    }

    public static function get(string $key, $default = null)
    {
        $value = self::all()[$key] ?? null;

        return ($value === null || $value === '') ? $default : $value;
    }

    public static function bool(string $key): bool
    {
        return (bool) (int) self::get($key, 0);
    }

    public static function money(string $key): float
    {
        return round((float) self::get($key, 0), 2);
    }

    /** Public URL of an uploaded setting image, or null. */
    public static function image(string $key): ?string
    {
        $path = self::get($key);

        return $path ? Storage::disk('public')->url($path) : null;
    }

    public static function set(string $key, $value): void
    {
        $def = SettingDefinitions::all()[$key] ?? ['general', 'text'];
        Setting::updateOrCreate(['key' => $key], ['group' => $def[0], 'type' => $def[1], 'value' => $value]);
        self::flush();
    }

    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
        self::$loaded = null;
    }

    /** Contribution per member for a welfare case category, from settings. */
    public static function contributionFor(string $category): float
    {
        return self::money('contribution_'.$category);
    }
}
