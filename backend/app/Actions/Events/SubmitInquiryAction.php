<?php

namespace App\Actions\Events;

use App\Enums\EventInquiryStatus;
use App\Enums\EventType;
use App\Events\InquirySubmitted;
use App\Models\EventInquiry;
use Illuminate\Support\Facades\DB;

class SubmitInquiryAction
{
    public function handle(array $data, ?int $guestId = null): array
    {
        return DB::transaction(function () use ($data, $guestId) {
            $eventType = EventType::from($data['event_type']);

            $inquiry = EventInquiry::create([
                'guest_id'        => $guestId,
                'event_space_id'  => $data['event_space_id'] ?? null,
                'name'            => $data['name'],
                'email'           => $data['email'],
                'phone'           => $data['phone'] ?? null,
                'company'         => $data['company'] ?? null,
                'event_type'      => $eventType,
                'event_date'      => $data['event_date'] ?? null,
                'expected_guests' => $data['expected_guests'] ?? null,
                'budget_usd'      => $data['budget_usd'] ?? null,
                'notes'           => $data['notes'] ?? null,
                'status'          => EventInquiryStatus::NEW,
                'department'      => $eventType->department(),
            ]);

            foreach ($data['requirements'] ?? [] as $req) {
                $inquiry->requirements()->create([
                    'type'  => $req['type'],
                    'notes' => $req['notes'] ?? null,
                ]);
            }

            event(new InquirySubmitted($inquiry));

            return ['data' => $inquiry->load('requirements'), 'code' => 201];
        });
    }
}
