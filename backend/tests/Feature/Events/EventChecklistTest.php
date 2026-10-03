<?php

namespace Tests\Feature\Events;

use App\Actions\Events\ToggleEventChecklistItemAction;
use App\Enums\EventChecklistItem;
use App\Models\EventInquiry;
use App\Models\EventInquiryChecklistItem;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Activitylog\Models\Activity;
use Tests\Concerns\RecordsRowLocks;
use Tests\TestCase;

/**
 * EVENT-01 (D-02, D-04, D-09, D-19, D-28, D-31): the checklist single writer.
 */
class EventChecklistTest extends TestCase
{
    use RefreshDatabase, RecordsRowLocks;

    private User $staff;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->staff = User::factory()->create(['name' => 'Events Lead']);
        $this->staff->assignRole('events');
        $this->token = $this->staff->createToken('t')->plainTextToken;
    }

    private function tick(EventInquiry $inquiry, string $item, mixed $done, ?string $token = null)
    {
        return $this->withToken($token ?? $this->token)
            ->patchJson("/api/cms/event-inquiries/{$inquiry->uuid}/checklist/{$item}", ['done' => $done]);
    }

    public function test_tick_records_actor_and_time(): void
    {
        $inquiry = EventInquiry::factory()->create();

        $response = $this->tick($inquiry, 'contract', true)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Checklist updated.')
            ->assertJsonPath('data.checklist.0.item', 'contract')
            ->assertJsonPath('data.checklist.0.done', true)
            ->assertJsonPath('data.checklist.0.completed_by.uuid', $this->staff->uuid);

        $this->assertNotNull($response->json('data.checklist.0.completed_at'));
        $this->assertDatabaseCount('event_inquiry_checklist_items', 1);
        $this->assertDatabaseHas('event_inquiry_checklist_items', [
            'event_inquiry_id' => $inquiry->id, 'item' => 'contract', 'completed_by' => $this->staff->id,
        ]);
    }

    public function test_untick_keeps_the_row_with_nulls(): void
    {
        $inquiry = EventInquiry::factory()->create();

        $this->tick($inquiry, 'contract', true)->assertOk();
        $this->tick($inquiry, 'contract', false)
            ->assertOk()
            ->assertJsonPath('data.checklist.0.done', false)
            ->assertJsonPath('data.checklist.0.completed_by', null);

        $row = EventInquiryChecklistItem::sole();
        $this->assertNull($row->completed_at);
        $this->assertNull($row->completed_by);
    }

    public function test_same_state_is_a_no_op(): void
    {
        $inquiry = EventInquiry::factory()->create();
        $this->tick($inquiry, 'beo', true)->assertOk();

        $row        = EventInquiryChecklistItem::sole();
        $updatedAt  = $row->updated_at->toIso8601String();
        $activities = Activity::where('subject_type', EventInquiryChecklistItem::class)->where('subject_id', $row->id)->count();

        $this->travel(5)->minutes();
        $this->tick($inquiry, 'beo', true)->assertOk()->assertJsonPath('data.checklist.3.done', true);

        $this->assertSame($updatedAt, $row->fresh()->updated_at->toIso8601String());
        $this->assertSame($activities, Activity::where('subject_type', EventInquiryChecklistItem::class)->where('subject_id', $row->id)->count());
    }

    public function test_untick_of_untouched_item_creates_no_row(): void
    {
        $inquiry = EventInquiry::factory()->create();

        $this->tick($inquiry, 'guarantee', false)->assertOk()->assertJsonPath('data.checklist.2.done', false);

        $this->assertDatabaseCount('event_inquiry_checklist_items', 0);
    }

    public function test_existing_row_is_reused(): void
    {
        $inquiry = EventInquiry::factory()->create();
        EventInquiryChecklistItem::factory()->for($inquiry, 'inquiry')->create(['item' => EventChecklistItem::BEO]);

        $this->tick($inquiry, 'beo', true)->assertOk()->assertJsonPath('data.checklist.3.done', true);

        $this->assertDatabaseCount('event_inquiry_checklist_items', 1);
    }

    /**
     * The unique (event_inquiry_id, item) index is the backstop for a writer
     * that loses the insert race: the action re-reads the winner instead of failing.
     */
    public function test_unique_backstop_reuses_the_winning_row(): void
    {
        $inquiry = EventInquiry::factory()->create();
        $raced   = false;

        // Right after the action's "is there a row?" read misses, a concurrent
        // writer inserts the row (outside the action's insert savepoint).
        DB::listen(function ($event) use (&$raced, $inquiry): void {
            if ($raced || ! str_starts_with($event->sql, 'select') || ! str_contains($event->sql, 'event_inquiry_checklist_items')) {
                return;
            }
            $raced = true;
            DB::table('event_inquiry_checklist_items')->insert([
                'uuid' => (string) Str::uuid(), 'event_inquiry_id' => $inquiry->id,
                'item' => 'contract', 'created_at' => now(), 'updated_at' => now(),
            ]);
        });

        $result = app(ToggleEventChecklistItemAction::class)
            ->handle($inquiry, EventChecklistItem::CONTRACT, true, $this->staff);

        $this->assertSame(200, $result['code']);
        $this->assertDatabaseCount('event_inquiry_checklist_items', 1);
        $this->assertNotNull(EventInquiryChecklistItem::sole()->completed_at);
        $this->assertSame($this->staff->id, (int) EventInquiryChecklistItem::sole()->completed_by);
    }

    public function test_derived_deposit_item_is_422(): void
    {
        $inquiry = EventInquiry::factory()->quoted()->create();

        $this->tick($inquiry, 'deposit', true)
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'event_checklist_item_derived')
            ->assertJsonPath('context.item', 'deposit');

        $this->assertDatabaseCount('event_inquiry_checklist_items', 0);
    }

    public function test_unknown_item_is_404(): void
    {
        $inquiry = EventInquiry::factory()->create();

        $this->tick($inquiry, 'foo', true)->assertStatus(404)->assertJsonPath('error_code', 'not_found');
    }

    public function test_cancelled_inquiry_is_422_with_context(): void
    {
        $inquiry = EventInquiry::factory()->cancelled()->create();

        $this->tick($inquiry, 'contract', true)
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'inquiry_state')
            ->assertJsonPath('context.status', 'cancelled')
            ->assertJsonPath('context.allowed', ['new', 'in_review', 'quoted', 'confirmed']);

        $this->assertDatabaseCount('event_inquiry_checklist_items', 0);
    }

    public static function openStatuses(): array
    {
        return [['new'], ['in_review'], ['quoted'], ['confirmed']];
    }

    #[DataProvider('openStatuses')]
    public function test_every_open_status_accepts_a_tick(string $status): void
    {
        $inquiry = EventInquiry::factory()->create(['status' => $status]);

        $this->tick($inquiry, 'av', true)->assertOk()->assertJsonPath('data.checklist.4.done', true);
    }

    public static function badDone(): array
    {
        return ['missing' => [null], 'string yes' => ['yes'], 'array' => [[true]]];
    }

    #[DataProvider('badDone')]
    public function test_done_must_be_a_boolean(mixed $done): void
    {
        $inquiry = EventInquiry::factory()->create();

        $body = $done === null ? [] : ['done' => $done];

        $this->withToken($this->token)
            ->patchJson("/api/cms/event-inquiries/{$inquiry->uuid}/checklist/contract", $body)
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_failed')
            ->assertJsonValidationErrors(['done']);
    }

    public function test_tick_locks_the_inquiry_row(): void
    {
        $inquiry = EventInquiry::factory()->create();

        $this->assertLocksRow('event_inquiries', fn () => $this->tick($inquiry, 'contract', true)->assertOk());
    }

    public function test_patch_read_budget(): void
    {
        $inquiry = EventInquiry::factory()->quoted()->withDeposit($this->staff)->create();
        $action  = app(ToggleEventChecklistItemAction::class);

        $selects = 0;
        DB::listen(function ($event) use (&$selects): void {
            if (str_starts_with(strtolower(ltrim($event->sql)), 'select')) {
                $selects++;
            }
        });

        $action->handle($inquiry, EventChecklistItem::CONTRACT, true, $this->staff);

        $this->assertLessThanOrEqual(9, $selects, "checklist PATCH ran {$selects} reads (D-30 cap 9)");
    }

    public function test_requires_a_token(): void
    {
        $inquiry = EventInquiry::factory()->create();

        $this->patchJson("/api/cms/event-inquiries/{$inquiry->uuid}/checklist/contract", ['done' => true])
            ->assertStatus(401);
    }

    public function test_events_view_alone_is_403(): void
    {
        $inquiry = EventInquiry::factory()->create();
        $this->app['auth']->forgetGuards();
        $viewer = User::factory()->create();
        $viewer->givePermissionTo('events.view');

        $this->tick($inquiry, 'contract', true, $viewer->createToken('t')->plainTextToken)->assertStatus(403);
        $this->assertDatabaseCount('event_inquiry_checklist_items', 0);
    }
}
