<?php

namespace Tests\Feature\NightAudit;

use App\Actions\NightAudit\OpenNightAuditAction;
use App\Enums\FolioStatus;
use App\Enums\NightAuditBlockerStatus;
use App\Enums\NightAuditCheckStatus;
use App\Enums\NightAuditCheckType;
use App\Enums\NightAuditStatus;
use App\Enums\ReservationStatus;
use App\Exceptions\DomainException;
use App\Exceptions\ForbiddenException;
use App\Exceptions\NightAuditDateInFutureException;
use App\Exceptions\NightAuditDateMismatchException;
use App\Exceptions\NightAuditNotInitializedException;
use App\Models\Folio;
use App\Models\FolioItemDispute;
use App\Models\NightAudit;
use App\Models\NightAuditBlocker;
use App\Models\NightAuditCheck;
use App\Models\NightAuditState;
use App\Models\Reservation;
use App\Models\ReservationRoom;
use App\Models\Room;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CountsDomainQueries;
use Tests\Concerns\RecordsRowLocks;
use Tests\TestCase;

/**
 * Phase 9 (D-03, D-04, D-06, D-09, D-11, D-22, D-23): lazy, idempotent,
 * lock-serialized audit creation at action level.
 */
class OpenNightAuditActionTest extends TestCase
{
    use CountsDomainQueries, RecordsRowLocks, RefreshDatabase;

    private const D = '2026-10-10';

    private User $actor;

    protected function setUp(): void
    {
        parent::setUp();
        config(['hotel.timezone' => 'Asia/Damascus']);
        // 22:00 hotel time on D (UTC+3).
        $this->travelTo(Carbon::parse('2026-10-10 19:00:00', 'UTC'));
        $this->actor = User::factory()->create();
    }

    private function open(?string $date = null, bool $canInitialize = true, ?OpenNightAuditAction $action = null): array
    {
        return ($action ?? app(OpenNightAuditAction::class))->handle($date, $canInitialize, $this->actor)['data'];
    }

    private function initializedAt(string $date): NightAuditState
    {
        return NightAuditState::factory()->on($date)->create();
    }

    private function expectDomain(string $class, array $context, callable $run): void
    {
        try {
            $run();
            $this->fail("Expected {$class}");
        } catch (DomainException $e) {
            $this->assertInstanceOf($class, $e);
            $this->assertSame($context, $e->context());
        }
    }

    private function departure(string $date = self::D): Reservation
    {
        return Reservation::factory()->create([
            'status' => ReservationStatus::CHECKED_IN, 'check_in' => '2026-10-07', 'check_out' => $date,
        ]);
    }

    // ---- initialization (D-03) ----------------------------------------------

    public function test_uninitialized_without_date_requires_a_date(): void
    {
        $this->expectDomain(NightAuditNotInitializedException::class, ['requires' => 'date'], fn () => $this->open(null, true));
        $this->assertSame(0, NightAuditState::count());
    }

    public function test_uninitialized_without_initialize_right_is_forbidden(): void
    {
        $this->expectDomain(ForbiddenException::class, ['reason' => 'night_audit_not_initialized'], fn () => $this->open(self::D, false));
        $this->assertSame(0, NightAuditState::count());
        $this->assertSame(0, NightAudit::count());
    }

    public function test_initialization_rejects_a_future_date(): void
    {
        $this->expectDomain(
            NightAuditDateInFutureException::class,
            ['requested_date' => '2026-10-11', 'hotel_today' => self::D],
            fn () => $this->open('2026-10-11'),
        );
        $this->assertSame(0, NightAuditState::count());
    }

    public function test_initialization_creates_state_and_audit(): void
    {
        $data = $this->open(self::D);

        $state = NightAuditState::sole();
        $this->assertSame(self::D, $state->current_business_date->toDateString());
        $this->assertNull($state->last_closed_date);
        $this->assertSame(self::D, $data['audit']->business_date->toDateString());
        $this->assertSame(1, NightAudit::count());
    }

    public function test_initialization_with_a_past_date_is_allowed(): void
    {
        $data = $this->open('2026-10-08');

        $this->assertSame('2026-10-08', NightAuditState::sole()->current_business_date->toDateString());
        $this->assertSame('2026-10-08', $data['audit']->business_date->toDateString());
    }

