<?php

namespace Tests\Feature\Events;

use App\Enums\EventChecklistItem;
use App\Http\Resources\Events\EventInquiryDetailResource;
use App\Http\Resources\Events\EventInquiryResource;
use App\Models\EventInquiry;
use App\Models\EventInquiryChecklistItem;
use App\Models\EventSpace;
use App\Models\Guest;
use App\Models\User;
use App\Services\Events\EventInquiryService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * Phase 8 read contract (D-02, D-04, D-21, D-29, D-30): the inquiry detail
 * carries the merged checklist, the deposit object and both note fields; the
 * list carries progress keys; transition errors carry context.
 */
class EventInquiryDetailTest extends TestCase
{
    use RefreshDatabase;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->token = User::factory()->create()->assignRole('events')->createToken('t')->plainTextToken;
    }

    private function show(EventInquiry $inquiry)
    {
        return $this->withToken($this->token)->getJson("/api/cms/event-inquiries/{$inquiry->uuid}");
    }

    public function test_untouched_inquiry_shows_five_open_items(): void
    {
        $inquiry = EventInquiry::factory()->create();

        $checklist = $this->show($inquiry)->assertOk()->json('data.checklist');

        $this->assertSame(['contract', 'deposit', 'guarantee', 'beo', 'av'], array_column($checklist, 'item'));
        $owners = ['contract' => 'sales', 'deposit' => 'events', 'guarantee' => 'sales', 'beo' => 'events', 'av' => 'maintenance'];

        foreach ($checklist as $entry) {
            $this->assertFalse($entry['done'], $entry['item']);
            $this->assertNull($entry['completed_at']);
            $this->assertNull($entry['completed_by']);
            $this->assertSame($entry['item'] === 'deposit', $entry['derived']);
            $this->assertSame($owners[$entry['item']], $entry['owner_department']);
            $this->assertSame(EventChecklistItem::from($entry['item'])->label(), $entry['label']);
        }
        $this->assertSame('Contract signed', $checklist[0]['label']);
        $this->assertDatabaseCount('event_inquiry_checklist_items', 0);
    }

    public function test_completed_rows_show_actor_and_time(): void
    {
        $inquiry = EventInquiry::factory()->create();
        $staff   = User::factory()->create(['name' => 'Rana Events']);

        foreach ([EventChecklistItem::CONTRACT, EventChecklistItem::BEO] as $item) {
            EventInquiryChecklistItem::factory()->for($inquiry, 'inquiry')->completed($staff)->create(['item' => $item]);
        }
        // An un-ticked row (nulls) reads as open.
        EventInquiryChecklistItem::factory()->for($inquiry, 'inquiry')->create(['item' => EventChecklistItem::AV]);

        $checklist = collect($this->show($inquiry)->assertOk()->json('data.checklist'))->keyBy('item');

        foreach (['contract', 'beo'] as $item) {
            $this->assertTrue($checklist[$item]['done']);
            $this->assertNotNull($checklist[$item]['completed_at']);
            $this->assertSame(['uuid' => $staff->uuid, 'name' => 'Rana Events'], $checklist[$item]['completed_by']);
        }
        foreach (['guarantee', 'av', 'deposit'] as $item) {
            $this->assertFalse($checklist[$item]['done'], $item);
        }
    }

    public function test_deposit_object_and_derived_tick(): void
    {
        $staff   = User::factory()->create(['name' => 'Cashier One']);
        $inquiry = EventInquiry::factory()->quoted()->withDeposit($staff, '750.00')->create();
        $payment = $inquiry->depositPayment()->first();

        $data = $this->show($inquiry)->assertOk()->json('data');

        $this->assertSame('paid', $data['deposit']['status']);
        $this->assertSame('750.00', $data['deposit']['amount_usd']);
        $this->assertSame('cash', $data['deposit']['method']);
        $this->assertNotNull($data['deposit']['paid_at']);
        $this->assertSame(['uuid' => $staff->uuid, 'name' => 'Cashier One'], $data['deposit']['received_by']);
        $this->assertSame($payment->uuid, $data['deposit']['payment_uuid']);

        $deposit = collect($data['checklist'])->firstWhere('item', 'deposit');
        $this->assertTrue($deposit['done']);
        $this->assertTrue($deposit['derived']);
        $this->assertSame($data['deposit']['paid_at'], $deposit['completed_at']);
        $this->assertSame(['uuid' => $staff->uuid, 'name' => 'Cashier One'], $deposit['completed_by']);
        $this->assertDatabaseMissing('event_inquiry_checklist_items', ['item' => 'deposit']);
    }

    public function test_unpaid_deposit_object(): void
    {
        $data = $this->show(EventInquiry::factory()->quoted()->create())->assertOk()->json('data');

        $this->assertSame([
            'status' => 'unpaid', 'amount_usd' => null, 'method' => null,
            'paid_at' => null, 'received_by' => null, 'payment_uuid' => null,
        ], $data['deposit']);
        $this->assertSame('unpaid', $data['deposit_status']);
    }

    public function test_notes_and_staff_notes_are_separate(): void
    {
        $assignee = User::factory()->create(['name' => 'Lead Planner']);
        $guest    = Guest::factory()->create();
        $space    = EventSpace::factory()->create();
        $inquiry  = EventInquiry::factory()->create([
            'notes'            => 'Garden wedding, 120 guests',
            'staff_notes'      => 'Call back Tuesday',
            'assigned_user_id' => $assignee->id,
            'guest_id'         => $guest->id,
            'event_space_id'   => $space->id,
        ]);

        $data = $this->show($inquiry)->assertOk()->json('data');

        $this->assertSame('Garden wedding, 120 guests', $data['notes']);
        $this->assertSame('Call back Tuesday', $data['staff_notes']);
        $this->assertSame($assignee->uuid, $data['assigned_to']);
        $this->assertSame(['uuid' => $assignee->uuid, 'name' => 'Lead Planner'], $data['assigned_user']);
        $this->assertSame($guest->uuid, $data['guest']['uuid']);
        $this->assertSame($space->uuid, $data['event_space']['uuid']);
        $this->assertArrayHasKey('requirements', $data);
    }

    public function test_list_rows_carry_progress_keys(): void
    {
        $staff   = User::factory()->create();
        $inquiry = EventInquiry::factory()->quoted()->withDeposit($staff)->create(['staff_notes' => 'VIP']);
        foreach ([EventChecklistItem::CONTRACT, EventChecklistItem::BEO] as $item) {
            EventInquiryChecklistItem::factory()->for($inquiry, 'inquiry')->completed($staff)->create(['item' => $item]);
        }
        EventInquiryChecklistItem::factory()->for($inquiry, 'inquiry')->create(['item' => EventChecklistItem::AV]);

        $row = $this->withToken($this->token)->getJson('/api/cms/event-inquiries')->assertOk()->json('data.items.0');

        $this->assertSame(3, $row['checklist_done_count']);
        $this->assertSame(5, $row['checklist_total']);
        $this->assertSame('paid', $row['deposit_status']);
        $this->assertNotNull($row['deposit_paid_at']);
        $this->assertSame('VIP', $row['staff_notes']);
        $this->assertArrayNotHasKey('checklist', $row);
        $this->assertArrayNotHasKey('deposit', $row);
    }

    public function test_invalid_transition_has_context(): void
    {
        $cancelled = EventInquiry::factory()->cancelled()->create();

        $this->withToken($this->token)
            ->patchJson("/api/cms/event-inquiries/{$cancelled->uuid}/status", ['status' => 'in_review'])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'inquiry_state')
            ->assertJsonPath('context.status', 'cancelled')
            ->assertJsonPath('context.allowed', []);

        $new = EventInquiry::factory()->create();

        $this->withToken($this->token)
            ->patchJson("/api/cms/event-inquiries/{$new->uuid}/status", ['status' => 'quoted'])
            ->assertStatus(422)
            ->assertJsonPath('context.status', 'new')
            ->assertJsonPath('context.allowed', ['in_review', 'cancelled']);
    }

    public function test_status_and_assign_return_the_detail_shape(): void
    {
        $inquiry = EventInquiry::factory()->create();
        $staff   = User::factory()->create();

        $status = $this->withToken($this->token)
            ->patchJson("/api/cms/event-inquiries/{$inquiry->uuid}/status", ['status' => 'in_review'])
            ->assertOk()->json('data');
        $this->assertCount(5, $status['checklist']);
        $this->assertArrayHasKey('deposit', $status);

        $assign = $this->withToken($this->token)
            ->patchJson("/api/cms/event-inquiries/{$inquiry->uuid}/assign", ['user_uuid' => $staff->uuid])
            ->assertOk()->json('data');
        $this->assertCount(5, $assign['checklist']);
        $this->assertSame($staff->uuid, $assign['assigned_user']['uuid']);
    }

    public function test_public_submit_response_carries_no_staff_fields(): void
    {
        $data = $this->postJson('/api/event-inquiries', [
            'name' => 'Jane', 'email' => 'jane@example.com', 'event_type' => 'wedding',
        ])->assertCreated()->json('data');

        foreach (['staff_notes', 'deposit_status', 'deposit_paid_at', 'checklist_done_count', 'checklist_total'] as $key) {
            $this->assertArrayNotHasKey($key, $data, $key);
        }
    }

    private function populated(int $count): void
    {
        $staff = User::factory()->create();

        EventInquiry::factory()->count($count)->quoted()->withDeposit($staff)->create([
            'assigned_user_id' => $staff->id,
            'guest_id'         => Guest::factory(),
            'event_space_id'   => EventSpace::factory(),
        ])->each(function (EventInquiry $inquiry) use ($staff) {
            $inquiry->requirements()->create(['type' => 'catering']);
            EventInquiryChecklistItem::factory()->for($inquiry, 'inquiry')->completed($staff)->create();
        });
    }

    public function test_list_budget(): void
    {
        $this->populated(3);
        $request = Request::create('/api/cms/event-inquiries');

        // count, rows (+ withCount subselect), requirements, assignedUser (D-30 cap 6).
        $this->expectsDatabaseQueryCount(4);

        $page = app(EventInquiryService::class)->adminIndex()['data'];
        EventInquiryResource::collection($page)->resolve($request);
    }

    public function test_show_budget(): void
    {
        $this->populated(1);
        $inquiry = EventInquiry::first();

        // requirements, assignedUser, guest, eventSpace, checklistItems,
        // checklistItems.completedBy, depositPayment, depositPayment.recorder (D-30 cap 9).
        $this->expectsDatabaseQueryCount(8);

        $data = app(EventInquiryService::class)->show($inquiry)['data'];
        (new EventInquiryDetailResource($data))->resolve(Request::create('/'));
    }

    public function test_requires_a_token_and_events_view(): void
    {
        $inquiry = EventInquiry::factory()->create();

        $this->getJson("/api/cms/event-inquiries/{$inquiry->uuid}")->assertStatus(401);

        $this->app['auth']->forgetGuards();
        $ticketsOnly = User::factory()->create();
        $ticketsOnly->givePermissionTo('tickets.view');

        $this->withToken($ticketsOnly->createToken('t')->plainTextToken)
            ->getJson("/api/cms/event-inquiries/{$inquiry->uuid}")
            ->assertStatus(403);
    }
}
