<?php

namespace Database\Seeders;

use App\Models\Guest;
use App\Models\Payment;
use App\Models\PricingRule;
use App\Models\PromoCode;
use App\Models\RatePlan;
use App\Models\Reservation;
use App\Models\ReservationRoom;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Database\Seeder;

class BookingSeeder extends Seeder
{
    const PROMO_ACTIVE  = 'SUMMER10';
    const PROMO_WELCOME = 'WELCOME20';
    const PROMO_EXPIRED = 'EXPIRED05';

    public function run(): void
    {
        $this->ratePlansAndPricing();
        $this->promoCodes();
        $this->reservations();
    }

    private function ratePlansAndPricing(): void
    {
        foreach (RoomType::all() as $roomType) {
            RatePlan::create(['room_type_id' => $roomType->id, 'name' => 'Standard Rate', 'prepay_required' => false, 'is_active' => true]);
            RatePlan::create(['room_type_id' => $roomType->id, 'name' => 'Advance Purchase (non-refundable)', 'prepay_required' => true, 'is_active' => true]);
        }

        // Summer seasonal surcharge + weekend surcharge on the two flagship types.
        foreach (RoomType::where('name->en', 'Deluxe King')->orWhere('name->en', 'Executive Suite')->get() as $roomType) {
            PricingRule::create([
                'room_type_id' => $roomType->id, 'scope' => PricingRule::SCOPE_SEASONAL,
                'starts_on' => now()->startOfMonth()->toDateString(), 'ends_on' => now()->addMonths(2)->toDateString(),
                'modifier_type' => PricingRule::TYPE_PERCENTAGE, 'modifier_value' => 20, 'is_active' => true,
            ]);
            PricingRule::create([
                'room_type_id' => $roomType->id, 'scope' => PricingRule::SCOPE_WEEKEND,
                'starts_on' => now()->startOfMonth()->toDateString(), 'ends_on' => now()->addMonths(6)->toDateString(),
                'modifier_type' => PricingRule::TYPE_PERCENTAGE, 'modifier_value' => 15, 'is_active' => true,
            ]);
        }
    }

    private function promoCodes(): void
    {
        PromoCode::create(['code' => self::PROMO_ACTIVE, 'type' => PromoCode::TYPE_PERCENTAGE, 'value' => 10, 'expires_at' => now()->addMonths(2), 'max_uses' => null, 'used_count' => 0, 'is_active' => true]);
        PromoCode::create(['code' => self::PROMO_WELCOME, 'type' => PromoCode::TYPE_PERCENTAGE, 'value' => 20, 'expires_at' => now()->addMonths(6), 'max_uses' => 100, 'used_count' => 3, 'is_active' => true]);
        // Deliberately expired — for testing the invalid_promo error path.
        PromoCode::create(['code' => self::PROMO_EXPIRED, 'type' => PromoCode::TYPE_FLAT, 'value' => 5, 'expires_at' => now()->subMonth(), 'max_uses' => null, 'used_count' => 0, 'is_active' => true]);
    }