    // ---- targeting (D-04) ---------------------------------------------------

    public function test_omitted_date_targets_the_current_business_date(): void
    {
        $this->initializedAt(self::D);

        $data = $this->open(null, false);

        $this->assertSame(self::D, $data['audit']->business_date->toDateString());
        $this->assertSame(self::D, $data['state']->current_business_date->toDateString());
    }

    public function test_explicit_current_date_returns_the_same_audit(): void
    {
        $this->initializedAt(self::D);
        $first  = $this->open();
        $second = $this->open(self::D);

        $this->assertSame($first['audit']->uuid, $second['audit']->uuid);
    }

    public function test_other_date_without_audit_is_a_mismatch(): void
    {
        $this->initializedAt(self::D);

        $this->expectDomain(
            NightAuditDateMismatchException::class,
            ['requested_date' => '2026-10-09', 'current_business_date' => self::D],
            fn () => $this->open('2026-10-09'),
        );
        $this->assertSame(0, NightAudit::count());
    }

    public function test_existing_closed_history_audit_is_returned_unchanged(): void
    {
        $this->initializedAt(self::D);
        $old = NightAudit::factory()->closed()->create(['business_date' => '2026-10-07']);
        $before = DB::table('night_audits')->where('id', $old->id)->first();

        $data = $this->open('2026-10-07', false);

        $this->assertSame($old->uuid, $data['audit']->uuid);
        $this->assertSame(NightAuditStatus::CLOSED, $data['audit']->status);
        $this->assertEquals($before, DB::table('night_audits')->where('id', $old->id)->first());
    }

    public function test_current_date_after_hotel_today_returns_a_null_audit(): void
    {
        $this->initializedAt('2026-10-11');

        $data = $this->open();

        $this->assertNull($data['audit']);
        $this->assertSame('2026-10-11', $data['state']->current_business_date->toDateString());
        $this->assertSame(0, NightAudit::count());
    }

    // ---- snapshot (D-06, D-09) ----------------------------------------------

    public function test_creation_persists_five_checks_and_blockers(): void
    {
        $this->departure();
        $this->departure();
        foreach (range(1, 3) as $n) {
            Room::factory()->create(['status' => 'dirty']);
        }
        $this->initializedAt(self::D);

        $audit = $this->open()['audit'];

        $this->assertSame(NightAuditStatus::OPEN, $audit->status);
        $this->assertSame('current_state_at_open', $audit->snapshot_basis);
        $this->assertNotNull($audit->evaluated_at);
        $this->assertSame($this->actor->id, $audit->opened_by);
        $this->assertTrue($audit->opener->is($this->actor));

        $checks = $audit->checks;
        $this->assertSame(
            array_map(fn ($t) => $t->value, NightAuditCheckType::cases()),
            $checks->map(fn ($c) => $c->type->value)->all(),
        );

        $byType = $checks->keyBy(fn ($c) => $c->type->value);
        $departures = $byType['unsettled_departures'];
        $this->assertSame(NightAuditCheckStatus::PENDING, $departures->status);
        $this->assertTrue($departures->blocking);
        $this->assertSame(2, $departures->issue_count);
        $this->assertCount(2, $departures->evidence);

        $this->assertSame(NightAuditCheckStatus::PASSED, $byType['unassigned_arrivals']->status);
        $this->assertTrue($byType['unassigned_arrivals']->blocking);

        $dirty = $byType['dirty_rooms'];
        $this->assertSame(NightAuditCheckStatus::PENDING, $dirty->status);
        $this->assertFalse($dirty->blocking);
        $this->assertSame(3, $dirty->issue_count);

        $this->assertCount(1, $audit->blockers);
        $blocker = $audit->blockers->first();
        $this->assertSame(NightAuditBlockerStatus::OPEN, $blocker->status);
        $this->assertSame($departures->id, $blocker->night_audit_check_id);
        $this->assertSame($audit->id, $blocker->night_audit_id);
        $this->assertNotEmpty($blocker->uuid);
        $this->assertTrue($blocker->check->is($departures));
    }

