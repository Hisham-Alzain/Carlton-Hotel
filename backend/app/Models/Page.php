<?php

namespace App\Models;

use App\Traits\HasTranslations;
use App\Traits\HasUuid;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Page extends Model
{
    use HasFactory, HasUuid, HasTranslations, LogsActivity;

    protected $translatable = ['title', 'content'];

    protected $fillable = [
        'slug',
        'title',
        'content',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
