<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['key', 'value'];

    public static function get(string $key, $default = null)
    {
        $row = static::query()->find($key);

        return $row && $row->value !== null && $row->value !== '' ? $row->value : $default;
    }

    public static function put(string $key, $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
    }
}