    public function test_both_blocking_categories_get_distinct_blockers(): void
    {
        $this->departure();
        $arrival = Reservation::factory()->create([
            'status' => ReservationStatus::CONFIRMED, 'check_in' => self::D, 'check_out' => '2026-10-12',
        ]);
        ReservationRoom::factory()->create(['reservation_id' => $arrival->id, 'room_id' => null]);
        $this->initializedAt(self::D);

        $audit = $this->open()['audit'];

        $this->assertCount(2, $audit->blockers);
        $this->assertCount(2, $audit->blockers->pluck('uuid')->unique());
        $this->assertSame(
            ['unsettled_departures', 'unassigned_arrivals'],
            $audit->blockers->map(fn ($b) => $b->check->type->value)->all(),
        );
    }

    public function test_reopening_is_idempotent_and_never_re_evaluates(): void
    {
        $departure = $this->departure();
        $folio = Folio::factory()->create(['reservation_id' => $departure->id, 'status' => FolioStatus::OPEN]);
        $room = Room::factory()->create(['status' => 'dirty']);
        $this->initializedAt(self::D);

        $first = $this->open()['audit'];
        $snapshot = DB::table('night_audit_checks')->orderBy('id')->get();

        $this->travel(5)->minutes();
        DB::table('rooms')->where('id', $room->id)->update(['status' => 'available']);
        DB::table('folios')->where('id', $folio->id)->update(['status' => FolioStatus::SETTLED->value]);

        $second = $this->open()['audit'];

        $this->assertSame($first->uuid, $second->uuid);
        $this->assertTrue($first->evaluated_at->equalTo($second->evaluated_at));
        $this->assertSame(1, NightAudit::count());
        $this->assertSame(5, NightAuditCheck::count());
        $this->assertSame(1, NightAuditBlocker::count());
        $this->assertEquals($snapshot, DB::table('night_audit_checks')->orderBy('id')->get());
    }

    // ---- unique recovery (D-11) ---------------------------------------------

    public function test_unique_violation_is_recovered_by_one_re_read(): void
    {
        $this->initializedAt(self::D);
        $seeded = NightAudit::factory()->create(['business_date' => self::D]);

        $action = new class(app(\App\Services\Operations\NightAuditService::class), app(\App\Support\NightAuditEvaluator::class)) extends OpenNightAuditAction
        {
            public int $lookups = 0;

            protected function findAudit(string $businessDate): ?NightAudit
            {
                return ++$this->lookups === 1 ? null : parent::findAudit($businessDate);
            }
        };

        $data = $this->open(null, true, $action);

        $this->assertSame($seeded->uuid, $data['audit']->uuid);
        $this->assertSame(2, $action->lookups);
        $this->assertSame(1, NightAudit::count());
        $this->assertSame(0, NightAuditCheck::count());
    }

    public function test_other_database_errors_propagate(): void
    {
        $this->initializedAt(self::D);

        DB::listen(function ($event): void {
            if (str_starts_with(strtolower($event->sql), 'insert into "night_audit_checks"')) {
                throw new QueryException('sqlite', $event->sql, [], new \PDOException('disk I/O error'));
            }
        });

        try {
            $this->open();
            $this->fail('Expected QueryException');
        } catch (QueryException $e) {
            $this->assertStringContainsString('disk I/O error', $e->getMessage());
        }

        $this->assertSame(0, NightAudit::count());
    }

    // ---- locks, budgets, source -----------------------------------------------

    public function test_state_row_is_locked_before_the_audit_lookup(): void
    {
        $this->initializedAt(self::D);
        $statements = [];
        DB::listen(function ($event) use (&$statements): void {
            $statements[] = $event->sql;
        });

        $locked = $this->lockedSelects(fn () => $this->open());

        $this->assertNotEmpty(array_filter($locked, fn ($sql) => str_contains($sql, 'from "night_audit_states"')));
        $stateAt = array_key_first(array_filter($statements, fn ($sql) => str_contains($sql, 'from "night_audit_states"')));
        $auditAt = array_key_first(array_filter($statements, fn ($sql) => str_contains($sql, 'from "night_audits"')));
        $this->assertNotNull($stateAt);
        $this->assertNotNull($auditAt);
        $this->assertLessThan($auditAt, $stateAt);
    }

