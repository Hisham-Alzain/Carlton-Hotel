<?php

namespace Tests\Unit\Housekeeping;

use App\Actions\Housekeeping\CreateHousekeepingTaskAction;
use App\Enums\HousekeepingTaskStatus;
use App\Enums\HousekeepingTaskType;
use App\Enums\ServiceRequestPriority;
use App\Events\HousekeepingTaskChanged;
use App\Models\HousekeepingTask;
use App\Models\HousekeepingTaskStatusHistory;
use App\Models\Room;
use App\Models\ServiceRequest;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Tests\Concerns\RecordsRowLocks;
use Tests\TestCase;

/**
 * D-01 / D-02 / D-03 / D-05: the single task creator, the model-derived
 * dedupe key, the service-request FK dedupe (consultant override) and the
 * room-before-task lock order.
 */
class CreateHousekeepingTaskActionTest extends TestCase
{
    use RecordsRowLocks, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2027-03-12 10:00:00'));
        Event::fake([HousekeepingTaskChanged::class]);
    }

    private function action(): CreateHousekeepingTaskAction
    {
        return app(CreateHousekeepingTaskAction::class);
    }

    private function turnover(Room $room, array $attrs = []): array
    {
        return $this->action()->ensureOpen($room, HousekeepingTaskType::TURNOVER, $attrs + ['reason' => 'check_out'], null);
    }

    /** Insert a raw task row around the model (no saving hook). */
    private function rawTask(Room $room, string $status, ?string $key): int
    {
        return DB::table('housekeeping_tasks')->insertGetId([
            'uuid'       => (string) Str::uuid(),
            'room_id'    => $room->id,
            'type'       => 'turnover',
            'status'     => $status,
            'priority'   => 'normal',
            'dedupe_key' => $key,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_first_call_creates_a_pending_task_with_one_history_row(): void
    {
        $room = Room::factory()->create(['status' => 'dirty']);

        $result = $this->action()->ensureOpen($room, HousekeepingTaskType::TURNOVER, [
            'reservation_id' => null,
            'priority'       => ServiceRequestPriority::HIGH,
            'due_at'         => now()->addHours(2),
            'notes'          => 'n',
            'reason'         => 'check_out',
        ], null);

        $this->assertSame(['data', 'code'], array_keys($result));
        $this->assertSame(201, $result['code']);

        $task = $result['data'];
        $this->assertInstanceOf(HousekeepingTask::class, $task);
        $this->assertSame(HousekeepingTaskStatus::PENDING, $task->status);
        $this->assertSame(ServiceRequestPriority::HIGH, $task->priority);
        $this->assertNull($task->created_by);
        $this->assertSame('n', $task->notes);

        $history = HousekeepingTaskStatusHistory::where('housekeeping_task_id', $task->id)->get();
        $this->assertCount(1, $history);
        $this->assertNull($history[0]->from_status);
        $this->assertSame('pending', $history[0]->to_status);
        $this->assertSame('check_out', $history[0]->reason);
        $this->assertNull($history[0]->changed_by);

        Event::assertDispatchedTimes(HousekeepingTaskChanged::class, 1);
    }

    public function test_second_call_returns_the_open_task(): void
    {
        $room = Room::factory()->create(['status' => 'dirty']);

        $first  = $this->turnover($room);
        $second = $this->turnover($room);

        $this->assertSame(201, $first['code']);
        $this->assertSame(200, $second['code']);
        $this->assertSame($first['data']->uuid, $second['data']->uuid);
        $this->assertSame(1, HousekeepingTask::count());
        $this->assertSame(1, HousekeepingTaskStatusHistory::count());
        Event::assertDispatchedTimes(HousekeepingTaskChanged::class, 1);
    }

    public function test_a_new_turnover_is_created_after_the_open_one_closes(): void
    {
        $room  = Room::factory()->create(['status' => 'dirty']);
        $first = $this->turnover($room)['data'];

        $first->status = HousekeepingTaskStatus::DONE;
        $first->save();

        $again = $this->turnover($room);

        $this->assertSame(201, $again['code']);
        $this->assertNotSame($first->uuid, $again['data']->uuid);
        $this->assertSame(2, HousekeepingTask::where('room_id', $room->id)->count());
    }

    public function test_types_are_deduped_independently(): void
    {
        $room = Room::factory()->create();

        $this->assertSame(201, $this->turnover($room)['code']);
        $this->assertSame(201, $this->action()->ensureOpen($room, HousekeepingTaskType::STAYOVER, [], null)['code']);
        $this->assertSame(201, $this->action()->ensureOpen($room, HousekeepingTaskType::INSPECTION, [], null)['code']);
    }

    public function test_lost_race_returns_the_competing_row(): void
    {
        $room    = Room::factory()->create(['status' => 'dirty']);
        $fired   = false;
        $competitorUuid = (string) Str::uuid();

        HousekeepingTask::creating(function () use (&$fired, $room, $competitorUuid) {
            if ($fired) {
                return;
            }
            $fired = true;

            DB::table('housekeeping_tasks')->insert([
                'uuid'       => $competitorUuid,
                'room_id'    => $room->id,
                'type'       => 'turnover',
                'status'     => 'pending',
                'priority'   => 'normal',
                'dedupe_key' => "{$room->id}:turnover",
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        $result = $this->turnover($room);

        $this->assertTrue($fired);
        $this->assertSame(200, $result['code']);
        $this->assertSame($competitorUuid, $result['data']->uuid);
        $this->assertSame(1, HousekeepingTask::count());
        $this->assertSame(0, HousekeepingTaskStatusHistory::count());
    }

    public function test_stale_key_is_rethrown(): void
    {
        $room = Room::factory()->create(['status' => 'dirty']);
        $this->rawTask($room, 'done', "{$room->id}:turnover");

        try {
            $this->turnover($room);
            $this->fail('Expected the stale dedupe key to be rethrown.');
        } catch (UniqueConstraintViolationException) {
            // expected
        }

        $this->assertSame(0, HousekeepingTaskStatusHistory::count());
        $this->assertSame(1, HousekeepingTask::count());
    }

    public function test_request_tasks_dedupe_on_their_service_request(): void
    {
        $room = Room::factory()->create();
        $sr1  = ServiceRequest::factory()->create();
        $sr2  = ServiceRequest::factory()->create();

        $a = $this->action()->ensureOpen($room, HousekeepingTaskType::REQUEST, ['service_request' => $sr1, 'reason' => 'service_request'], null);
        $b = $this->action()->ensureOpen($room, HousekeepingTaskType::REQUEST, ['service_request' => $sr2, 'reason' => 'service_request'], null);

        $this->assertSame(201, $a['code']);
        $this->assertSame(201, $b['code']);

        $a['data']->status = HousekeepingTaskStatus::DONE;
        $a['data']->save();

        $again = $this->action()->ensureOpen($room, HousekeepingTaskType::REQUEST, ['service_request' => $sr1], null);

        $this->assertSame(200, $again['code']);
        $this->assertSame($a['data']->uuid, $again['data']->uuid);
        $this->assertSame(2, HousekeepingTask::count());
        $this->assertSame($sr1->id, (int) DB::table('housekeeping_tasks')->where('uuid', $a['data']->uuid)->value('service_request_id'));
        $this->assertTrue($sr1->housekeepingTask()->exists());

        // Consultant override: no morph alias; the global morph map is unchanged.
        $this->assertSame(ServiceRequest::class, (new ServiceRequest)->getMorphClass());
    }

    public function test_request_type_requires_a_service_request_and_others_refuse_one(): void
    {
        $room = Room::factory()->create();

        try {
            $this->action()->ensureOpen($room, HousekeepingTaskType::REQUEST, [], null);
            $this->fail('REQUEST without a service request must throw.');
        } catch (InvalidArgumentException) {
        }

        $this->expectException(InvalidArgumentException::class);
        $this->action()->ensureOpen($room, HousekeepingTaskType::TURNOVER, ['service_request' => ServiceRequest::factory()->create()], null);
    }

    public function test_model_derives_the_dedupe_key(): void
    {
        $task = HousekeepingTask::factory()->create(['dedupe_key' => 'bogus']);
        $key  = "{$task->room_id}:turnover";

        $this->assertSame($key, $task->fresh()->getRawOriginal('dedupe_key'));
        $this->assertSame($key, $task->derivedDedupeKey());

        $task->status = HousekeepingTaskStatus::ASSIGNED;
        $task->save();
        $this->assertSame($key, $task->fresh()->getRawOriginal('dedupe_key'));

        $task->status = HousekeepingTaskStatus::CANCELLED;
        $this->assertNull($task->derivedDedupeKey());
        $task->save();
        $this->assertNull($task->fresh()->getRawOriginal('dedupe_key'));

        $request = HousekeepingTask::factory()->request()->create(['dedupe_key' => 'bogus']);
        $this->assertNull($request->fresh()->getRawOriginal('dedupe_key'));
        $this->assertNull($request->derivedDedupeKey());
    }

    public function test_room_row_is_locked_before_the_task_table(): void
    {
        $room = Room::factory()->create(['status' => 'dirty']);

        $locked = $this->lockedSelects(fn () => $this->turnover($room));

        $this->assertNotEmpty($locked);
        $this->assertStringContainsString('from "rooms"', $locked[0]);
        $this->assertNotEmpty(array_filter(
            array_slice($locked, 1),
            fn (string $sql) => str_contains($sql, 'from "housekeeping_tasks"'),
        ));
    }

    public function test_history_and_task_roll_back_together(): void
    {
        $room = Room::factory()->create(['status' => 'dirty']);
        Schema::drop('housekeeping_task_status_history');

        try {
            $this->turnover($room);
            $this->fail('Expected the history insert to fail.');
        } catch (\Illuminate\Database\QueryException) {
        }

        $this->assertSame(0, HousekeepingTask::count());
    }
}
