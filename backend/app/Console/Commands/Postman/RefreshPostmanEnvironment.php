<?php

namespace App\Console\Commands\Postman;

use App\Models\CheckInApproval;
use App\Models\Conversation;
use App\Models\DiningVenue;
use App\Models\EventInquiry;
use App\Models\EventSpace;
use App\Models\Facility;
use App\Models\Folio;
use App\Models\Guest;
use App\Models\Media;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\PoolCabana;
use App\Models\Promotion;
use App\Models\Reservation;
use App\Models\RestaurantTable;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\ServiceRequest;
use App\Models\SpaService;
use App\Models\Ticket;
use App\Models\Transfer;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

// Regenerates docs/postman/carlton-api.postman_environment.json against
// whatever the DB currently looks like — run after every `migrate:fresh
// --seed` so the Postman collection's tokens/uuids stay live. Requires the
// demo seeders (DatabaseSeeder) to have run; fails loudly and names the
// missing record if the expected demo data isn't there.
#[Signature('postman:refresh-env')]
#[Description('Regenerate the Postman environment file (fresh demo tokens + uuids) from the current database')]
class RefreshPostmanEnvironment extends Command
{
    public function handle(): int
    {
        $path = base_path('docs/postman/carlton-api.postman_environment.json');
        if (! File::exists($path)) {
            $this->error("Environment file not found at {$path} — nothing to refresh against. Expected the file created alongside this command to already exist.");
            return self::FAILURE;
        }

        try {
            $values = $this->collect();
        } catch (\Throwable $e) {
            $this->error('Could not collect demo data: ' . $e->getMessage());
            $this->error('Make sure you ran `php artisan migrate:fresh --seed` (the demo seeders) first, not just the base migrations.');
            return self::FAILURE;
        }

        $env = json_decode(File::get($path), true);
        $existing = collect($env['values'])->keyBy('key');

        foreach ($values as $key => $value) {
            $entry = $existing->get($key, ['key' => $key, 'type' => 'default', 'enabled' => true]);
            $entry['value'] = (string) $value;
            $existing->put($key, $entry);
        }

        $env['values'] = $existing->values()->all();
        File::put($path, json_encode($env, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        $this->info('Refreshed ' . count($values) . ' variables in ' . $path);
        $this->comment('Re-import (or re-select) the environment in Postman to pick up the new values.');

        return self::SUCCESS;
    }

    /** @return array<string, string> */
    private function collect(): array
    {
        $ahmad = Guest::where('phone', '+963900000001')->firstOrFail();
        $layla = Guest::where('phone', '+963900000002')->firstOrFail();
        $rania = Guest::where('phone', '+963900000005')->firstOrFail();

        $ahmad->tokens()->delete();
        $layla->tokens()->delete();
        $ahmadToken = $ahmad->createToken('postman-demo')->plainTextToken;
        $laylaToken = $layla->createToken('postman-demo')->plainTextToken;

        $superAdmin = User::where('email', 'super@carlton.demo')->firstOrFail();
        $superAdmin->tokens()->delete();
        $superAdminToken = $superAdmin->createToken('postman-demo')->plainTextToken;

        $ahmadReservation = Reservation::where('guest_id', $ahmad->id)->firstOrFail();
        $laylaReservation = Reservation::where('guest_id', $layla->id)->firstOrFail();
        $raniaReservation = Reservation::where('guest_id', $rania->id)->firstOrFail();
        $cancelledReservation = Reservation::where('status', Reservation::STATUS_CANCELLED)->firstOrFail();
        $pendingReservation = Reservation::where('status', Reservation::STATUS_PENDING)->first();

        $roomType = RoomType::where('name->en', 'Deluxe King')->firstOrFail();
        $standardQueen = RoomType::where('name->en', 'Standard Queen')->firstOrFail();
        $room = Room::where('room_type_id', $standardQueen->id)->where('status', 'available')->firstOrFail();

        return [
            'staff_token' => $superAdminToken,
            'guest_token' => $ahmadToken,
            'layla_guest_token' => $laylaToken,
            'guest_uuid' => $ahmad->uuid,
            'ahmad_phone' => $ahmad->phone,
            'layla_phone' => $layla->phone,
            'rania_phone' => $rania->phone,
            'ahmad_reservation_uuid' => $ahmadReservation->uuid,
            'layla_reservation_uuid' => $laylaReservation->uuid,
            'rania_reservation_uuid' => $raniaReservation->uuid,
            'cancelled_reservation_uuid' => $cancelledReservation->uuid,
            'pending_reservation_uuid' => $pendingReservation?->uuid,
            'room_type_uuid' => $roomType->uuid,
            'room_uuid' => $room->uuid,
            'facility_uuid' => Facility::firstOrFail()->uuid,
            'dining_venue_uuid' => DiningVenue::firstOrFail()->uuid,
            'event_space_uuid' => EventSpace::firstOrFail()->uuid,
            'page_slug' => Page::where('slug', 'about-us')->firstOrFail()->slug,
            'page_uuid' => Page::where('slug', 'about-us')->firstOrFail()->uuid,
            'promotion_uuid' => Promotion::firstOrFail()->uuid,
            'event_inquiry_uuid' => EventInquiry::where('status', EventInquiry::STATUS_NEW)->firstOrFail()->uuid,
            'spa_service_uuid' => SpaService::firstOrFail()->uuid,
            'restaurant_table_uuid' => RestaurantTable::firstOrFail()->uuid,
            'pool_cabana_uuid' => PoolCabana::firstOrFail()->uuid,
            'transfer_uuid' => Transfer::firstOrFail()->uuid,
            'menu_category_uuid' => MenuCategory::firstOrFail()->uuid,
            'menu_item_uuid' => MenuItem::firstOrFail()->uuid,
            'service_request_uuid' => ServiceRequest::firstOrFail()->uuid,
            'conversation_assigned_uuid' => Conversation::whereNotNull('assigned_user_id')->firstOrFail()->uuid,
            'conversation_unassigned_uuid' => Conversation::whereNull('assigned_user_id')->firstOrFail()->uuid,
            'ticket_open_uuid' => Ticket::where('status', Ticket::STATUS_OPEN)->firstOrFail()->uuid,
            'ticket_assigned_uuid' => Ticket::where('status', Ticket::STATUS_ASSIGNED)->firstOrFail()->uuid,
            'check_in_approval_reservation_uuid' => CheckInApproval::where('status', CheckInApproval::STATUS_PENDING)->firstOrFail()->reservation->uuid,
            'staff_target_uuid' => User::where('email', 'reception@carlton.demo')->firstOrFail()->uuid,
            'trainee_staff_uuid' => User::where('email', 'trainee@carlton.demo')->firstOrFail()->uuid,
            'media_uuid' => Media::first()?->uuid ?? '',
            'ahmad_folio_uuid' => Folio::where('reservation_id', $ahmadReservation->id)->firstOrFail()->uuid,
        ];
    }
}
