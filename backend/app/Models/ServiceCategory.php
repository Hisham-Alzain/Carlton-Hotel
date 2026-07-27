<?php

namespace App\Models;

use App\Enums\Department;
use App\Enums\ServiceCategoryKind;
use App\Traits\HasTranslations;
use App\Traits\HasUuid;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ServiceCategory extends Model
{
    use HasFactory, HasUuid, HasTranslations, LogsActivity;

    protected $translatable = ['name', 'description'];

    protected $fillable = [
        'code', 'name', 'description', 'kind', 'department',
        'link_target', 'icon', 'is_active', 'sort_order',
    ];

    protected $casts = [
        'kind'       => ServiceCategoryKind::class,
        'department' => Department::class,
        'is_active'  => 'boolean',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(ServiceItem::class)->orderBy('sort_order');
    }

    /** Items the app shows in a picker — the hidden default is excluded. */
    public function visibleItems(): HasMany
    {
        return $this->items()->where('is_default', false)->where('is_active', true);
    }

    /** The single item a `direct` category requests without a picker. */
    public function defaultItem(): HasOne
    {
        return $this->hasOne(ServiceItem::class)->where('is_default', true);
    }
}
