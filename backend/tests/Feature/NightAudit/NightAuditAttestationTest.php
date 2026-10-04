<?php

namespace Tests\Feature\NightAudit;

use App\Actions\NightAudit\OpenNightAuditAction;
use App\Actions\NightAudit\ResolveNightAuditBlockerAction;
use App\Actions\NightAudit\ResolveNightAuditCheckAction;
use App\Enums\NightAuditBlockerStatus;
use App\Enums\NightAuditCheckStatus;
use App\Enums\NightAuditStatus;
use App\Enums\ReservationStatus;
use App\Models\NightAudit;
use App\Models\NightAuditBlocker;
use App\Models\NightAuditCheck;
use App\Models\Reservation;
use App\Models\Room;
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
 * Phase 9 (D-06, D-12, D-13, D-14, D-22): attesting checks (AUDIT-02) and
 * resolving blockers (AUDIT-03), independently, by the authenticated manager.
 */
class NightAuditAttestationTest extends TestCase
{
    use CountsDomainQueries, RecordsRowLocks, RefreshDatabase;

    private const D = '2026-10-10';

    private User $manager;
    private NightAudit $audit;
    private NightAuditCheck $departures;
    private NightAuditCheck $dirty;
    private NightAuditCheck $passed;
    private NightAuditBlocker $blocker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        config(['hotel.timezone' => 'Asia/Damascus']);
        $this->travelTo(Carbon::parse('2026-10-10 19:00:00', 'UTC'));

        Reservation::factory()->create([
            'status' => ReservationStatus::CHECKED_IN, 'check_in' => '2026-10-07', 'check_out' => self::D,
        ]);
        Room::factory()->create(['status' => 'dirty']);

        $this->manager = User::factory()->create();
        $this->manager->givePermissionTo('night_audit.manage');
        app(OpenNightAuditAction::class)->handle(self::D, true, $this->manager);

