<?php

namespace Tests\Feature\NightAudit;

use App\Enums\NightAuditCheckType;
use App\Enums\ReservationStatus;
use App\Models\Guest;
use App\Models\NightAudit;
use App\Models\NightAuditState;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CountsDomainQueries;
use Tests\TestCase;

/**
 * Phase 9 (D-01, D-03, D-04, D-13): GET /api/operations/night-audit.
 */
class NightAuditShowTest extends TestCase
{
    use CountsDomainQueries, RefreshDatabase;

    private const URL = '/api/operations/night-audit';
    private const D   = '2026-10-10';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        config(['hotel.timezone' => 'Asia/Damascus']);
        $this->travelTo(Carbon::parse('2026-10-10 19:00:00', 'UTC'));
    }

    private function user(string ...$permissions): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo($permissions);

        return $user;
    }

    private function fetch(User $user, array $query = [])
    {
        $this->app['auth']->forgetGuards();
        $url = self::URL . ($query ? '?' . http_build_query($query) : '');

        return $this->withToken($user->createToken('t')->plainTextToken)->getJson($url);
    }

    private function seedIssues(?Guest $guest = null): void
    {
        Reservation::factory()->create([
            'status' => ReservationStatus::CHECKED_IN, 'check_in' => '2026-10-07', 'check_out' => self::D,
            'guest_id' => ($guest ?? Guest::factory()->create())->id,
        ]);
        Room::factory()->create(['status' => 'dirty']);
    }

    public function test_manager_initializes_and_receives_the_d13_payload(): void
    {
        $this->seedIssues();
        $manager = $this->user('night_audit.manage');

        $response = $this->fetch($manager, ['date' => self::D])->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success', 'message', 'request_id',
                'data' => [
                    'state' => ['current_business_date', 'last_closed_date'],
                    'audit' => [
                        'uuid', 'business_date', 'status', 'snapshot_basis', 'evaluated_at',
                        'opened_by' => ['uuid', 'name'], 'closed_at', 'closed_by',
                        'readiness' => ['checks_pending', 'blockers_open', 'can_close'],
                        'checks' => ['*' => [
                            'uuid', 'type', 'label', 'blocking', 'status', 'issue_count', 'evidence',
                            'evidence_truncated', 'note', 'acted_by', 'acted_at', 'blocker_uuid',
                        ]],
                        'blockers' => ['*' => ['uuid', 'check_uuid', 'type', 'status', 'note', 'acted_by', 'acted_at']],
                    ],
                ],
            ]);

        $data = $response->json('data');
        $this->assertSame(['current_business_date' => self::D, 'last_closed_date' => null], $data['state']);

        $audit = $data['audit'];
        $this->assertSame(self::D, $audit['business_date']);
        $this->assertSame('open', $audit['status']);
        $this->assertSame('current_state_at_open', $audit['snapshot_basis']);
        $this->assertStringEndsWith('Z', $audit['evaluated_at']);
        $this->assertSame(['uuid' => $manager->uuid, 'name' => $manager->name], $audit['opened_by']);
        $this->assertNull($audit['closed_at']);
        $this->assertNull($audit['closed_by']);
        $this->assertSame(['checks_pending' => 2, 'blockers_open' => 1, 'can_close' => false], $audit['readiness']);

        $this->assertSame(
            array_map(fn ($t) => $t->value, NightAuditCheckType::cases()),
            array_column($audit['checks'], 'type'),
        );
        $checks = collect($audit['checks'])->keyBy('type');
        $this->assertSame(__('custom.night_audit.checks.dirty_rooms'), $checks['dirty_rooms']['label']);
        $this->assertSame('Dirty rooms', $checks['dirty_rooms']['label']);

        $this->assertCount(1, $audit['blockers']);
        $blocker = $audit['blockers'][0];
        $departures = $checks['unsettled_departures'];
        $this->assertTrue($departures['blocking']);
        $this->assertSame($blocker['uuid'], $departures['blocker_uuid']);
        $this->assertSame($departures['uuid'], $blocker['check_uuid']);
        $this->assertSame('unsettled_departures', $blocker['type']);
        $this->assertSame('open', $blocker['status']);
        $this->assertNull($blocker['acted_by']);

        foreach (['unassigned_arrivals', 'dirty_rooms', 'open_high_priority_tickets', 'open_folio_disputes'] as $type) {
            $this->assertNull($checks[$type]['blocker_uuid'], $type);
        }
        $this->assertFalse($checks['dirty_rooms']['blocking']);
        $this->assertTrue($checks['unassigned_arrivals']['blocking']);
        $this->assertSame('passed', $checks['unassigned_arrivals']['status']);

        $this->assertNoIdKeys($data);
    }

    public function test_labels_follow_accept_language(): void
    {
        $manager = $this->user('night_audit.manage');
        $this->app['auth']->forgetGuards();

        $label = $this->withToken($manager->createToken('t')->plainTextToken)
            ->withHeader('Accept-Language', 'ar')
            ->getJson(self::URL . '?date=' . self::D)
            ->assertOk()->json('data.audit.checks.2.label');

        $this->assertSame(trans('custom.night_audit.checks.dirty_rooms', [], 'ar'), $label);
    }

    public function test_second_get_without_date_returns_the_same_audit(): void
    {
        $manager = $this->user('night_audit.manage');
        $first = $this->fetch($manager, ['date' => self::D])->assertOk()->json('data.audit.uuid');

        $this->assertSame($first, $this->fetch($manager)->assertOk()->json('data.audit.uuid'));
        $this->assertSame(1, NightAudit::count());
    }

    public function test_uninitialized_without_date_is_422(): void
    {
        $this->fetch($this->user('night_audit.manage'))
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error_code', 'night_audit_not_initialized')
            ->assertJsonPath('context.requires', 'date');
    }

    public function test_viewer_cannot_initialize_but_reads_after_a_manager_does(): void
    {
        $viewer = $this->user('reports.view');

        $this->fetch($viewer, ['date' => self::D])
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'forbidden')
            ->assertJsonPath('context.reason', 'night_audit_not_initialized');
        $this->assertSame(0, NightAuditState::count());

        $this->fetch($this->user('night_audit.manage'), ['date' => self::D])->assertOk();

        $this->fetch($viewer)->assertOk()->assertJsonPath('data.audit.business_date', self::D);
        $this->fetch($viewer, ['date' => self::D])->assertOk();
    }

    public function test_viewer_creates_the_current_audit_lazily_once_initialized(): void
    {
        NightAuditState::factory()->on(self::D)->create();

        $this->fetch($this->user('reports.view'))->assertOk()->assertJsonPath('data.audit.business_date', self::D);
        $this->assertSame(1, NightAudit::count());
    }

    public function test_future_initial_date_is_422(): void
    {
        $this->fetch($this->user('night_audit.manage'), ['date' => '2026-10-11'])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'night_audit_date_in_future')
            ->assertJsonPath('context.requested_date', '2026-10-11')
            ->assertJsonPath('context.hotel_today', self::D);
    }

    public function test_uncreated_other_date_is_a_mismatch(): void
    {
        NightAuditState::factory()->on(self::D)->create();

        $this->fetch($this->user('night_audit.manage'), ['date' => '2026-10-09'])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'night_audit_date_mismatch')
            ->assertJsonPath('context.requested_date', '2026-10-09')
            ->assertJsonPath('context.current_business_date', self::D);
    }

    public function test_current_date_ahead_of_hotel_today_returns_null_audit(): void
    {
        NightAuditState::factory()->on('2026-10-11', self::D)->create();

        $this->fetch($this->user('reports.view'))
            ->assertOk()
            ->assertJsonPath('data.audit', null)
            ->assertJsonPath('data.state.current_business_date', '2026-10-11')
            ->assertJsonPath('data.state.last_closed_date', self::D);
    }

    public function test_invalid_dates_are_validation_errors(): void
    {
        $manager = $this->user('night_audit.manage');

        foreach (['2026-02-30', '10-10-2026', '2026-10-10T00:00', 'tomorrow'] as $bad) {
            $this->fetch($manager, ['date' => $bad])
                ->assertStatus(422)
                ->assertJsonPath('error_code', 'validation_failed')
                ->assertJsonValidationErrors(['date']);
        }

        $this->fetch($manager, ['date' => ['x']])
            ->assertStatus(422)->assertJsonPath('error_code', 'validation_failed');
        $this->assertSame(0, NightAuditState::count());
    }

    public function test_closed_history_is_readable_by_a_viewer(): void
    {
        NightAuditState::factory()->on(self::D, '2026-10-09')->create();
        $closer = User::factory()->create();
        $old = NightAudit::factory()->closed($closer)->create(['business_date' => '2026-10-07']);

        $audit = $this->fetch($this->user('reports.view'), ['date' => '2026-10-07'])->assertOk()->json('data.audit');

        $this->assertSame($old->uuid, $audit['uuid']);
        $this->assertSame('closed', $audit['status']);
        $this->assertSame(['uuid' => $closer->uuid, 'name' => $closer->name], $audit['closed_by']);
        $this->assertStringEndsWith('Z', $audit['closed_at']);
        $this->assertFalse($audit['readiness']['can_close']);
    }

    public function test_response_carries_no_guest_pii(): void
    {
        $guest = Guest::factory()->create([
            'first_name' => 'Zebulon', 'last_name' => 'Quixotic', 'name' => 'Zebulon Quixotic', 'phone' => '+963911223344',
        ]);
        $this->seedIssues($guest);

        $body = $this->fetch($this->user('night_audit.manage'), ['date' => self::D])->assertOk()->getContent();

        foreach (['Zebulon', 'Quixotic', '963911223344'] as $needle) {
            $this->assertStringNotContainsString($needle, $body);
        }
    }

    public function test_re_read_budget_through_http(): void
    {
        $manager = $this->user('night_audit.manage');
        $this->fetch($manager, ['date' => self::D])->assertOk();

        $controller = app(\App\Actions\NightAudit\OpenNightAuditAction::class);
        // State lock, audit lookup, checks, blockers, users (D-22 ≤ 7).
        $this->assertSame(5, $this->countDomainQueries(fn () => $controller->handle(null, true, $manager)));
    }

    private function assertNoIdKeys(array $data, string $path = 'data'): void
    {
        foreach ($data as $key => $value) {
            $this->assertNotSame('id', $key, "{$path} exposes an internal id");
            $this->assertFalse(is_string($key) && str_ends_with($key, '_id'), "{$path}.{$key} exposes an internal id");
            if (is_array($value)) {
                $this->assertNoIdKeys($value, "{$path}.{$key}");
            }
        }
    }
}
