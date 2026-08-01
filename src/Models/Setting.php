<?php

namespace ConferenceTools\Branding\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Generic key/value settings store backing the branding management UI. It lives
 * in the branding package alongside the rest of branding; the host application
 * does not own it. Stored in the "branding_settings" table so it never collides
 * with any host settings table.
 */
class Setting extends Model
{
    protected $table = 'branding_settings';

    protected $fillable = ['key', 'value'];

    /** A branding setting's stored value, or the default when unset. */
    public static function get(string $key, mixed $default = null): mixed
    {
        $value = Cache::rememberForever('branding.setting.'.$key, fn () => static::query()->where('key', $key)->value('value'));

        return $value ?? $default;
    }

    /** Store a branding setting (null deletes) and refresh its cache. */
    public static function put(string $key, mixed $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget('branding.setting.'.$key);
    }
}