    private function reservations(): void
    {
        $standardQueen = RoomType::where('name->en', 'Standard Queen')->firstOrFail();
        $deluxeKing    = RoomType::where('name->en', 'Deluxe King')->firstOrFail();
        $executive     = RoomType::where('name->en', 'Executive Suite')->firstOrFail();
        $family        = RoomType::where('name->en', 'Family Room')->firstOrFail();
        $staff         = User::where('email', 'reception@carlton.demo')->firstOrFail();

        // --- Ahmad: checked in, room physically assigned ---
        $ahmad = Guest::where('phone', GuestSeeder::PHONE_CHECKED_IN)->firstOrFail();
        $ahmadReservation = Reservation::create([
            'guest_id' => $ahmad->id, 'booking_code' => 'CARL-DEMO0001', 'source' => Reservation::SOURCE_DIRECT,
            'check_in' => now()->subDays(1)->toDateString(), 'check_out' => now()->addDays(2)->toDateString(),
            'status' => Reservation::STATUS_CHECKED_IN, 'payment_method' => Reservation::PAYMENT_CASH, 'total_usd' => 450.00,
        ]);
        $room = Room::where('room_type_id', $deluxeKing->id)->first();
        ReservationRoom::create(['reservation_id' => $ahmadReservation->id, 'room_type_id' => $deluxeKing->id, 'room_id' => $room->id, 'price_usd' => 450.00]);
        $room->update(['status' => 'occupied']);
        Payment::create(['payable_type' => Reservation::class, 'payable_id' => $ahmadReservation->id, 'method' => 'cash', 'amount_usd' => 450.00, 'recorded_by' => $staff->id, 'note' => 'Paid at check-in', 'status' => 'completed']);

        // --- Layla: confirmed, not yet checked in ---
        $layla = Guest::where('phone', GuestSeeder::PHONE_CONFIRMED)->firstOrFail();
        $laylaReservation = Reservation::create([
            'guest_id' => $layla->id, 'booking_code' => 'CARL-DEMO0002', 'source' => Reservation::SOURCE_DIRECT,
            'check_in' => now()->addDays(5)->toDateString(), 'check_out' => now()->addDays(8)->toDateString(),
            'status' => Reservation::STATUS_CONFIRMED, 'payment_method' => Reservation::PAYMENT_ON_ARRIVAL, 'total_usd' => 840.00,
        ]);
        ReservationRoom::create(['reservation_id' => $laylaReservation->id, 'room_type_id' => $executive->id, 'room_id' => null, 'price_usd' => 840.00]);

        // --- Sara: checked out, past stay ---
        $sara = Guest::where('phone', GuestSeeder::PHONE_CHECKED_OUT)->firstOrFail();
        $saraReservation = Reservation::create([
            'guest_id' => $sara->id, 'booking_code' => 'CARL-DEMO0003', 'source' => Reservation::SOURCE_DIRECT,
            'check_in' => now()->subDays(10)->toDateString(), 'check_out' => now()->subDays(7)->toDateString(),
            'status' => Reservation::STATUS_CHECKED_OUT, 'payment_method' => Reservation::PAYMENT_CASH, 'total_usd' => 270.00,
        ]);
        $saraRoom = Room::where('room_type_id', $standardQueen->id)->first();
        ReservationRoom::create(['reservation_id' => $saraReservation->id, 'room_type_id' => $standardQueen->id, 'room_id' => $saraRoom->id, 'price_usd' => 270.00]);
        Payment::create(['payable_type' => Reservation::class, 'payable_id' => $saraReservation->id, 'method' => 'cash', 'amount_usd' => 270.00, 'recorded_by' => $staff->id, 'note' => null, 'status' => 'completed']);

        // --- Omar: cancelled ---
        $omar = Guest::where('phone', GuestSeeder::PHONE_CANCELLED)->firstOrFail();
        $omarReservation = Reservation::create([
            'guest_id' => $omar->id, 'booking_code' => 'CARL-DEMO0004', 'source' => Reservation::SOURCE_DIRECT,
            'check_in' => now()->addDays(3)->toDateString(), 'check_out' => now()->addDays(4)->toDateString(),
            'status' => Reservation::STATUS_CANCELLED, 'payment_method' => Reservation::PAYMENT_ON_ARRIVAL, 'total_usd' => 90.00,
        ]);
        ReservationRoom::create(['reservation_id' => $omarReservation->id, 'room_type_id' => $standardQueen->id, 'room_id' => null, 'price_usd' => 90.00]);

        // --- Rania: pending_verification soft hold (mid two-step public booking) ---
        $rania = Guest::where('phone', GuestSeeder::PHONE_UNVERIFIED)->firstOrFail();
        $raniaReservation = Reservation::create([
            'guest_id' => $rania->id, 'booking_code' => 'CARL-DEMO0005', 'source' => Reservation::SOURCE_DIRECT,
            'check_in' => now()->addDays(14)->toDateString(), 'check_out' => now()->addDays(16)->toDateString(),
            'status' => Reservation::STATUS_PENDING_VERIFICATION, 'hold_expires_at' => now()->addMinutes(5),
            'payment_method' => Reservation::PAYMENT_ON_ARRIVAL, 'total_usd' => 180.00,
        ]);
        ReservationRoom::create(['reservation_id' => $raniaReservation->id, 'room_type_id' => $standardQueen->id, 'room_id' => null, 'price_usd' => 180.00]);

        // A spread of plain `pending` reservations from the random guest pool,
        // for admin-list pagination and dashboard-summary volume.
        foreach (Guest::whereNotIn('phone', [
            GuestSeeder::PHONE_CHECKED_IN, GuestSeeder::PHONE_CONFIRMED, GuestSeeder::PHONE_CHECKED_OUT,
            GuestSeeder::PHONE_CANCELLED, GuestSeeder::PHONE_UNVERIFIED,
        ])->inRandomOrder()->limit(6)->get() as $guest) {
            $roomType = collect([$standardQueen, $deluxeKing, $family])->random();
            $checkIn = now()->addDays(random_int(3, 40));
            $nights = random_int(1, 5);
            $reservation = Reservation::create([
                'guest_id' => $guest->id, 'booking_code' => 'CARL-' . strtoupper(str()->random(8)),
                'source' => Reservation::SOURCE_DIRECT, 'check_in' => $checkIn->toDateString(),
                'check_out' => $checkIn->copy()->addDays($nights)->toDateString(),
                'status' => Reservation::STATUS_PENDING, 'payment_method' => 'on_arrival',
                'total_usd' => $roomType->base_price_usd * $nights,
            ]);
            ReservationRoom::create(['reservation_id' => $reservation->id, 'room_type_id' => $roomType->id, 'room_id' => null, 'price_usd' => $roomType->base_price_usd * $nights]);
        }
    }
}