        $this->audit = NightAudit::with(['checks', 'blockers'])->sole();
        $byType = $this->audit->checks->keyBy(fn ($c) => $c->type->value);
        $this->departures = $byType['unsettled_departures'];
        $this->dirty      = $byType['dirty_rooms'];
        $this->passed     = $byType['unassigned_arrivals'];
        $this->blocker    = $this->audit->blockers->sole();
    }

    private function token(?User $user = null): string
    {
        $this->app['auth']->forgetGuards();

        return ($user ?? $this->manager)->createToken('t')->plainTextToken;
    }

    private function patchCheck(NightAuditCheck|string $check, array $body, ?User $user = null)
    {
        $uuid = is_string($check) ? $check : $check->uuid;

        return $this->withToken($this->token($user))->patchJson("/api/operations/night-audit/checks/{$uuid}", $body);
    }

    private function patchBlocker(NightAuditBlocker|string $blocker, array $body, ?User $user = null)
    {
        $uuid = is_string($blocker) ? $blocker : $blocker->uuid;

        return $this->withToken($this->token($user))->patchJson("/api/operations/night-audit/blockers/{$uuid}", $body);
    }

    // ---- checks (AUDIT-02) --------------------------------------------------

    public function test_resolving_a_check_records_actor_note_and_time(): void
    {
        $response = $this->patchCheck($this->departures, ['status' => 'resolved', 'note' => '  Folio settled at desk.  '])
            ->assertOk()
            ->assertJsonPath('message', __('custom.messages.night_audit_check_updated'));

        $check = collect($response->json('data.audit.checks'))->firstWhere('uuid', $this->departures->uuid);
        $this->assertSame('resolved', $check['status']);
        $this->assertSame('Folio settled at desk.', $check['note']);
        $this->assertSame(['uuid' => $this->manager->uuid, 'name' => $this->manager->name], $check['acted_by']);
        $this->assertStringEndsWith('Z', $check['acted_at']);
        $this->assertSame(['checks_pending' => 1, 'blockers_open' => 1, 'can_close' => false], $response->json('data.audit.readiness'));

        // Independence (D-06): the blocker is untouched.
        $this->assertSame(NightAuditBlockerStatus::OPEN, $this->blocker->fresh()->status);
        $this->assertSame('open', $response->json('data.audit.blockers.0.status'));
    }

    public function test_overriding_an_advisory_check(): void
    {
        $this->patchCheck($this->dirty, ['status' => 'overridden', 'note' => 'Rooms 101/102 cleaned after cut-off.'])
            ->assertOk();

        $check = $this->dirty->fresh();
        $this->assertSame(NightAuditCheckStatus::OVERRIDDEN, $check->status);
        $this->assertSame($this->manager->id, $check->acted_by);
        $this->assertNotNull($check->acted_at);
    }

    public function test_client_actor_and_snapshot_fields_are_ignored(): void
    {
        $other = User::factory()->create();
        $snapshot = DB::table('night_audit_checks')->where('id', $this->departures->id)
            ->first(['type', 'blocking', 'issue_count', 'evidence', 'evidence_truncated', 'uuid', 'night_audit_id']);

        $this->patchCheck($this->departures, [
            'status' => 'resolved', 'note' => 'ok',
            'acted_by' => $other->id, 'actor' => $other->uuid, 'done' => true,
            'issue_count' => 0, 'evidence' => [], 'uuid' => (string) Str::uuid(), 'acted_at' => '2020-01-01T00:00:00Z',
        ])->assertOk();

        $this->assertSame($this->manager->id, $this->departures->fresh()->acted_by);
        $this->assertTrue(now()->equalTo($this->departures->fresh()->acted_at));
        $this->assertEquals($snapshot, DB::table('night_audit_checks')->where('id', $this->departures->id)
            ->first(['type', 'blocking', 'issue_count', 'evidence', 'evidence_truncated', 'uuid', 'night_audit_id']));
    }

    public function test_terminal_check_cannot_be_changed_again(): void
    {
        $this->patchCheck($this->departures, ['status' => 'resolved', 'note' => 'first'])->assertOk();

        $this->patchCheck($this->departures, ['status' => 'overridden', 'note' => 'second'])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'night_audit_item_resolved')
            ->assertJsonPath('context.item', 'check')
            ->assertJsonPath('context.status', 'resolved');

        $this->assertSame('first', $this->departures->fresh()->note);
    }

    public function test_passed_check_cannot_be_attested(): void
    {
        $this->patchCheck($this->passed, ['status' => 'resolved', 'note' => 'x'])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'night_audit_item_resolved')
            ->assertJsonPath('context.item', 'check')
            ->assertJsonPath('context.status', 'passed');
    }

    // ---- blockers (AUDIT-03) ------------------------------------------------

    public function test_resolving_a_blocker_leaves_its_check_pending(): void
    {
        $response = $this->patchBlocker($this->blocker, ['note' => ' Guest on city ledger; AR follows. '])
            ->assertOk()
            ->assertJsonPath('message', __('custom.messages.night_audit_blocker_resolved'));

        $blocker = $response->json('data.audit.blockers.0');
        $this->assertSame('resolved', $blocker['status']);
        $this->assertSame('Guest on city ledger; AR follows.', $blocker['note']);
        $this->assertSame($this->manager->uuid, $blocker['acted_by']['uuid']);
        $this->assertStringEndsWith('Z', $blocker['acted_at']);
        $this->assertSame(['checks_pending' => 2, 'blockers_open' => 0, 'can_close' => false], $response->json('data.audit.readiness'));

        $this->assertSame(NightAuditCheckStatus::PENDING, $this->departures->fresh()->status);
    }

    public function test_blocker_accepts_explicit_resolved_status(): void
    {
        $this->patchBlocker($this->blocker, ['status' => 'resolved', 'note' => 'done'])->assertOk();
        $this->assertSame(NightAuditBlockerStatus::RESOLVED, $this->blocker->fresh()->status);
    }

    public function test_resolved_blocker_cannot_be_resolved_again(): void
    {
        $this->patchBlocker($this->blocker, ['note' => 'first'])->assertOk();

        $this->patchBlocker($this->blocker, ['note' => 'second'])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'night_audit_item_resolved')
            ->assertJsonPath('context.item', 'blocker')
            ->assertJsonPath('context.status', 'resolved');
    }

    public function test_blockers_have_no_override(): void
    {
        $this->patchBlocker($this->blocker, ['status' => 'overridden', 'note' => 'x'])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_failed')
            ->assertJsonValidationErrors(['status']);

        $this->assertSame(NightAuditBlockerStatus::OPEN, $this->blocker->fresh()->status);
    }

    // ---- closed first ---------------------------------------------------------

    public function test_closed_audit_refuses_changes_before_the_terminal_guard(): void
    {
        DB::table('night_audits')->where('id', $this->audit->id)->update([
            'status' => NightAuditStatus::CLOSED->value, 'closed_at' => now(), 'closed_by' => $this->manager->id,
        ]);

        foreach ([$this->patchCheck($this->departures, ['status' => 'resolved', 'note' => 'x']),
                  $this->patchCheck($this->passed, ['status' => 'resolved', 'note' => 'x']),
                  $this->patchBlocker($this->blocker, ['note' => 'x'])] as $response) {
            $response->assertStatus(422)
                ->assertJsonPath('error_code', 'night_audit_closed')
                ->assertJsonPath('context.business_date', self::D)
                ->assertJsonPath('context.closed_at', $this->audit->fresh()->closed_at->toIso8601ZuluString());
        }
    }

    // ---- validation, 404 --------------------------------------------------

    public function test_check_validation(): void
    {
        $cases = [
            ['status' => 'resolved'],
            ['status' => 'resolved', 'note' => '   '],
            ['status' => 'resolved', 'note' => str_repeat('a', 1001)],
            ['note' => 'x'],
            ['status' => 'passed', 'note' => 'x'],
            ['status' => 'pending', 'note' => 'x'],
            ['status' => 'foo', 'note' => 'x'],
            ['status' => 'resolved', 'note' => ['x']],
        ];

        foreach ($cases as $body) {
            $this->patchCheck($this->departures, $body)
                ->assertStatus(422)->assertJsonPath('error_code', 'validation_failed');
        }

        $this->assertSame(NightAuditCheckStatus::PENDING, $this->departures->fresh()->status);
    }

    public function test_note_of_exactly_1000_characters_is_accepted(): void
    {
        $this->patchCheck($this->departures, ['status' => 'resolved', 'note' => str_repeat('a', 1000)])->assertOk();
    }

    public function test_blocker_validation(): void
    {
        foreach ([[], ['note' => ''], ['note' => '  '], ['note' => str_repeat('b', 1001)], ['status' => 'open', 'note' => 'x']] as $body) {
            $this->patchBlocker($this->blocker, $body)
                ->assertStatus(422)->assertJsonPath('error_code', 'validation_failed');
        }
    }

    public function test_unknown_uuids_are_404(): void
    {
        $uuid = (string) Str::uuid();

        $this->patchCheck($uuid, ['status' => 'resolved', 'note' => 'x'])
            ->assertStatus(404)->assertJsonPath('error_code', 'not_found');
        $this->patchBlocker($uuid, ['note' => 'x'])
            ->assertStatus(404)->assertJsonPath('error_code', 'not_found');
    }

    // ---- locks, budget, source, log --------------------------------------------

    public function test_locks_state_then_audit_then_child(): void
    {
        $check = $this->lockedSelects(fn () => app(ResolveNightAuditCheckAction::class)
            ->handle($this->departures, 'resolved', 'x', $this->manager));
        $this->assertLockOrder($check, 'night_audit_checks');

        $blocker = $this->lockedSelects(fn () => app(ResolveNightAuditBlockerAction::class)
            ->handle($this->blocker, 'x', $this->manager));
        $this->assertLockOrder($blocker, 'night_audit_blockers');
    }

    public function test_query_budget_per_patch(): void
    {
        $check = $this->countDomainQueries(fn () => app(ResolveNightAuditCheckAction::class)
            ->handle($this->departures, 'resolved', 'x', $this->manager));
        $blocker = $this->countDomainQueries(fn () => app(ResolveNightAuditBlockerAction::class)
            ->handle($this->blocker, 'x', $this->manager));

        // Lock state, audit, child; update child; Spatie's activity-log
        // subject re-read; payload checks, blockers, users = 8 (D-22 ≤ 12).
        $this->assertSame(8, $check);
        $this->assertSame(8, $blocker);
    }

    public function test_source_rows_untouched_and_note_not_logged(): void
    {
        $tables = ['reservations', 'reservation_rooms', 'folios', 'folio_items', 'folio_item_disputes', 'rooms', 'tickets', 'payments'];
        $before = [];
        foreach ($tables as $table) {
            $before[$table] = DB::table($table)->orderBy('id')->get();
        }

        $this->patchCheck($this->departures, ['status' => 'resolved', 'note' => 'SENTINEL-CHECK-NOTE'])->assertOk();
        $this->patchBlocker($this->blocker, ['note' => 'SENTINEL-BLOCKER-NOTE'])->assertOk();

        foreach ($tables as $table) {
            $this->assertEquals($before[$table], DB::table($table)->orderBy('id')->get(), $table);
        }

        $logged = DB::table('activity_log')->where('subject_type', $this->departures->getMorphClass())->where('event', 'updated')->first();
        $this->assertNotNull($logged);
        $this->assertStringContainsString('resolved', (string) $logged->attribute_changes);
        foreach (DB::table('activity_log')->get() as $row) {
            foreach (['attribute_changes', 'properties'] as $column) {
                $this->assertStringNotContainsString('SENTINEL', (string) $row->{$column});
            }
        }
    }

    /** @param list<string> $locked */
    private function assertLockOrder(array $locked, string $child): void
    {
        $tables = array_map(function (string $sql) {
            preg_match('/from "([a-z_]+)"/', $sql, $m);

            return $m[1] ?? '?';
        }, $locked);

        $this->assertSame(['night_audit_states', 'night_audits', $child], $tables);
    }
}