    public function test_first_open_and_re_read_budgets_do_not_depend_on_volume(): void
    {
        $this->initializedAt(self::D);
        $empty = $this->countDomainQueries(fn () => $this->open());
        $emptyReread = $this->countDomainQueries(fn () => $this->open());

        DB::table('night_audit_blockers')->delete();
        DB::table('night_audit_checks')->delete();
        DB::table('night_audits')->delete();
        $this->seedEveryCategory(25);

        $full = $this->countDomainQueries(fn () => $this->open());
        $fullReread = $this->countDomainQueries(fn () => $this->open());

        $audit = NightAudit::sole();
        $this->assertSame(2, $audit->blockers()->count());
        foreach ($audit->checks as $check) {
            $this->assertSame(25, $check->issue_count, $check->type->value);
        }

        // First open (D-22 ≤ 24): state lock, audit lookup, audit insert,
        // the activity-log subject re-read Spatie runs after the audit insert,
        // 10 evaluator statements, checks insert, blockers INSERT … SELECT,
        // then the payload's checks, blockers and users = 19.
        $this->assertSame($empty, $full);
        $this->assertSame(19, $full);
        // Re-read (D-22 ≤ 7): state lock, audit lookup, checks, blockers, users = 5.
        $this->assertSame($emptyReread, $fullReread);
        $this->assertSame(5, $fullReread);
    }

    public function test_initializing_open_adds_only_the_state_insert_and_reselect(): void
    {
        $count = $this->countDomainQueries(fn () => $this->open(self::D));

        // 19 + insertOrIgnore + the locked re-select of the new state row.
        $this->assertSame(21, $count);
    }

    public function test_opening_never_writes_source_rows(): void
    {
        $this->seedEveryCategory(3);
        $tables = ['reservations', 'reservation_rooms', 'folios', 'folio_items', 'folio_item_disputes', 'rooms', 'tickets', 'payments'];
        $before = [];
        foreach ($tables as $table) {
            $before[$table] = DB::table($table)->orderBy('id')->get();
        }

        $writes = array_filter(
            $this->domainQueries(fn () => $this->open(self::D)),
            fn ($sql) => preg_match('/^\s*(insert|update|delete)/i', $sql),
        );

        foreach ($tables as $table) {
            $this->assertEquals($before[$table], DB::table($table)->orderBy('id')->get(), $table);
        }
        foreach ($writes as $sql) {
            $this->assertMatchesRegularExpression('/"night_audit(s|_states|_checks|_blockers)"/', $sql);
        }
    }

    public function test_audit_messages_are_localized_in_every_locale(): void
    {
        $keys = [
            'custom.errors.night_audit_not_initialized', 'custom.errors.night_audit_date_mismatch',
            'custom.errors.night_audit_date_in_future', 'custom.errors.night_audit_closed',
            'custom.errors.night_audit_item_resolved', 'custom.errors.night_audit_not_ready',
            'custom.messages.night_audit_check_updated', 'custom.messages.night_audit_blocker_resolved',
            'custom.messages.night_audit_closed',
        ];
        foreach (NightAuditCheckType::cases() as $type) {
            $keys[] = 'custom.night_audit.checks.' . $type->value;
        }
        foreach (NightAuditCheckStatus::cases() as $status) {
            $keys[] = 'custom.night_audit.check_statuses.' . $status->value;
        }
        foreach (NightAuditBlockerStatus::cases() as $status) {
            $keys[] = 'custom.night_audit.blocker_statuses.' . $status->value;
        }
        foreach (NightAuditStatus::cases() as $status) {
            $keys[] = 'custom.night_audit.statuses.' . $status->value;
        }

        foreach (['en', 'ar', 'fr', 'tr', 'es'] as $locale) {
            foreach ($keys as $key) {
                $this->assertNotSame($key, __($key, [], $locale), "{$locale}: {$key}");
            }
        }
    }

    private function seedEveryCategory(int $n): void
    {
        for ($i = 0; $i < $n; $i++) {
            $this->departure();
            $arrival = Reservation::factory()->create([
                'status' => ReservationStatus::CONFIRMED, 'check_in' => self::D, 'check_out' => '2026-10-12',
            ]);
            ReservationRoom::factory()->create(['reservation_id' => $arrival->id, 'room_id' => null]);
            Room::factory()->create(['status' => 'dirty']);
            Ticket::factory()->create(['priority' => 3]);
            FolioItemDispute::factory()->create();
        }
    }
}
