<?php

namespace App\Models;

use App\Traits\HasTranslations;
use App\Traits\HasUuid;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class DiningVenue extends Model
{
    use HasFactory, HasUuid, HasTranslations, LogsActivity;

    protected $translatable = ['name', 'description', 'cuisine_type', 'location', 'hours'];

    protected $fillable = [
        'name',
        'description',
        'cuisine_type',
        'location',
        'hours',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function images(): MorphMany
    {
        return $this->morphMany(Media::class, 'mediable')->orderBy('sort_order');
    }
}
