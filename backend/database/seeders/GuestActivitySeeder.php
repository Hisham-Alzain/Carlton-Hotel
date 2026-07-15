<?php

namespace Database\Seeders;

use App\Models\CheckInApproval;
use App\Models\Conversation;
use App\Models\DeviceToken;
use App\Models\Folio;
use App\Models\FolioItem;
use App\Models\Guest;
use App\Models\GuestDocument;
use App\Models\GuestNotification;
use App\Models\Message;
use App\Models\Reservation;
use App\Models\ServiceBooking;
use App\Models\ServiceRequest;
use App\Models\SpaService;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Seeder;

class GuestActivitySeeder extends Seeder
{
    public function run(): void
    {
        $ahmad = Guest::where('phone', GuestSeeder::PHONE_CHECKED_IN)->firstOrFail();
        $ahmadReservation = Reservation::where('guest_id', $ahmad->id)->firstOrFail();
        $layla = Guest::where('phone', GuestSeeder::PHONE_CONFIRMED)->firstOrFail();
        $laylaReservation = Reservation::where('guest_id', $layla->id)->firstOrFail();
        $sara = Guest::where('phone', GuestSeeder::PHONE_CHECKED_OUT)->firstOrFail();
        $saraReservation = Reservation::where('guest_id', $sara->id)->firstOrFail();
        $reception = User::where('email', 'reception@carlton.demo')->firstOrFail();
        $concierge = User::where('email', 'concierge@carlton.demo')->firstOrFail();
        $spaService = SpaService::first();

        $this->serviceLayer($ahmad, $ahmadReservation, $layla, $laylaReservation, $spaService, $reception);
        $this->preArrival($ahmad, $ahmadReservation, $layla, $laylaReservation, $reception);
        $this->folios($ahmad, $ahmadReservation, $sara, $saraReservation);
        $this->deviceTokensAndNotifications($ahmad, $ahmadReservation);
        $this->chat($ahmad, $layla, $reception, $concierge);
        $this->tickets($concierge, $reception);
    }

    private function serviceLayer(Guest $ahmad, Reservation $ahmadReservation, Guest $layla, Reservation $laylaReservation, ?SpaService $spaService, User $reception): void
    {
        // Ahmad (checked in) — one confirmed spa booking, two service requests (one open, one done).
        ServiceBooking::create([
            'guest_id' => $ahmad->id, 'reservation_id' => $ahmadReservation->id,
            'bookable_type' => 'spa_service', 'bookable_id' => $spaService->id,
            'scheduled_at' => now()->addDay(), 'status' => ServiceBooking::STATUS_CONFIRMED,
        ]);
        ServiceRequest::create([
            'guest_id' => $ahmad->id, 'reservation_id' => $ahmadReservation->id,
            'type' => 'room_service', 'department' => 'kitchen', 'status' => ServiceRequest::STATUS_NEW,
            'priority' => 'normal', 'notes' => 'Two coffees and a fruit plate, please.',
        ]);
        ServiceRequest::create([
            'guest_id' => $ahmad->id, 'reservation_id' => $ahmadReservation->id,
            'type' => 'housekeeping', 'department' => 'housekeeping', 'status' => ServiceRequest::STATUS_COMPLETED,
            'priority' => 'low', 'notes' => 'Extra towels', 'assigned_user_id' => $reception->id,
        ]);

        // Layla (pre-arrival, not checked in yet) — a scheduled spa booking for arrival day.
        ServiceBooking::create([
            'guest_id' => $layla->id, 'reservation_id' => $laylaReservation->id,
            'bookable_type' => 'spa_service', 'bookable_id' => $spaService->id,
            'scheduled_at' => $laylaReservation->check_in->copy()->addHours(2), 'status' => ServiceBooking::STATUS_PENDING,
        ]);
    }

    private function preArrival(Guest $ahmad, Reservation $ahmadReservation, Guest $layla, Reservation $laylaReservation, User $reception): void
    {
        foreach ([[$ahmad, $ahmadReservation, CheckInApproval::STATUS_APPROVED, $reception->id], [$layla, $laylaReservation, CheckInApproval::STATUS_PENDING, null]] as [$guest, $reservation, $status, $approvedBy]) {
            GuestDocument::create(['guest_id' => $guest->id, 'reservation_id' => $reservation->id, 'type' => 'passport', 'file_path' => "guest-documents/{$guest->uuid}/{$reservation->uuid}/passport-demo.jpg"]);
            CheckInApproval::create(['reservation_id' => $reservation->id, 'status' => $status, 'approved_by' => $approvedBy, 'notes' => $status === CheckInApproval::STATUS_APPROVED ? 'Documents verified.' : null]);
        }
    }

    private function folios(Guest $ahmad, Reservation $ahmadReservation, Guest $sara, Reservation $saraReservation): void
    {
        $ahmadFolio = Folio::create(['reservation_id' => $ahmadReservation->id, 'status' => Folio::STATUS_OPEN, 'subtotal_usd' => 450.00, 'total_usd' => 530.00]);
        FolioItem::create(['folio_id' => $ahmadFolio->id, 'description' => 'Room charge', 'amount_usd' => 450.00, 'source_type' => 'reservation', 'source_id' => $ahmadReservation->id]);
        FolioItem::create(['folio_id' => $ahmadFolio->id, 'description' => 'Deep Tissue Massage', 'amount_usd' => 80.00, 'source_type' => 'service_booking']);

        $saraFolio = Folio::create(['reservation_id' => $saraReservation->id, 'status' => Folio::STATUS_SETTLED, 'subtotal_usd' => 270.00, 'total_usd' => 270.00, 'approved_by_guest_at' => now()->subDays(7), 'settled_at' => now()->subDays(7)]);
        FolioItem::create(['folio_id' => $saraFolio->id, 'description' => 'Room charge', 'amount_usd' => 270.00, 'source_type' => 'reservation', 'source_id' => $saraReservation->id]);
    }

