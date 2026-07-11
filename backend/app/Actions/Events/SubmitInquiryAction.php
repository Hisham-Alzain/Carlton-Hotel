<?php

namespace App\Actions\Events;

use App\Events\InquirySubmitted;
use App\Models\EventInquiry;
use Illuminate\Support\Facades\DB;

class SubmitInquiryAction
{
    public function handle(array $data, ?int $guestId = null): array
    {
        return DB::transaction(function () use ($data, $guestId) {
            $department = in_array($data['event_type'], EventInquiry::SALES_EVENT_TYPES)
                ? EventInquiry::DEPARTMENT_SALES
                : EventInquiry::DEPARTMENT_EVENTS;

            $inquiry = EventInquiry::create([
                'guest_id'        => $guestId,
                'event_space_id'  => $data['event_space_id'] ?? null,
                'name'            => $data['name'],
                'email'           => $data['email'],
                'phone'           => $data['phone'] ?? null,
                'company'         => $data['company'] ?? null,
                'event_type'      => $data['event_type'],
                'event_date'      => $data['event_date'] ?? null,
                'expected_guests' => $data['expected_guests'] ?? null,
                'budget_usd'      => $data['budget_usd'] ?? null,
                'notes'           => $data['notes'] ?? null,
                'status'          => EventInquiry::STATUS_NEW,
                'department'      => $department,
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
