<?php

namespace Database\Seeders;

use App\Models\Guest;
use Illuminate\Database\Seeder;

// Named demo guests have fixed phone numbers so later seeders (and the
// Postman environment generator) can look them up deterministically.
class GuestSeeder extends Seeder
{
    const PHONE_CHECKED_IN        = '+963900000001'; // Ahmad — full in-stay activity
    const PHONE_CONFIRMED         = '+963900000002'; // Layla — booked, not checked in (pre-arrival tier)
    const PHONE_CHECKED_OUT       = '+963900000003'; // Sara — past stay, settled folio
    const PHONE_CANCELLED         = '+963900000004'; // Omar — cancelled booking
    const PHONE_UNVERIFIED        = '+963900000005'; // Rania — mid soft-hold, never completed OTP verify

    public function run(): void
    {
        Guest::factory()->phoneVerified()->emailVerified()->create([
            'first_name' => 'Ahmad', 'last_name' => 'Khalil', 'name' => 'Ahmad Khalil',
            'phone' => self::PHONE_CHECKED_IN, 'email' => 'ahmad.khalil@example.com',
        ]);

        Guest::factory()->phoneVerified()->emailVerified()->create([
            'first_name' => 'Layla', 'last_name' => 'Hassan', 'name' => 'Layla Hassan',
            'phone' => self::PHONE_CONFIRMED, 'email' => 'layla.hassan@example.com',
        ]);

        Guest::factory()->phoneVerified()->emailVerified()->create([
            'first_name' => 'Sara', 'last_name' => 'Ibrahim', 'name' => 'Sara Ibrahim',
            'phone' => self::PHONE_CHECKED_OUT, 'email' => 'sara.ibrahim@example.com',
        ]);

        Guest::factory()->phoneVerified()->create([
            'first_name' => 'Omar', 'last_name' => 'Youssef', 'name' => 'Omar Youssef',
            'phone' => self::PHONE_CANCELLED, 'email' => 'omar.youssef@example.com',
        ]);

        // Never OTP-verified — represents a guest mid soft-hold in the public
        // two-step booking flow (see BookingSeeder's pending_verification reservation).
        Guest::factory()->create([
            'first_name' => 'Rania', 'last_name' => 'Saad', 'name' => 'Rania Saad',
            'phone' => self::PHONE_UNVERIFIED, 'email' => 'rania.saad@example.com',
        ]);

        // Volume for pagination / admin list testing.
        Guest::factory()->count(10)->phoneVerified()->create();
    }
}
