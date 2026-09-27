<?php

namespace Tests\Feature\Housekeeping;

use App\Actions\Housekeeping\CreateHousekeepingTaskAction;
use App\Enums\HousekeepingTaskType;
use App\Models\HousekeepingTask;
use App\Models\Room;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * D-02: `housekeeping:reconcile` re-derives stale dedupe keys and lists dirty
 * rooms without an open turnover task.
 */
class ReconcileHousekeepingTasksTest extends TestCase
{
    use RefreshDatabase;

    private function rawTurnover(Room $room, string $status, ?string $key): void
    {
        DB::table('housekeeping_tasks')->insert([
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

    public function test_reconcile_clears_a_stale_key_and_unblocks_creation(): void
    {
        $room = Room::factory()->create(['number' => '501', 'status' => 'available']);
        $this->rawTurnover($room, 'done', "{$room->id}:turnover");

        $this->artisan('housekeeping:reconcile')
            ->expectsOutputToContain('Cleared 1 stale dedupe key(s).')
            ->assertExitCode(0);

        $this->assertNull(HousekeepingTask::first()->getRawOriginal('dedupe_key'));

        $result = app(CreateHousekeepingTaskAction::class)->ensureOpen($room, HousekeepingTaskType::TURNOVER, [], null);
        $this->assertSame(201, $result['code']);
    }

    public function test_reconcile_restores_a_missing_key_on_an_open_task(): void
    {
        $room = Room::factory()->create(['status' => 'dirty']);
        $this->rawTurnover($room, 'pending', null);

        $this->artisan('housekeeping:reconcile')
            ->expectsOutputToContain('Restored 1 missing dedupe key(s).')
            ->doesntExpectOutputToContain('Duplicate open tasks')
            ->assertExitCode(0);

        $this->assertSame("{$room->id}:turnover", HousekeepingTask::first()->getRawOriginal('dedupe_key'));

        // The restored key blocks a second open turnover again.
        $result = app(CreateHousekeepingTaskAction::class)->ensureOpen($room, HousekeepingTaskType::TURNOVER, [], null);
        $this->assertSame(200, $result['code']);
        $this->assertSame(1, HousekeepingTask::count());
    }

    public function test_reconcile_reports_an_open_duplicate_it_cannot_key(): void
    {
        $room = Room::factory()->create(['status' => 'dirty']);
        $this->rawTurnover($room, 'pending', "{$room->id}:turnover");
        $this->rawTurnover($room, 'in_progress', null);
        $duplicate = HousekeepingTask::whereNull('dedupe_key')->firstOrFail();

        $this->artisan('housekeeping:reconcile')
            ->expectsOutputToContain('Restored 0 missing dedupe key(s).')
            ->expectsOutputToContain("Duplicate open tasks (close by hand): {$duplicate->uuid}")
            ->assertExitCode(0);

        $this->assertNull($duplicate->fresh()->getRawOriginal('dedupe_key'));
    }

    public function test_reconcile_leaves_closed_tasks_keyless(): void
    {
        $room = Room::factory()->create(['status' => 'available']);
        $this->rawTurnover($room, 'done', null);
        $this->rawTurnover($room, 'cancelled', null);

        $this->artisan('housekeeping:reconcile')
            ->expectsOutputToContain('Restored 0 missing dedupe key(s).')
            ->assertExitCode(0);

        $this->assertSame(0, HousekeepingTask::whereNotNull('dedupe_key')->count());
    }

    public function test_reconcile_lists_dirty_rooms_without_an_open_turnover(): void
    {
        Room::factory()->create(['number' => '101', 'status' => 'dirty']);
        $r102 = Room::factory()->create(['number' => '102', 'status' => 'dirty']);
        Room::factory()->create(['number' => '103', 'status' => 'available']);
        $r104 = Room::factory()->create(['number' => '104', 'status' => 'dirty']);

        HousekeepingTask::factory()->create(['room_id' => $r102->id]);
        HousekeepingTask::factory()->done()->create(['room_id' => $r104->id]);

        $this->artisan('housekeeping:reconcile')
            ->expectsOutputToContain('Dirty rooms without an open turnover task: 101, 104')
            ->doesntExpectOutputToContain('102')
            ->doesntExpectOutputToContain('103')
            ->assertExitCode(0);
    }

    public function test_reconcile_reports_nothing_on_a_clean_state(): void
    {
        $this->artisan('housekeeping:reconcile')
            ->expectsOutputToContain('Cleared 0 stale dedupe key(s).')
            ->expectsOutputToContain('Dirty rooms without an open turnover task: none')
            ->assertExitCode(0);
    }
}