    private function deviceTokensAndNotifications(Guest $ahmad, Reservation $ahmadReservation): void
    {
        DeviceToken::create(['guest_id' => $ahmad->id, 'token' => 'demo-fcm-token-ahmad-android', 'platform' => DeviceToken::PLATFORM_ANDROID, 'last_used_at' => now()]);

        GuestNotification::create([
            'guest_id' => $ahmad->id, 'type' => GuestNotification::TYPE_WELCOME,
            'title' => 'Welcome to Carlton', 'body' => 'You are all set — we will keep you posted here throughout your stay.',
            'data' => [], 'sent_at' => now()->subDays(1),
        ]);
        GuestNotification::create([
            'guest_id' => $ahmad->id, 'type' => GuestNotification::TYPE_ROOM_READY,
            'title' => 'Your room is ready', 'body' => 'Your room has been assigned. See you soon!',
            'data' => ['reservation_uuid' => $ahmadReservation->uuid], 'sent_at' => now()->subDay(),
        ]);

        // Department-addressed notifications, mirroring what NotifyDepartmentOnInquiry produces.
        GuestNotification::create(['department' => 'sales', 'type' => GuestNotification::TYPE_INQUIRY_ROUTED, 'title' => 'New event inquiry', 'body' => 'A new inquiry from Fadi Kanaan has been routed to your department.', 'data' => [], 'sent_at' => now()->subHours(6)]);
        GuestNotification::create(['department' => 'events', 'type' => GuestNotification::TYPE_INQUIRY_ROUTED, 'title' => 'New event inquiry', 'body' => 'A new inquiry from Yasmin Nour has been routed to your department.', 'data' => [], 'sent_at' => now()->subHours(3)]);
    }

    private function chat(Guest $ahmad, Guest $layla, User $reception, User $concierge): void
    {
        // Ahmad: an answered conversation (claimed by concierge).
        $ahmadConversation = Conversation::create(['guest_id' => $ahmad->id, 'assigned_user_id' => $concierge->id, 'status' => Conversation::STATUS_OPEN, 'last_message_at' => now()->subMinutes(10)]);
        $this->message($ahmadConversation, $ahmad, 'Hi, could I get late checkout tomorrow?', now()->subMinutes(30));
        $this->message($ahmadConversation, $concierge, 'Of course — 2pm checkout is confirmed for you.', now()->subMinutes(10));

        // Layla: an unanswered conversation — exercises the unassigned-queue view.
        $laylaConversation = Conversation::create(['guest_id' => $layla->id, 'assigned_user_id' => null, 'status' => Conversation::STATUS_OPEN, 'last_message_at' => now()->subMinutes(5)]);
        $this->message($laylaConversation, $layla, 'Can I request a high floor room for my upcoming stay?', now()->subMinutes(5));
    }

    private function message(Conversation $conversation, Guest|User $sender, string $body, $createdAt): void
    {
        $message = new Message(['body' => $body]);
        $message->conversation()->associate($conversation);
        $message->sender()->associate($sender);
        $message->timestamps = false;
        $message->created_at = $createdAt;
        $message->updated_at = $createdAt;
        $message->save();
    }

    private function tickets(User $concierge, User $reception): void
    {
        $tickets = [
            ['subject' => 'WiFi not working in room', 'category' => Ticket::CATEGORY_COMPLAINT, 'status' => Ticket::STATUS_OPEN, 'priority' => 3, 'department' => Ticket::DEPARTMENT_CONCIERGE, 'assigned' => null],
            ['subject' => 'Air conditioning maintenance', 'category' => Ticket::CATEGORY_MAINTENANCE, 'status' => Ticket::STATUS_ASSIGNED, 'priority' => 2, 'department' => Ticket::DEPARTMENT_HOUSEKEEPING, 'assigned' => $reception],
            ['subject' => 'Help changing reservation dates', 'category' => Ticket::CATEGORY_BOOKING_HELP, 'status' => Ticket::STATUS_RESOLVED, 'priority' => 2, 'department' => Ticket::DEPARTMENT_RECEPTION, 'assigned' => $reception],
            ['subject' => 'General question about parking', 'category' => Ticket::CATEGORY_INQUIRY, 'status' => Ticket::STATUS_CLOSED, 'priority' => 1, 'department' => Ticket::DEPARTMENT_CONCIERGE, 'assigned' => $concierge],
            ['subject' => 'Noise complaint from adjoining room', 'category' => Ticket::CATEGORY_COMPLAINT, 'status' => Ticket::STATUS_OPEN, 'priority' => 3, 'department' => Ticket::DEPARTMENT_CONCIERGE, 'assigned' => null],
        ];

        foreach ($tickets as $t) {
            Ticket::create([
                'guest_id' => null, 'chatbot_session_id' => null, 'conversation_id' => null,
                'subject' => $t['subject'], 'category' => $t['category'], 'status' => $t['status'],
                'priority' => $t['priority'], 'department' => $t['department'], 'source' => Ticket::SOURCE_CHATBOT,
                'assigned_user_id' => $t['assigned']?->id,
            ]);
        }
    }
}
