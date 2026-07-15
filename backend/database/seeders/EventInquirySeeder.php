<?php

namespace Database\Seeders;

use App\Models\EventInquiry;
use App\Models\EventSpace;
use App\Models\Guest;
use App\Models\User;
use Illuminate\Database\Seeder;

class EventInquirySeeder extends Seeder
{
    public function run(): void
    {
        $eventsStaff = User::where('email', 'events@carlton.demo')->first();
        $grandBallroom = EventSpace::where('name->en', 'Grand Ballroom')->first();

        $inquiries = [
            ['name' => 'Yasmin Nour', 'email' => 'yasmin.nour@example.com', 'event_type' => 'wedding', 'status' => EventInquiry::STATUS_NEW, 'guests' => 180, 'budget' => 25000],
            ['name' => 'Fadi Kanaan', 'email' => 'fadi.kanaan@example.com', 'event_type' => 'corporate', 'status' => EventInquiry::STATUS_IN_REVIEW, 'guests' => 60, 'budget' => 8000, 'assign' => true],
            ['name' => 'Maya Salloum', 'email' => 'maya.salloum@example.com', 'event_type' => 'conference', 'status' => EventInquiry::STATUS_QUOTED, 'guests' => 120, 'budget' => 15000, 'assign' => true],
            ['name' => 'Rami Chamoun', 'email' => 'rami.chamoun@example.com', 'event_type' => 'birthday', 'status' => EventInquiry::STATUS_CONFIRMED, 'guests' => 40, 'budget' => 3000, 'assign' => true],
            ['name' => 'Dania Fares', 'email' => 'dania.fares@example.com', 'event_type' => 'gala', 'status' => EventInquiry::STATUS_CANCELLED, 'guests' => 200, 'budget' => 30000, 'assign' => true],
            ['name' => 'Sami Barakat', 'email' => 'sami.barakat@example.com', 'event_type' => 'product_launch', 'status' => EventInquiry::STATUS_NEW, 'guests' => 90, 'budget' => 12000],
        ];

        foreach ($inquiries as $i) {
            $department = in_array($i['event_type'], EventInquiry::SALES_EVENT_TYPES, true) ? EventInquiry::DEPARTMENT_SALES : EventInquiry::DEPARTMENT_EVENTS;

            $inquiry = EventInquiry::create([
                'guest_id' => null,
                'event_space_id' => $grandBallroom?->id,
                'name' => $i['name'], 'email' => $i['email'], 'phone' => null, 'company' => null,
                'event_type' => $i['event_type'], 'event_date' => now()->addMonths(random_int(1, 6))->toDateString(),
                'expected_guests' => $i['guests'], 'budget_usd' => $i['budget'],
                'notes' => 'Seeded demo inquiry for API testing.',
                'status' => $i['status'], 'department' => $department,
                'assigned_user_id' => ($i['assign'] ?? false) ? $eventsStaff?->id : null,
            ]);

            $inquiry->requirements()->create(['type' => 'catering', 'notes' => 'Vegetarian options required']);
            $inquiry->requirements()->create(['type' => 'av_equipment', 'notes' => 'Projector and sound system']);
        }

        // One with a guest_id linked, to demonstrate the "authenticated guest submits an inquiry" path.
        $guest = Guest::where('phone', GuestSeeder::PHONE_CHECKED_IN)->first();
        EventInquiry::create([
            'guest_id' => $guest?->id, 'event_space_id' => $grandBallroom?->id,
            'name' => 'Ahmad Khalil', 'email' => 'ahmad.khalil@example.com', 'phone' => GuestSeeder::PHONE_CHECKED_IN,
            'event_type' => 'wedding', 'event_date' => now()->addMonths(4)->toDateString(),
            'expected_guests' => 150, 'budget_usd' => 20000, 'notes' => 'Inquiry submitted while logged in.',
            'status' => EventInquiry::STATUS_NEW, 'department' => EventInquiry::DEPARTMENT_EVENTS,
        ]);
    }
}
