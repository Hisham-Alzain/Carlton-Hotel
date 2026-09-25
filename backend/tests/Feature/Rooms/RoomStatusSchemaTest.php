<?php

namespace Tests\Feature\Rooms;

use App\Models\Room;
use App\Models\RoomType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Schema produced by the Phase 2 migrations (D-01, D-03): rooms.status as a
 * string with its index kept, the history table and the denormalised columns.
 */
class RoomStatusSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_status_column_accepts_dirty_and_defaults_to_available(): void
    {
        $room = Room::factory()->create(['status' => 'dirty']);
        $this->assertSame('dirty', DB::table('rooms')->where('id', $room->id)->value('status'));

        $type = RoomType::factory()->create();
        DB::table('rooms')->insert([
            'uuid'         => (string) Str::uuid(),
            'room_type_id' => $type->id,
            'number'       => '999',
            'is_active'    => true,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        $this->assertSame('available', DB::table('rooms')->where('number', '999')->value('status'));
    }

    public function test_status_index_survives_the_column_change(): void
    {
        $this->assertTrue(Schema::hasIndex('rooms', ['status']));

        // Exactly one single-column index over status (no duplicate from the change).
        $statusIndexes = collect(Schema::getIndexes('rooms'))
            ->filter(fn (array $i) => $i['columns'] === ['status']);
        $this->assertCount(1, $statusIndexes);
    }

    public function test_live_room_number_unique_stays_scoped_to_live_rows(): void
    {
        // The SQLite rebuild must not turn the partial unique into a plain one:
        // a trashed room's number can be reused.
        $type = RoomType::factory()->create();
        $old  = Room::factory()->create(['room_type_id' => $type->id, 'number' => '404']);
        $old->delete();

        $new = Room::factory()->create(['room_type_id' => $type->id, 'number' => '404']);
        $this->assertNotSame($old->id, $new->id);
    }

    public function test_history_table_and_denormalized_columns_exist(): void
    {
        $this->assertTrue(Schema::hasColumns('room_status_history', [
            'id', 'room_id', 'from_status', 'to_status', 'changed_by', 'reason', 'created_at',
        ]));
        $this->assertFalse(Schema::hasColumn('room_status_history', 'updated_at'));
        $this->assertTrue(Schema::hasColumns('rooms', ['status_changed_at', 'status_changed_by']));

        $this->assertTrue(Schema::hasIndex('room_status_history', ['room_id', 'created_at']));
        $this->assertTrue(Schema::hasIndex('room_status_history', ['changed_by']));
        $this->assertTrue(Schema::hasIndex('rooms', ['status_changed_by']));
    }
}
