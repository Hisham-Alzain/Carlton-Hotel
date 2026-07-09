<?php

namespace App\Models;

use App\Traits\HasTranslations;
use App\Traits\HasUuid;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Promotion extends Model
{
    use HasFactory, HasUuid, HasTranslations, LogsActivity;

    protected $translatable = ['title', 'description', 'terms'];

    protected $fillable = [
        'title',
        'description',
        'terms',
        'valid_from',
        'valid_until',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active'   => 'boolean',
        'valid_from'  => 'date',
        'valid_until' => 'date',
    ];

    public function images(): MorphMany
    {
        return $this->morphMany(Media::class, 'mediable')->orderBy('sort_order');
    }
}
