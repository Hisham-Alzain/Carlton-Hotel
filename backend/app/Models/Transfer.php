<?php

namespace App\Models;

use App\Traits\HasTranslations;
use App\Traits\HasUuid;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Transfer extends Model
{
    use HasFactory, HasUuid, HasTranslations, LogsActivity;

    protected $translatable = ['name'];

    protected $fillable = ['name', 'price_usd', 'is_active'];

    protected $casts = ['is_active' => 'boolean', 'price_usd' => 'decimal:2'];

    public function bookings(): MorphMany { return $this->morphMany(ServiceBooking::class, 'bookable'); }
}
