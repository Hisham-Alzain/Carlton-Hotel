<?php

namespace Database\Factories;

use App\Models\Guest;
use App\Models\GuestDocument;
use App\Models\Reservation;
use Illuminate\Database\Eloquent\Factories\Factory;

class GuestDocumentFactory extends Factory
{
    protected $model = GuestDocument::class;

    public function definition(): array
    {
        return [
            'guest_id'       => Guest::factory(),
            'reservation_id' => Reservation::factory(),
            'type'           => 'passport',
            'file_path'      => 'guest-documents/test/passport.jpg',
        ];
    }
}
