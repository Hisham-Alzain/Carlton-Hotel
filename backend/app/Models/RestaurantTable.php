<?php

namespace App\Models;

use App\Traits\HasUuid;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class RestaurantTable extends Model
{
    use HasFactory, HasUuid, LogsActivity;

    protected $fillable = ['dining_venue_id', 'table_number', 'capacity', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function diningVenue(): BelongsTo { return $this->belongsTo(DiningVenue::class); }
    public function bookings(): MorphMany    { return $this->morphMany(ServiceBooking::class, 'bookable'); }
}
