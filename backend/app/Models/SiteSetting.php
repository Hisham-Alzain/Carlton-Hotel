<?php

namespace App\Models;

use App\Enums\SettingType;
use App\Traits\HasUuid;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One global website setting, addressed by (group, key).
 *
 * Deliberately NOT `HasTranslations`. Spatie's trait assumes every value in the
 * column is a locale map and would turn the scalar `"+963 (0)11 000 00 00"`
 * into garbage on read. Here `value` is the raw decoded JSON — a string, a
 * bool, a locale map, or a nested structure — and the caller knows which from
 * `type` and from the key it asked for. That is the price of letting
 * translatable and scalar settings share one table, and it is why this model
 * carries no `$translatable`.
 */
class SiteSetting extends Model
{
    use HasFactory, HasUuid, LogsActivity, SoftDeletes;

    protected $fillable = [
        'group',
        'key',
        'value',
        'type',
        'is_active',
    ];

    protected $casts = [
        // `json`, not `array`: the cast has to survive a scalar. `array` would
        // coerce the phone number string into `["+963 …"]` on the way out.
        'value'     => 'json',
        'type'      => SettingType::class,
        'is_active' => 'boolean',
    ];
}
