<?php

namespace Tests\Feature\NightAudit;

use App\Actions\NightAudit\CloseNightAuditAction;
use App\Actions\NightAudit\OpenNightAuditAction;
use App\Enums\NightAuditStatus;
use App\Enums\ReservationStatus;
use App\Models\FolioItem;
use App\Models\FolioItemDispute;
use App\Models\NightAudit;
use App\Models\NightAuditState;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\Ticket;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\CountsDomainQueries;
use Tests\Concerns\RecordsRowLocks;
use Tests\TestCase;

/**
 * Phase 9 (AUDIT-04; D-04, D-05, D-06, D-12, D-22): closing a business date.
 */
class NightAuditCloseTest extends TestCase
{
    use CountsDomainQueries, RecordsRowLocks, RefreshDatabase;

    private const D = '2026-10-10';

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        config(['hotel.timezone' => 'Asia/Damascus']);
        $this->hotelTime(self::D . ' 22:00');
        $this->manager = User::factory()->create();
        $this->manager->givePermissionTo('night_audit.manage');
    }

    private function hotelTime(string $local): void
    {
        $this->travelTo(Carbon::parse($local, config('hotel.timezone'))->utc());
    }

    private function token(): string
    {
        $this->app['auth']->forgetGuards();

        return $this->manager->createToken('t')->plainTextToken;
    }

    private function openVia(?string $date = null)
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($this->token())->getJson('/api/operations/night-audit' . ($date ? "?date={$date}" : ''));
    }

    private function close(string $auditUuid, array $body = [])
    {
        return $this->withToken($this->token())->postJson("/api/operations/night-audit/{$auditUuid}/close", $body);
    }

    private function attest(string $path, array $body)
    {
        return $this->withToken($this->token())->patchJson("/api/operations/night-audit/{$path}", $body);
    }

    private function state(): NightAuditState
    {
        return NightAuditState::sole();
    }

    // ---- readiness ------------------------------------------------------------

    public function test_pending_check_or_open_blocker_blocks_close(): void
    {
        Reservation::factory()->create([
            'status' => ReservationStatus::CHECKED_IN, 'check_in' => '2026-10-07', 'check_out' => self::D,
        ]);
        $audit = $this->openVia(self::D)->assertOk()->json('data.audit');
        $check = collect($audit['checks'])->firstWhere('type', 'unsettled_departures');

        $this->close($audit['uuid'])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'night_audit_not_ready')
            ->assertJsonPath('context.checks_pending', 1)
            ->assertJsonPath('context.blockers_open', 1);

        $this->attest("checks/{$check['uuid']}", ['status' => 'resolved', 'note' => 'settled'])->assertOk();
        $this->close($audit['uuid'])
            ->assertStatus(422)
            ->assertJsonPath('context.checks_pending', 0)
            ->assertJsonPath('context.blockers_open', 1);

        $this->assertSame(NightAuditStatus::OPEN, NightAudit::sole()->status);
        $this->assertSame(self::D, $this->state()->current_business_date->toDateString());

        $this->attest("blockers/{$check['blocker_uuid']}", ['note' => 'city ledger'])->assertOk();
        $this->close($audit['uuid'])->assertOk()->assertJsonPath('data.audit.status', 'closed');
    }

    public function test_resolved_blocker_with_pending_check_still_blocks(): void
    {
        Reservation::factory()->create([
            'status' => ReservationStatus::CHECKED_IN, 'check_in' => '2026-10-07', 'check_out' => self::D,
        ]);
        $audit = $this->openVia(self::D)->json('data.audit');
        $check = collect($audit['checks'])->firstWhere('type', 'unsettled_departures');

        $this->attest("blockers/{$check['blocker_uuid']}", ['note' => 'ok'])->assertOk();

        $this->close($audit['uuid'])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'night_audit_not_ready')
            ->assertJsonPath('context.checks_pending', 1)
            ->assertJsonPath('context.blockers_open', 0);
    }

    // ---- success ----------------------------------------------------------------

    public function test_ready_audit_closes_and_advances_the_business_date(): void
    {
        $uuid = $this->openVia(self::D)->json('data.audit.uuid');

        $response = $this->close($uuid)->assertOk()
            ->assertJsonPath('message', __('custom.messages.night_audit_closed'))
            ->assertJsonPath('data.audit.status', 'closed')
            ->assertJsonPath('data.audit.closed_by.uuid', $this->manager->uuid)
            ->assertJsonPath('data.audit.readiness.can_close', false)
            ->assertJsonPath('data.state.current_business_date', '2026-10-11')
            ->assertJsonPath('data.state.last_closed_date', self::D);

        $this->assertStringEndsWith('Z', $response->json('data.audit.closed_at'));

        $audit = NightAudit::sole();
        $this->assertSame(NightAuditStatus::CLOSED, $audit->status);
        $this->assertSame($this->manager->id, $audit->closed_by);
        $this->assertTrue(now()->equalTo($audit->closed_at));
        $this->assertSame('2026-10-11', $this->state()->current_business_date->toDateString());
        $this->assertSame(self::D, $this->state()->last_closed_date->toDateString());
    }

    public function test_body_is_ignored(): void
    {
        $uuid = $this->openVia(self::D)->json('data.audit.uuid');
        $other = User::factory()->create();

        $this->close($uuid, ['date' => '2030-01-01', 'closed_by' => $other->uuid, 'status' => 'open', 'closed_at' => '2020-01-01'])
            ->assertOk()
            ->assertJsonPath('data.audit.status', 'closed')
            ->assertJsonPath('data.audit.closed_by.uuid', $this->manager->uuid)
            ->assertJsonPath('data.state.current_business_date', '2026-10-11');
    }

    public function test_repeat_close_is_a_no_op(): void
    {
        $uuid = $this->openVia(self::D)->json('data.audit.uuid');
        $first = $this->close($uuid)->assertOk()->json('data');
        $stateBefore = DB::table('night_audit_states')->first();
        $auditBefore = DB::table('night_audits')->first();

        $this->travel(5)->minutes();
        $second = $this->close($uuid)->assertOk()->json('data');

        $this->assertSame($first['audit']['closed_at'], $second['audit']['closed_at']);
        $this->assertSame($first['state'], $second['state']);
        $this->assertEquals($stateBefore, DB::table('night_audit_states')->first());
        $this->assertEquals($auditBefore, DB::table('night_audits')->first());
    }

    // ---- calendar arithmetic (D-05) -------------------------------------------

    public function test_month_end_advances_to_the_first(): void
    {
        $this->hotelTime('2026-10-31 23:00');
        $uuid = $this->openVia('2026-10-31')->json('data.audit.uuid');

        $this->close($uuid)->assertOk()->assertJsonPath('data.state.current_business_date', '2026-11-01');
    }

    public function test_dst_change_advances_exactly_one_calendar_day(): void
    {
        config(['hotel.timezone' => 'Europe/London']);
        // 2026-10-25 is the October clock change in Europe/London (25-hour day).
        $this->hotelTime('2026-10-25 23:30');
        $uuid = $this->openVia('2026-10-25')->json('data.audit.uuid');

        $this->close($uuid)->assertOk()
            ->assertJsonPath('data.state.current_business_date', '2026-10-26')
            ->assertJsonPath('data.state.last_closed_date', '2026-10-25');
    }

    // ---- defence in depth -----------------------------------------------------

    public function test_stale_open_audit_for_another_date_is_a_mismatch(): void
    {
        NightAuditState::factory()->on(self::D, '2026-10-09')->create();
        $stale = NightAudit::factory()->create(['business_date' => '2026-10-08']);

        $this->close($stale->uuid)
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'night_audit_date_mismatch')
            ->assertJsonPath('context.requested_date', '2026-10-08')
            ->assertJsonPath('context.current_business_date', self::D);

        $this->assertSame(NightAuditStatus::OPEN, $stale->fresh()->status);
    }

    public function test_unknown_audit_is_404(): void
    {
        $this->close((string) Str::uuid())->assertStatus(404)->assertJsonPath('error_code', 'not_found');
    }

    // ---- next date and invariants -----------------------------------------------

    public function test_next_get_targets_the_next_date_once_it_arrives(): void
    {
        $this->hotelTime(self::D . ' 23:30');
        $uuid = $this->openVia(self::D)->json('data.audit.uuid');
        $this->close($uuid)->assertOk();

        $this->openVia()->assertOk()
            ->assertJsonPath('data.audit', null)
            ->assertJsonPath('data.state.current_business_date', '2026-10-11');
        $this->assertSame(1, NightAudit::count());

        $this->hotelTime('2026-10-11 01:00');
        $this->openVia()->assertOk()
            ->assertJsonPath('data.audit.business_date', '2026-10-11')
            ->assertJsonPath('data.audit.status', 'open');

        // The closed date stays readable as history.
        $this->openVia(self::D)->assertOk()->assertJsonPath('data.audit.status', 'closed');
    }

    public function test_at_most_one_open_audit_and_it_is_the_current_date(): void
    {
        $assertInvariant = function (): void {
            $open = NightAudit::query()->where('status', NightAuditStatus::OPEN)->get();
            $this->assertLessThanOrEqual(1, $open->count());
            if ($open->count() === 1) {
                $this->assertSame(
                    $this->state()->current_business_date->toDateString(),
                    $open->first()->business_date->toDateString(),
                );
            }
        };

        $uuid = $this->openVia(self::D)->json('data.audit.uuid');
        $assertInvariant();
        $this->close($uuid)->assertOk();
        $assertInvariant();

        $this->hotelTime('2026-10-11 22:00');
        $next = $this->openVia()->json('data.audit.uuid');
        $assertInvariant();
        $this->close($next)->assertOk();
        $assertInvariant();
        $this->assertSame(2, NightAudit::where('status', NightAuditStatus::CLOSED)->count());
    }

    public function test_full_cycle_never_writes_source_rows(): void
    {
        $departure = Reservation::factory()->create([
            'status' => ReservationStatus::CHECKED_IN, 'check_in' => '2026-10-07', 'check_out' => self::D,
        ]);
        $item = FolioItem::factory()->create();
        FolioItemDispute::factory()->create(['folio_item_id' => $item->id]);
        Payment::factory()->create();
        Room::factory()->create(['status' => 'dirty']);
        Ticket::factory()->create(['priority' => 3]);

        $tables = ['reservations', 'reservation_rooms', 'folios', 'folio_items', 'folio_item_disputes', 'payments', 'rooms', 'tickets'];
        $before = [];
        foreach ($tables as $table) {
            $before[$table] = DB::table($table)->orderBy('id')->get()->toArray();
        }

        $audit = $this->openVia(self::D)->json('data.audit');
        $checks = collect($audit['checks'])->keyBy('type');
        $this->attest("checks/{$checks['unsettled_departures']['uuid']}", ['status' => 'resolved', 'note' => 'a'])->assertOk();
        $this->attest("blockers/{$checks['unsettled_departures']['blocker_uuid']}", ['note' => 'b'])->assertOk();
        foreach (['dirty_rooms', 'open_high_priority_tickets', 'open_folio_disputes'] as $type) {
            $this->attest("checks/{$checks[$type]['uuid']}", ['status' => 'overridden', 'note' => 'c'])->assertOk();
        }
        $this->close($audit['uuid'])->assertOk();

        foreach ($tables as $table) {
            $this->assertEquals($before[$table], DB::table($table)->orderBy('id')->get()->toArray(), $table);
        }
        $this->assertNotNull($departure->fresh());
    }

    // ---- locks and budget --------------------------------------------------------

    public function test_locks_state_then_audit(): void
    {
        $this->openVia(self::D);
        $audit = NightAudit::sole();

        $locked = $this->lockedSelects(fn () => app(CloseNightAuditAction::class)->handle($audit, $this->manager));
        $tables = array_map(fn ($sql) => preg_match('/from "([a-z_]+)"/', $sql, $m) ? $m[1] : '?', $locked);

        $this->assertSame(['night_audit_states', 'night_audits'], $tables);
    }

    public function test_close_budget(): void
    {
        app(OpenNightAuditAction::class)->handle(self::D, true, $this->manager);
        $audit = NightAudit::sole();

        $count = $this->countDomainQueries(fn () => app(CloseNightAuditAction::class)->handle($audit, $this->manager));
        $closed = $audit->fresh();
        $repeat = $this->countDomainQueries(fn () => app(CloseNightAuditAction::class)->handle($closed, $this->manager));

        // State + audit locks; payload checks, blockers, users; audit update +
        // Spatie's subject re-read; state update + re-read = 9 (D-22 ≤ 12).
        $this->assertSame(9, $count);
        // Repeat close: locks + payload only = 5.
        $this->assertSame(5, $repeat);
    }
}
