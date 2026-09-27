<?php

namespace Tests\Feature\Operations;

use App\Contracts\FirebaseServiceInterface;
use App\Enums\CheckOutMode;
use App\Enums\HousekeepingTaskStatus;
use App\Events\ReservationCheckedOut;
use App\Models\Guest;
use App\Models\HousekeepingTask;
use App\Models\HousekeepingTaskStatusHistory;
use App\Models\Reservation;
use App\Models\ReservationRoom;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\ServiceRequest;
use App\Models\Ticket;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Support\FakeFirebaseService;
use Tests\TestCase;

/**
 * Housekeeping tasks as the third operations-queue type (Phase 6, HK-05;
 * D-11b, D-12, D-13, D-14).
 */
class OperationsQueueHousekeepingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        config(['hotel.timezone' => 'Asia/Damascus']);
        $this->travelTo(Carbon::parse('2027-03-12 10:00:00'));
    }

    private function fakeFirebase(): FakeFirebaseService
    {
        $fake = new FakeFirebaseService();
        $this->app->instance(FirebaseServiceInterface::class, $fake);

        return $fake;
    }

    private function staffToken(string ...$permissions): string
    {
        $user = User::factory()->create();
        $user->givePermissionTo($permissions);

        return $user->createToken('t')->plainTextToken;
    }

    private function queue(string $token)
    {
        return $this->withToken($token)->getJson('/api/operations/queue');
    }

    /** A reservation whose lines hold the given rooms, in order (null = no room). */
    private function reservationWithRooms(array $rooms): Reservation
    {
        $reservation = Reservation::factory()->create();
        $type        = RoomType::factory()->create();

        foreach ($rooms as $room) {
            ReservationRoom::factory()->create([
                'reservation_id' => $reservation->id,
                'room_type_id'   => $type->id,
                'room_id'        => $room?->id,
            ]);
        }

        return $reservation;
    }

    private function mirrorsOf(FakeFirebaseService $fake, string $document): array
    {
        return array_values(array_filter($fake->mirrors, fn ($m) => $m['document'] === $document));
    }

    public function test_requires_a_token(): void
    {
        $this->getJson('/api/operations/queue')->assertStatus(401);
    }

    public function test_housekeeping_view_alone_opens_the_queue(): void
    {
        HousekeepingTask::factory()->create();

        $this->queue($this->staffToken('housekeeping.view'))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.type', 'housekeeping_task');
    }

    public function test_no_view_permission_is_forbidden(): void
    {
        $this->queue($this->staffToken('housekeeping.assign', 'housekeeping.update'))
            ->assertStatus(403)
            ->assertJsonPath('success', false);
    }

    public function test_open_tasks_are_listed_with_the_task_shape(): void
    {
        $room = Room::factory()->create(['number' => '312']);
        $pending    = HousekeepingTask::factory()->create(['room_id' => $room->id]);
        $assigned   = HousekeepingTask::factory()->stayover()->assigned()->create(['room_id' => $room->id]);
        $inProgress = HousekeepingTask::factory()->inspection()->inProgress()->create(['room_id' => $room->id]);
        HousekeepingTask::factory()->done()->create(['room_id' => $room->id]);
        HousekeepingTask::factory()->cancelled()->create(['room_id' => $room->id]);

        $items = collect($this->queue($this->staffToken('housekeeping.view'))->assertOk()->json('data.items'))->keyBy('uuid');

        $this->assertSame(
            collect([$pending->uuid, $assigned->uuid, $inProgress->uuid])->sort()->values()->all(),
            $items->keys()->sort()->values()->all(),
        );

        $row = $items[$pending->uuid];
        $this->assertSame('housekeeping_task', $row['type']);
        $this->assertSame('turnover', $row['subject']);
        $this->assertSame('housekeeping', $row['department']);
        $this->assertSame('pending', $row['status']);
        $this->assertSame('normal', $row['priority']);
        $this->assertNull($row['assigned_user_uuid']);
        $this->assertSame('312', $row['room_number']);
        $this->assertSame(['assigned', 'in_progress', 'cancelled'], $row['allowed_statuses']);

        $this->assertSame('stayover', $items[$assigned->uuid]['subject']);
        $this->assertSame($assigned->assignedUser->uuid, $items[$assigned->uuid]['assigned_user_uuid']);
        $this->assertSame(['in_progress', 'cancelled'], $items[$assigned->uuid]['allowed_statuses']);
        $this->assertSame(['done', 'cancelled'], $items[$inProgress->uuid]['allowed_statuses']);
    }

    public function test_tasks_are_hidden_without_housekeeping_view(): void
    {
        HousekeepingTask::factory()->create();
        ServiceRequest::factory()->create();

        $this->queue($this->staffToken('service_requests.view'))
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.type', 'service_request');
    }

    public function test_every_row_carries_room_number_and_allowed_statuses(): void
    {
        $reservation = $this->reservationWithRooms([null, Room::factory()->create(['number' => '204'])]);
        $request     = ServiceRequest::factory()->create(['reservation_id' => $reservation->id]);
        $ticket      = Ticket::factory()->create();

        $items = collect($this->queue($this->staffToken('service_requests.view', 'tickets.view'))
            ->assertOk()->json('data.items'))->keyBy('uuid');

        foreach ($items as $item) {
            $this->assertArrayHasKey('room_number', $item);
            $this->assertArrayHasKey('allowed_statuses', $item);
        }

        $this->assertSame('204', $items[$request->uuid]['room_number']);
        $this->assertSame(['in_progress', 'completed', 'cancelled'], $items[$request->uuid]['allowed_statuses']);
        $this->assertNull($items[$ticket->uuid]['room_number']);
        $this->assertSame(['assigned', 'resolved', 'closed'], $items[$ticket->uuid]['allowed_statuses']);
    }

    public function test_request_task_and_its_request_are_two_rows(): void
    {
        $room        = Room::factory()->create(['number' => '118']);
        $reservation = $this->reservationWithRooms([$room]);
        $request     = ServiceRequest::factory()->create(['reservation_id' => $reservation->id, 'department' => 'housekeeping']);
        $task        = HousekeepingTask::factory()->request($request)->create(['room_id' => $room->id]);

        $items = collect($this->queue($this->staffToken('service_requests.view', 'housekeeping.view'))
            ->assertOk()->json('data.items'))->keyBy('uuid');

        $this->assertCount(2, $items);
        $this->assertSame('service_request', $items[$request->uuid]['type']);
        $this->assertSame('housekeeping_task', $items[$task->uuid]['type']);
        $this->assertSame('118', $items[$request->uuid]['room_number']);
        $this->assertSame('118', $items[$task->uuid]['room_number']);
    }

    public function test_empty_queue_for_housekeeping_only_holder(): void
    {
        ServiceRequest::factory()->create();
        HousekeepingTask::factory()->done()->create();

        $this->queue($this->staffToken('housekeeping.view'))
            ->assertOk()
            ->assertJsonPath('data.items', []);
    }

    public function test_summary_carries_housekeeping_tasks_only_for_view_holders(): void
    {
        $viewer = $this->staffToken('housekeeping.view');

        $this->withToken($viewer)->getJson('/api/dashboard/summary')
            ->assertOk()
            ->assertJsonPath('data.housekeeping_tasks', []);

        HousekeepingTask::factory()->count(2)->create();
        HousekeepingTask::factory()->done()->create();

        $this->withToken($viewer)->getJson('/api/dashboard/summary')
            ->assertOk()
            ->assertJsonPath('data.housekeeping_tasks.pending', 2)
            ->assertJsonPath('data.housekeeping_tasks.done', 1);

        $this->withToken($this->staffToken('service_requests.view'))->getJson('/api/dashboard/summary')
            ->assertOk()
            ->assertJsonMissingPath('data.housekeeping_tasks');
    }

    public function test_equal_created_at_keeps_registry_order(): void
    {
        $at      = now()->subHour();
        $taskA   = HousekeepingTask::factory()->create(['created_at' => $at]);
        $taskB   = HousekeepingTask::factory()->create(['created_at' => $at]);
        $ticket  = Ticket::factory()->create(['created_at' => $at]);
        $request = ServiceRequest::factory()->create(['created_at' => $at]);

        $token    = $this->staffToken('service_requests.view', 'tickets.view', 'housekeeping.view');
        $expected = [$request->uuid, $ticket->uuid, $taskB->uuid, $taskA->uuid];

        $this->assertSame($expected, collect($this->queue($token)->assertOk()->json('data.items'))->pluck('uuid')->all());
        $this->assertSame($expected, collect($this->queue($token)->assertOk()->json('data.items'))->pluck('uuid')->all());
    }

    public function test_assign_via_queue_needs_housekeeping_assign(): void
    {
        $this->fakeFirebase();
        $task     = HousekeepingTask::factory()->create();
        $assignee = User::factory()->create();
        $url      = "/api/operations/queue/housekeeping-tasks/{$task->uuid}/assign";

        $this->withToken($this->staffToken('service_requests.assign'))
            ->patchJson($url, ['user_uuid' => $assignee->uuid])
            ->assertStatus(403);

        $this->withToken($this->staffToken('housekeeping.assign'))
            ->patchJson($url, ['user_uuid' => $assignee->uuid])
            ->assertOk()
            ->assertJsonPath('data.type', 'housekeeping_task')
            ->assertJsonPath('data.status', 'assigned')
            ->assertJsonPath('data.assigned_user_uuid', $assignee->uuid)
            ->assertJsonPath('data.room_number', $task->room->number);

        $history = HousekeepingTaskStatusHistory::where('housekeeping_task_id', $task->id)->get();
        $this->assertCount(1, $history);
        $this->assertSame('pending', $history[0]->from_status);
        $this->assertSame('assigned', $history[0]->to_status);
    }

    public function test_assign_via_queue_validates_the_body(): void
    {
        $task = HousekeepingTask::factory()->create();

        $this->withToken($this->staffToken('housekeeping.assign'))
            ->patchJson("/api/operations/queue/housekeeping-tasks/{$task->uuid}/assign", [])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_failed');
    }

    public function test_unknown_task_uuid_is_404(): void
    {
        $this->withToken($this->staffToken('housekeeping.update'))
            ->patchJson('/api/operations/queue/housekeeping-tasks/00000000-0000-0000-0000-000000000000/status', ['status' => 'in_progress'])
            ->assertStatus(404);
    }

    public function test_status_via_queue(): void
    {
        $this->fakeFirebase();
        $room  = Room::factory()->create(['status' => 'dirty']);
        $task  = HousekeepingTask::factory()->create(['room_id' => $room->id]);
        $url   = "/api/operations/queue/housekeeping-tasks/{$task->uuid}/status";
        $token = $this->staffToken('housekeeping.update');

        $this->withToken($this->staffToken('service_requests.update'))
            ->patchJson($url, ['status' => 'in_progress'])
            ->assertStatus(403);

        $this->withToken($token)->patchJson($url, ['status' => 'completed'])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_failed')
            ->assertJsonValidationErrors('status');

        $this->withToken($token)->patchJson($url, ['status' => 'done'])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'housekeeping_task_transition_invalid');

        $this->withToken($token)->patchJson($url, ['status' => 'in_progress', 'reason' => 'on it'])
            ->assertOk()
            ->assertJsonPath('data.status', 'in_progress')
            ->assertJsonPath('data.allowed_statuses', ['done', 'cancelled']);

        $this->assertSame('on it', HousekeepingTaskStatusHistory::where('housekeeping_task_id', $task->id)
            ->where('to_status', 'in_progress')->value('reason'));

        $this->withToken($token)->patchJson($url, ['status' => 'done'])->assertOk()->assertJsonPath('data.status', 'done');

        $this->assertSame(HousekeepingTaskStatus::DONE, $task->fresh()->status);
        $this->assertSame('available', $room->fresh()->status->value);
    }

    public function test_task_changes_are_mirrored_once_per_write(): void
    {
        $fake = $this->fakeFirebase();
        $room = Room::factory()->create(['number' => '507']);

        $uuid = $this->withToken($this->staffToken('housekeeping.assign'))
            ->postJson('/api/housekeeping/tasks', ['room_uuid' => $room->uuid, 'type' => 'turnover'])
            ->assertCreated()->json('data.uuid');

        $doc     = "housekeeping_task_{$uuid}";
        $mirrors = $this->mirrorsOf($fake, $doc);
        $this->assertCount(1, $mirrors);
        $this->assertSame('ops_queue', $mirrors[0]['collection']);
        $this->assertSame('pending', $mirrors[0]['data']['status']);
        $this->assertSame('housekeeping', $mirrors[0]['data']['department']);
        $this->assertSame('turnover', $mirrors[0]['data']['task_type']);
        $this->assertSame($room->uuid, $mirrors[0]['data']['room_uuid']);
        $this->assertSame('507', $mirrors[0]['data']['room_number']);
        $this->assertNull($mirrors[0]['data']['guest_uuid']);
        $this->assertSame(
            ['uuid', 'department', 'status', 'priority', 'guest_uuid', 'assigned_user_uuid', 'created_at', 'task_type', 'room_uuid', 'room_number'],
            array_keys($mirrors[0]['data']),
        );

        $assignee = User::factory()->create();
        $this->withToken($this->staffToken('housekeeping.assign'))
            ->patchJson("/api/operations/queue/housekeeping-tasks/{$uuid}/assign", ['user_uuid' => $assignee->uuid])
            ->assertOk();

        $mirrors = $this->mirrorsOf($fake, $doc);
        $this->assertCount(2, $mirrors);
        $this->assertSame('assigned', $mirrors[1]['data']['status']);
        $this->assertSame($assignee->uuid, $mirrors[1]['data']['assigned_user_uuid']);

        $this->withToken($this->staffToken('housekeeping.update'))
            ->patchJson("/api/operations/queue/housekeeping-tasks/{$uuid}/status", ['status' => 'in_progress'])
            ->assertOk();

        $mirrors = $this->mirrorsOf($fake, $doc);
        $this->assertCount(3, $mirrors);
        $this->assertSame('in_progress', $mirrors[2]['data']['status']);
    }

    public function test_check_out_task_mirror_carries_the_guest(): void
    {
        $fake        = $this->fakeFirebase();
        $guest       = Guest::factory()->create();
        $reservation = $this->reservationWithRooms([Room::factory()->create()]);
        $reservation->update(['guest_id' => $guest->id]);

        ReservationCheckedOut::dispatch($reservation->fresh(), CheckOutMode::NONE, null);

        $task    = HousekeepingTask::firstOrFail();
        $mirrors = $this->mirrorsOf($fake, "housekeeping_task_{$task->uuid}");

        $this->assertCount(1, $mirrors);
        $this->assertSame($guest->uuid, $mirrors[0]['data']['guest_uuid']);
    }

    public function test_firestore_outage_does_not_fail_a_task_write(): void
    {
        $fake                = $this->fakeFirebase();
        $fake->throwOnMirror = true;
        $task                = HousekeepingTask::factory()->create();

        $this->withToken($this->staffToken('housekeeping.update'))
            ->patchJson("/api/operations/queue/housekeeping-tasks/{$task->uuid}/status", ['status' => 'in_progress'])
            ->assertOk();

        $this->assertSame(HousekeepingTaskStatus::IN_PROGRESS, $task->fresh()->status);
    }
}
