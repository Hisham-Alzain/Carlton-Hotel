<?php

namespace App\Models;

use App\Traits\HasTranslations;
use App\Traits\HasUuid;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MenuItem extends Model
{
    use HasFactory, HasUuid, HasTranslations, LogsActivity;

    protected $translatable = ['name', 'description'];

    protected $fillable = ['menu_category_id', 'name', 'description', 'price_usd', 'is_active'];

    protected $casts = ['is_active' => 'boolean', 'price_usd' => 'decimal:2'];

    public function category(): BelongsTo { return $this->belongsTo(MenuCategory::class, 'menu_category_id'); }
}
