<?php

namespace Tests\Feature\Housekeeping;

use App\Models\HousekeepingTask;
use App\Models\HousekeepingTaskStatusHistory;
use App\Models\Room;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** POST /api/housekeeping/tasks (Phase 6, D-08, HK-04). */
class CreateTest extends TestCase
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

    private function create(array $body, ?string $token = null)
    {
        return $this->withToken($token ?? $this->staffToken('housekeeping.assign'))
            ->withHeaders(['Accept-Language' => 'en'])
            ->postJson('/api/housekeeping/tasks', $body);
    }

    public function test_creates_a_task(): void
    {
        $room = Room::factory()->create(['status' => 'dirty']);
        $user = User::factory()->create();
        $user->assignRole('reception');

        $response = $this->create([
            'room_uuid' => $room->uuid,
            'type'      => 'turnover',
            'due_at'    => '2027-03-12T15:00:00+03:00',
            'priority'  => 'high',
            'notes'     => 'VIP arriving',
        ], $user->createToken('t')->plainTextToken)
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', __('custom.messages.housekeeping_task_created'))
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.type', 'turnover')
            ->assertJsonPath('data.priority', 'high')
            ->assertJsonPath('data.room.uuid', $room->uuid)
            ->assertJsonPath('data.due_at', '2027-03-12T12:00:00+00:00');

        $task = HousekeepingTask::where('uuid', $response->json('data.uuid'))->firstOrFail();
        $this->assertSame($user->id, $task->created_by);
        $this->assertSame('manual', HousekeepingTaskStatusHistory::where('housekeeping_task_id', $task->id)->value('reason'));
        $this->assertSame('dirty', $room->fresh()->status->value);
    }

    public function test_existing_open_task_is_returned(): void
    {
        $room  = Room::factory()->create();
        $first = $this->create(['room_uuid' => $room->uuid, 'type' => 'turnover'])->assertCreated()->json('data.uuid');

        $this->create(['room_uuid' => $room->uuid, 'type' => 'turnover'])
            ->assertOk()
            ->assertJsonPath('message', __('custom.messages.housekeeping_task_exists'))
            ->assertJsonPath('data.uuid', $first);

        $this->assertSame(1, HousekeepingTask::count());
    }

    public function test_stayover_and_inspection_are_allowed(): void
    {
        $room = Room::factory()->create();

        foreach (['stayover', 'inspection'] as $type) {
            $this->create(['room_uuid' => $room->uuid, 'type' => $type])
                ->assertCreated()
                ->assertJsonPath('data.type', $type)
                ->assertJsonPath('data.priority', 'normal');
        }

        $this->assertSame(2, HousekeepingTask::count());
    }

    public function test_request_type_is_rejected(): void
    {
        $room = Room::factory()->create();

        $this->create(['room_uuid' => $room->uuid, 'type' => 'request'])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_failed')
            ->assertJsonValidationErrors(['type']);

        $this->assertSame(0, HousekeepingTask::count());
    }

    public function test_validation(): void
    {
        $room = Room::factory()->create();

        $cases = [
            'room_uuid' => ['room_uuid' => fake()->uuid(), 'type' => 'turnover'],
            'due_at'    => ['room_uuid' => $room->uuid, 'type' => 'turnover', 'due_at' => '2027-03-12T09:00:00Z'],
            'priority'  => ['room_uuid' => $room->uuid, 'type' => 'turnover', 'priority' => 'urgent'],
            'notes'     => ['room_uuid' => $room->uuid, 'type' => 'turnover', 'notes' => str_repeat('a', 1001)],
        ];

        foreach ($cases as $field => $body) {
            $this->create($body)
                ->assertStatus(422)
                ->assertJsonPath('error_code', 'validation_failed')
                ->assertJsonValidationErrors([$field]);
        }

        $this->create([])->assertStatus(422)->assertJsonValidationErrors(['room_uuid', 'type']);
        $this->assertSame(0, HousekeepingTask::count());
    }

    public function test_requires_a_token(): void
    {
        $this->postJson('/api/housekeeping/tasks', [])->assertStatus(401);
    }

    public function test_view_only_holder_cannot_create(): void
    {
        $room = Room::factory()->create();

        $this->create(['room_uuid' => $room->uuid, 'type' => 'turnover'], $this->staffToken('housekeeping.view'))
            ->assertStatus(403)
            ->assertJsonPath('success', false);

        $this->assertSame(0, HousekeepingTask::count());
    }
}
