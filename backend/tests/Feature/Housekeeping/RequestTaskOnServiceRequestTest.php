<?php

namespace Tests\Feature\Housekeeping;

use App\Contracts\FirebaseServiceInterface;
use App\Enums\Department;
use App\Enums\HousekeepingTaskStatus;
use App\Enums\ServiceRequestStatus;
use App\Models\User;
use Tests\Support\FakeFirebaseService;
use App\Enums\HousekeepingTaskType;
use App\Enums\ServiceRequestPriority;
use App\Events\ServiceRequestPlaced;
use App\Models\Guest;
use App\Models\HousekeepingTask;
use App\Models\HousekeepingTaskStatusHistory;
use App\Models\Reservation;
use App\Models\ReservationRoom;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\ServiceCategory;
use App\Models\ServiceItem;
use App\Models\ServiceRequest;
use Database\Seeders\GuestServiceCatalogSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use App\Actions\Housekeeping\UpdateHousekeepingTaskStatusAction;
use RuntimeException;
use Tests\TestCase;

/**
 * D-10: a service request routed to Department::HOUSEKEEPING creates one
 * request-type task on the reservation's first assigned room.
 */
class RequestTaskOnServiceRequestTest extends TestCase
{
    use RefreshDatabase;

    private RoomType $type;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(GuestServiceCatalogSeeder::class);
        config(['hotel.timezone' => 'Asia/Damascus']);
        $this->travelTo(Carbon::parse('2027-03-12 10:00:00'));
        $this->type = RoomType::factory()->create();
    }

    /**
     * A checked-in guest; one reservation line per entry of $lines (true = a
     * line with a fresh room, false = a roomless line).
     *
     * @return array{0: Guest, 1: Reservation, 2: list<Room|null>, 3: string}
     */
    private function checkedInGuest(array $lines = [true]): array
    {
        $guest       = Guest::factory()->create();
        $reservation = Reservation::factory()->checkedIn()->create([
            'guest_id'      => $guest->id,
            'check_in'      => '2027-03-11',
            'check_out'     => '2027-03-14',
            'checked_in_at' => '2027-03-11 12:00:00',
        ]);

        $rooms = [];
        foreach ($lines as $withRoom) {
            $room = $withRoom ? Room::factory()->create(['room_type_id' => $this->type->id, 'status' => 'available']) : null;
            ReservationRoom::factory()->create([
                'reservation_id' => $reservation->id,
                'room_type_id'   => $this->type->id,
                'room_id'        => $room?->id,
            ]);
            $rooms[] = $room;
        }

        return [$guest, $reservation, $rooms, $guest->createToken('guest')->plainTextToken];
    }

    private function place(string $token, array $body)
    {
        return $this->withToken($token)
            ->withHeaders(['Accept-Language' => 'en'])
            ->postJson('/api/service-requests', $body);
    }

    private function firstItemOf(string $categoryCode): ServiceItem
    {
        $category = ServiceCategory::where('code', $categoryCode)->firstOrFail();

        return ServiceItem::where('service_category_id', $category->id)->where('is_active', true)->orderBy('id')->firstOrFail();
    }

    public function test_housekeeping_item_creates_a_request_task(): void
    {
        [, $reservation, $rooms, $token] = $this->checkedInGuest();
        $item = $this->firstItemOf('housekeeping');

        $uuid = $this->place($token, [
            'service_item_uuid' => $item->uuid,
            'notes'             => 'extra towels',
            'priority'          => 'high',
        ])->assertCreated()->json('data.uuid');

        $request = ServiceRequest::where('uuid', $uuid)->firstOrFail();
        $this->assertSame(1, HousekeepingTask::count());

        $task = HousekeepingTask::first();
        $this->assertSame(HousekeepingTaskType::REQUEST, $task->type);
        $this->assertSame(HousekeepingTaskStatus::PENDING, $task->status);
        $this->assertSame($request->id, $task->service_request_id);
        $this->assertSame($rooms[0]->id, $task->room_id);
        $this->assertSame($reservation->id, $task->reservation_id);
        $this->assertSame(ServiceRequestPriority::HIGH, $task->priority);
        $this->assertTrue($task->due_at->equalTo($request->created_at->copy()->addMinutes($item->expected_minutes)));
        $this->assertSame('extra towels', $task->notes);
        $this->assertNull($task->created_by);
        $this->assertNull($task->getRawOriginal('dedupe_key'));
        $this->assertSame('service_request', HousekeepingTaskStatusHistory::where('housekeeping_task_id', $task->id)->value('reason'));
    }

    public function test_legacy_laundry_request_is_due_in_sixty_minutes(): void
    {
        [, , , $token] = $this->checkedInGuest();

        $uuid = $this->place($token, ['type' => 'laundry'])->assertCreated()->json('data.uuid');

        $request = ServiceRequest::where('uuid', $uuid)->firstOrFail();
        $task    = HousekeepingTask::firstOrFail();
        $this->assertSame($request->id, $task->service_request_id);
        $this->assertTrue($task->due_at->equalTo($request->created_at->copy()->addMinutes(60)));
    }

    public function test_non_housekeeping_request_creates_no_task(): void
    {
        [, , , $token] = $this->checkedInGuest();

        $this->place($token, ['service_item_uuid' => $this->firstItemOf('room_service')->uuid])->assertCreated();

        $this->assertSame(0, HousekeepingTask::count());
    }

    public function test_request_without_an_assigned_room_is_skipped_and_logged(): void
    {
        [, , , $token] = $this->checkedInGuest([false]);

        $uuid    = $this->place($token, ['service_item_uuid' => $this->firstItemOf('housekeeping')->uuid])->assertCreated()->json('data.uuid');
        $request = ServiceRequest::where('uuid', $uuid)->firstOrFail();

        $this->assertSame(0, HousekeepingTask::count());

        $row = DB::table('activity_log')->where('description', 'housekeeping_task_skipped')->first();
        $this->assertNotNull($row);
        $this->assertSame($request->getMorphClass(), $row->subject_type);
        $this->assertEquals($request->id, $row->subject_id);
        $this->assertSame('no_room', json_decode($row->properties, true)['reason']);
    }

    public function test_redispatch_keeps_one_request_task(): void
    {
        [, , , $token] = $this->checkedInGuest();

        $uuid    = $this->place($token, ['type' => 'housekeeping'])->assertCreated()->json('data.uuid');
        $request = ServiceRequest::where('uuid', $uuid)->firstOrFail();

        event(new ServiceRequestPlaced($request));
        event(new ServiceRequestPlaced($request->fresh()));

        $this->assertSame(1, HousekeepingTask::count());
        $this->assertSame(1, HousekeepingTaskStatusHistory::count());
    }

    // ── D-11: closing the request closes its task ──────────────────────────

    private function fakeFirebase(): FakeFirebaseService
    {
        $fake = new FakeFirebaseService();
        $this->app->instance(FirebaseServiceInterface::class, $fake);
        return $fake;
    }

    /** @return array{0: ServiceRequest, 1: HousekeepingTask} */
    private function requestWithTask(string $taskStatus = 'pending'): array
    {
        $room    = Room::factory()->create(['room_type_id' => $this->type->id, 'status' => 'dirty']);
        $request = ServiceRequest::factory()->create([
            'type'       => 'housekeeping',
            'department' => Department::HOUSEKEEPING,
            'status'     => ServiceRequestStatus::NEW,
        ]);
        $task = HousekeepingTask::factory()->request($request)->create(['room_id' => $room->id, 'status' => $taskStatus]);

        return [$request, $task];
    }

    private function closeRequest(ServiceRequest $request, string $status, User $staff)
    {
        return $this->withToken($staff->createToken('t')->plainTextToken)
            ->patchJson("/api/operations/queue/service-requests/{$request->uuid}/status", ['status' => $status]);
    }

    private function staffWith(string ...$permissions): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo($permissions);
        return $user;
    }

    public function test_completing_the_request_cancels_its_open_task(): void
    {
        $this->fakeFirebase();
        [$request, $task] = $this->requestWithTask();
        $staff = $this->staffWith('service_requests.update');

        $this->closeRequest($request, 'completed', $staff)->assertOk()->assertJsonPath('data.status', 'completed');

        $this->assertSame(HousekeepingTaskStatus::CANCELLED, $task->fresh()->status);
        $row = HousekeepingTaskStatusHistory::where('housekeeping_task_id', $task->id)->latest('id')->first();
        $this->assertSame('cancelled', $row->to_status);
        $this->assertSame('service_request_closed', $row->reason);
        $this->assertEquals($staff->id, $row->changed_by);
        $this->assertSame('dirty', $task->room->fresh()->status->value);
    }

    public function test_cancelling_the_request_cancels_its_open_task(): void
    {
        $this->fakeFirebase();
        [$request, $task] = $this->requestWithTask('in_progress');

        $this->closeRequest($request, 'cancelled', $this->staffWith('service_requests.update'))->assertOk();

        $this->assertSame(HousekeepingTaskStatus::CANCELLED, $task->fresh()->status);
        $this->assertSame('service_request_closed', HousekeepingTaskStatusHistory::where('housekeeping_task_id', $task->id)->latest('id')->value('reason'));
    }

    /**
     * QA minor 1: the request write and the D-11 task cancel share one
     * transaction, so a failing cancel leaves no half state (request closed,
     * task open) and nothing is mirrored.
     */
    public function test_a_failing_task_cancel_rolls_back_the_request_close(): void
    {
        $fake = $this->fakeFirebase();
        [$request, $task] = $this->requestWithTask();
        $this->mock(UpdateHousekeepingTaskStatusAction::class, fn ($mock) => $mock
            ->shouldReceive('handle')->once()->andThrow(new RuntimeException('cancel failed')));

        $this->closeRequest($request, 'completed', $this->staffWith('service_requests.update'))->assertStatus(500);

        $this->assertSame(ServiceRequestStatus::NEW, $request->fresh()->status);
        $this->assertSame(HousekeepingTaskStatus::PENDING, $task->fresh()->status);
        $this->assertSame([], $fake->mirrors);
    }

    public function test_in_progress_request_keeps_its_task_open(): void
    {
        $this->fakeFirebase();
        [$request, $task] = $this->requestWithTask();

        $this->closeRequest($request, 'in_progress', $this->staffWith('service_requests.update'))->assertOk();

        $this->assertSame(HousekeepingTaskStatus::PENDING, $task->fresh()->status);
        $this->assertSame(0, HousekeepingTaskStatusHistory::where('housekeeping_task_id', $task->id)->count());
    }

    public function test_a_done_request_task_is_not_touched_by_a_later_close(): void
    {
        $this->fakeFirebase();
        [$request, $task] = $this->requestWithTask('done');

        $this->closeRequest($request, 'completed', $this->staffWith('service_requests.update'))->assertOk();

        $this->assertSame(HousekeepingTaskStatus::DONE, $task->fresh()->status);
        $this->assertSame(0, HousekeepingTaskStatusHistory::where('housekeeping_task_id', $task->id)->count());
    }

    public function test_first_assigned_line_supplies_the_room(): void
    {
        [, , $rooms, $token] = $this->checkedInGuest([false, true, true]);

        $this->place($token, ['service_item_uuid' => $this->firstItemOf('housekeeping')->uuid])->assertCreated();

        $this->assertSame($rooms[1]->id, HousekeepingTask::firstOrFail()->room_id);
    }
}
