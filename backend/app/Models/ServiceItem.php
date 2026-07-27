<?php

namespace App\Models;

use App\Traits\HasTranslations;
use App\Traits\HasUuid;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceItem extends Model
{
    use HasFactory, HasUuid, HasTranslations, LogsActivity;

    protected $translatable = ['name', 'description'];

    protected $fillable = [
        'service_category_id', 'name', 'description', 'expected_minutes',
        'price_usd', 'is_default', 'is_active', 'sort_order',
    ];

    protected $casts = [
        'expected_minutes' => 'integer',
        'price_usd'        => 'decimal:2',
        'is_default'       => 'boolean',
        'is_active'        => 'boolean',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(ServiceCategory::class, 'service_category_id');
    }
}
