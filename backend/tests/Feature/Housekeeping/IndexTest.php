<?php

namespace Tests\Feature\Housekeeping;

use App\Enums\HousekeepingTaskStatus;
use App\Http\Resources\Housekeeping\HousekeepingTaskResource;
use App\Models\Guest;
use App\Models\HousekeepingTask;
use App\Models\HousekeepingTaskStatusHistory;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\User;
use App\Services\Housekeeping\HousekeepingTaskService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * GET /api/housekeeping/tasks and GET /api/housekeeping/tasks/{task}
 * (Phase 6, HK-01, D-06).
 */
class IndexTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        config(['hotel.timezone' => 'Asia/Damascus']);
        $this->travelTo(Carbon::parse('2027-03-12 10:00:00'));
    }

    private function staffToken(string ...$permissions): string
    {
        $user = User::factory()->create();
        $user->givePermissionTo($permissions);

        return $user->createToken('t')->plainTextToken;
    }

    private function presetToken(string $role): string
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user->createToken('t')->plainTextToken;
    }

    private function list(string $query = '', ?string $token = null)
    {
        return $this->withToken($token ?? $this->staffToken('housekeeping.view'))
            ->getJson('/api/housekeeping/tasks' . ($query !== '' ? '?' . $query : ''));
    }

    /** @return list<string> */
    private function uuids(string $query = ''): array
    {
        return collect($this->list($query)->assertOk()->json('data.items'))->pluck('uuid')->all();
    }

    public function test_requires_a_token(): void
    {
        $this->getJson('/api/housekeeping/tasks')->assertStatus(401);
    }

    public function test_presets_without_housekeeping_view_are_forbidden(): void
    {
        foreach (['kitchen', 'concierge'] as $role) {
            $this->list('', $this->presetToken($role))
                ->assertStatus(403)
                ->assertJsonPath('success', false);
        }
    }

    public function test_reception_and_housekeeping_presets_can_list(): void
    {
        HousekeepingTask::factory()->create();

        foreach (['reception', 'housekeeping'] as $role) {
            $this->list('', $this->presetToken($role))->assertOk()->assertJsonCount(1, 'data.items');
        }
    }

    public function test_item_shape(): void
    {
        $guest       = Guest::factory()->create(['phone' => '+963911111111', 'email' => 'occupant@example.test']);
        $reservation = Reservation::factory()->create(['guest_id' => $guest->id]);
        $assignee    = User::factory()->create();
        $task        = HousekeepingTask::factory()->assigned($assignee)->create(['reservation_id' => $reservation->id]);

        $response = $this->list()->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['items' => [[
                'uuid', 'type', 'status', 'priority', 'notes',
                'room' => ['uuid', 'number', 'floor', 'status'],
                'reservation' => ['uuid', 'booking_code', 'check_out'],
                'assigned_user' => ['uuid', 'name'],
                'service_request_uuid', 'due_at', 'started_at', 'completed_at', 'created_at', 'updated_at',
                'allowed_statuses',
            ]], 'meta']]);

        $item = $response->json('data.items.0');
        $this->assertSame($task->uuid, $item['uuid']);
        $this->assertSame($task->room->uuid, $item['room']['uuid']);
        $this->assertSame($reservation->uuid, $item['reservation']['uuid']);
        $this->assertSame($assignee->uuid, $item['assigned_user']['uuid']);
        $this->assertNull($item['service_request_uuid']);
        $this->assertSame(['in_progress', 'cancelled'], $item['allowed_statuses']);
        $this->assertArrayNotHasKey('guest', $item);
        $this->assertArrayNotHasKey('id', $item);

        $json = $response->getContent();
        $this->assertStringNotContainsString($guest->phone, $json);
        $this->assertStringNotContainsString($guest->email, $json);
    }

    public function test_filters_by_status_type_and_priority(): void
    {
        $pending    = HousekeepingTask::factory()->create();
        $assigned   = HousekeepingTask::factory()->assigned()->stayover()->create(['priority' => 'high']);
        $inProgress = HousekeepingTask::factory()->inProgress()->inspection()->create(['priority' => 'low']);

        $this->assertSame([$pending->uuid], $this->uuids('status=pending'));
        $this->assertEqualsCanonicalizing([$pending->uuid, $assigned->uuid], $this->uuids('status[in]=pending,assigned'));
        $this->assertSame([$assigned->uuid], $this->uuids('type=stayover'));
        $this->assertEqualsCanonicalizing([$assigned->uuid, $inProgress->uuid], $this->uuids('type[in]=stayover,inspection'));
        $this->assertSame([$inProgress->uuid], $this->uuids('priority=low'));
    }

    public function test_filters_by_room_number_and_room_uuid(): void
    {
        $room  = Room::factory()->create(['number' => 'H707']);
        $task  = HousekeepingTask::factory()->create(['room_id' => $room->id]);
        HousekeepingTask::factory()->create();

        $this->assertSame([$task->uuid], $this->uuids('room=H707'));
        $this->assertSame([$task->uuid], $this->uuids('room=' . $room->uuid));
    }

    public function test_filters_by_assignee_uuid_and_unassigned(): void
    {
        $user       = User::factory()->create();
        $mine       = HousekeepingTask::factory()->assigned($user)->create();
        $unassigned = HousekeepingTask::factory()->create();
        HousekeepingTask::factory()->assigned()->create();

        $this->assertSame([$mine->uuid], $this->uuids('assignee=' . $user->uuid));
        $this->assertSame([$unassigned->uuid], $this->uuids('assignee=unassigned'));
    }

    public function test_due_at_bounds_are_inclusive(): void
    {
        $at    = HousekeepingTask::factory()->create(['due_at' => '2027-03-12 12:00:00']);
        $later = HousekeepingTask::factory()->create(['due_at' => '2027-03-12 12:00:01']);

        $this->assertSame([$at->uuid], $this->uuids('due_at[lte]=' . urlencode('2027-03-12T12:00:00Z')));
        $this->assertSame([$at->uuid, $later->uuid], $this->uuids('due_at[gte]=' . urlencode('2027-03-12T12:00:00Z')));
        // An offset is honoured: 15:00 in Damascus is 12:00 UTC.
        $this->assertSame([$at->uuid], $this->uuids('due_at[lte]=' . urlencode('2027-03-12T15:00:00+03:00')));
    }

    public function test_due_date_uses_the_hotel_day(): void
    {
        // 2027-03-12 23:59:59 Asia/Damascus = 20:59:59 UTC; 2027-03-13 00:00:00 local = 21:00:00 UTC.
        $lastSecond = HousekeepingTask::factory()->create(['due_at' => '2027-03-12 20:59:59']);
        $nextDay    = HousekeepingTask::factory()->create(['due_at' => '2027-03-12 21:00:00']);
        $firstSecond = HousekeepingTask::factory()->create(['due_at' => '2027-03-11 21:00:00']);

        $this->assertSame([$firstSecond->uuid, $lastSecond->uuid], $this->uuids('due_date=2027-03-12'));
        $this->assertSame([$nextDay->uuid], $this->uuids('due_date=2027-03-13'));
    }

    public function test_bad_due_date_and_bad_assignee_are_422(): void
    {
        $this->list('due_date=2027-13-01')->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_failed')
            ->assertJsonValidationErrors(['due_date']);

        $this->list('assignee=nobody')->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_failed')
            ->assertJsonValidationErrors(['assignee']);

        $this->list('due_at[lte]=not-a-date')->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_failed');
    }

    public function test_blank_filters_are_ignored(): void
    {
        HousekeepingTask::factory()->count(2)->create();

        $this->list('status=&room=&assignee=&due_date=&type[in]=')->assertOk()->assertJsonCount(2, 'data.items');
    }

    public function test_empty_board(): void
    {
        $this->list()->assertOk()
            ->assertJsonPath('data.items', [])
            ->assertJsonPath('data.meta.total', 0);
    }

    public function test_default_order_is_due_at_then_id_with_nulls_last(): void
    {
        $undated = HousekeepingTask::factory()->create(['due_at' => null]);
        $tieA    = HousekeepingTask::factory()->create(['due_at' => '2027-03-12 14:00:00']);
        $tieB    = HousekeepingTask::factory()->create(['due_at' => '2027-03-12 14:00:00']);
        $early   = HousekeepingTask::factory()->create(['due_at' => '2027-03-12 11:00:00']);

        $expected = [$early->uuid, $tieA->uuid, $tieB->uuid, $undated->uuid];
        $this->assertSame($expected, $this->uuids());
        $this->assertSame($expected, $this->uuids());
    }

    public function test_sort_by_priority(): void
    {
        $low    = HousekeepingTask::factory()->create(['priority' => 'low']);
        $high   = HousekeepingTask::factory()->create(['priority' => 'high']);
        $normal = HousekeepingTask::factory()->create(['priority' => 'normal']);
        $high2  = HousekeepingTask::factory()->create(['priority' => 'high']);

        $this->assertSame([$high->uuid, $high2->uuid, $normal->uuid, $low->uuid], $this->uuids('sort=priority&sort_dir=desc'));
        $this->assertSame([$low->uuid, $normal->uuid, $high->uuid, $high2->uuid], $this->uuids('sort=priority'));
    }

    public function test_show_returns_history_newest_first_capped_at_ten(): void
    {
        $task  = HousekeepingTask::factory()->create();
        $actor = User::factory()->create();

        for ($i = 1; $i <= 12; $i++) {
            $this->travel(1)->minutes();
            HousekeepingTaskStatusHistory::create([
                'housekeeping_task_id' => $task->id,
                'from_status'          => 'pending',
                'to_status'            => 'pending',
                'changed_by'           => $i === 12 ? $actor->id : null,
                'reason'               => "row {$i}",
            ]);
        }

        $response = $this->withToken($this->staffToken('housekeeping.view'))
            ->getJson("/api/housekeeping/tasks/{$task->uuid}")
            ->assertOk()
            ->assertJsonPath('data.uuid', $task->uuid)
            ->assertJsonCount(10, 'data.history');

        $this->assertSame('row 12', $response->json('data.history.0.reason'));
        $this->assertSame('row 3', $response->json('data.history.9.reason'));
        $this->assertSame($actor->uuid, $response->json('data.history.0.changed_by.uuid'));
        $this->assertNull($response->json('data.history.1.changed_by'));
    }

    public function test_show_unknown_uuid_is_404(): void
    {
        $this->withToken($this->staffToken('housekeeping.view'))
            ->getJson('/api/housekeeping/tasks/' . fake()->uuid())
            ->assertStatus(404)
            ->assertJsonPath('success', false);
    }

    public function test_show_requires_a_token_and_housekeeping_view(): void
    {
        $task = HousekeepingTask::factory()->create();
        $url  = '/api/housekeeping/tasks/' . $task->uuid;

        $this->getJson($url)->assertStatus(401);

        foreach (['kitchen', 'concierge'] as $role) {
            $this->withToken($this->presetToken($role))->getJson($url)
                ->assertStatus(403)
                ->assertJsonPath('success', false);
            $this->app['auth']->forgetGuards();
        }

        $this->withToken($this->presetToken('reception'))->getJson($url)
            ->assertOk()
            ->assertJsonPath('data.uuid', $task->uuid);
    }

    public function test_board_page_query_count_does_not_grow_with_rows(): void
    {
        $guest = Guest::factory()->create();

        foreach (range(1, 6) as $i) {
            HousekeepingTask::factory()->create([
                'reservation_id'   => Reservation::factory()->create(['guest_id' => $guest->id])->id,
                'assigned_user_id' => User::factory()->create()->id,
            ]);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();

        $page = app(HousekeepingTaskService::class)->index()['data'];
        $rows = HousekeepingTaskResource::collection($page)->resolve(request());

        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        // Every relation the board shows is present, so the budget covers them.
        $this->assertCount(6, $rows);
        foreach ($rows as $row) {
            $this->assertNotNull($row['room']['uuid'] ?? null);
            $this->assertNotNull($row['reservation']['uuid'] ?? null);
            $this->assertNotNull($row['assigned_user']['uuid'] ?? null);
            $this->assertArrayHasKey('service_request_uuid', $row);
        }

        // count + page + four eager loads (room, reservation, assignedUser, serviceRequest).
        $this->assertLessThanOrEqual(6, $count);
    }

    public function test_list_hides_history(): void
    {
        HousekeepingTask::factory()->create();

        $this->assertArrayNotHasKey('history', $this->list()->json('data.items.0'));
        $this->assertSame(HousekeepingTaskStatus::PENDING->value, $this->list()->json('data.items.0.status'));
    }
}
