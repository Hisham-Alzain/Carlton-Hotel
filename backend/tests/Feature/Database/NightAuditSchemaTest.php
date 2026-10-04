<?php

namespace Tests\Feature\Database;

use App\Enums\NightAuditCheckStatus;
use App\Enums\NightAuditCheckType;
use App\Models\NightAudit;
use App\Models\NightAuditBlocker;
use App\Models\NightAuditCheck;
use App\Models\NightAuditState;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Phase 9 storage (D-07, D-08, D-14): four audit tables, two report indexes,
 * restrict FKs and a scoped activity log.
 */
class NightAuditSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_tables_have_the_d07_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('night_audit_states', [
            'id', 'singleton', 'current_business_date', 'last_closed_date', 'created_at', 'updated_at',
        ]));
        $this->assertFalse(Schema::hasColumn('night_audit_states', 'uuid'));

        $this->assertTrue(Schema::hasColumns('night_audits', [
            'id', 'uuid', 'business_date', 'status', 'snapshot_basis', 'evaluated_at',
            'opened_by', 'closed_by', 'closed_at', 'created_at', 'updated_at',
        ]));

        $this->assertTrue(Schema::hasColumns('night_audit_checks', [
            'id', 'uuid', 'night_audit_id', 'type', 'blocking', 'status', 'issue_count', 'evidence',
            'evidence_truncated', 'note', 'acted_by', 'acted_at', 'created_at', 'updated_at',
        ]));

        $this->assertTrue(Schema::hasColumns('night_audit_blockers', [
            'id', 'uuid', 'night_audit_id', 'night_audit_check_id', 'status', 'note',
            'acted_by', 'acted_at', 'created_at', 'updated_at',
        ]));

        foreach (['night_audit_states', 'night_audits', 'night_audit_checks', 'night_audit_blockers'] as $table) {
            $this->assertFalse(Schema::hasColumn($table, 'deleted_at'), $table);
        }
    }

    public function test_business_date_is_unique(): void
    {
        NightAudit::factory()->create(['business_date' => '2026-10-01']);

        $this->expectException(UniqueConstraintViolationException::class);
        NightAudit::factory()->create(['business_date' => '2026-10-01']);
    }

    public function test_check_type_is_unique_per_audit(): void
    {
        $audit = NightAudit::factory()->create();
        NightAuditCheck::factory()->for($audit, 'audit')->create(['type' => NightAuditCheckType::DIRTY_ROOMS]);

        $this->expectException(UniqueConstraintViolationException::class);
        NightAuditCheck::factory()->for($audit, 'audit')->create(['type' => NightAuditCheckType::DIRTY_ROOMS]);
    }

    public function test_one_blocker_per_check(): void
    {
        $check = NightAuditCheck::factory()->create(['type' => NightAuditCheckType::UNSETTLED_DEPARTURES]);
        NightAuditBlocker::factory()->forCheck($check)->create();

        $this->expectException(UniqueConstraintViolationException::class);
        NightAuditBlocker::factory()->forCheck($check)->create();
    }

    public function test_state_is_a_singleton(): void
    {
        NightAuditState::factory()->create();

        $this->expectException(UniqueConstraintViolationException::class);
        NightAuditState::factory()->create();
    }

    public function test_user_referenced_by_an_audit_cannot_be_deleted(): void
    {
        $opener = User::factory()->create();
        $closer = User::factory()->create();
        NightAudit::factory()->create(['opened_by' => $opener->id, 'closed_by' => $closer->id]);

        foreach ([$opener, $closer] as $user) {
            try {
                DB::table('users')->where('id', $user->id)->delete();
                $this->fail('restrictOnDelete did not fire for user ' . $user->id);
            } catch (QueryException) {
                $this->assertDatabaseHas('users', ['id' => $user->id]);
            }
        }
    }

    public function test_user_referenced_by_a_check_or_blocker_cannot_be_deleted(): void
    {
        $checker = User::factory()->create();
        $blockerActor = User::factory()->create();
        $check = NightAuditCheck::factory()->create([
            'type'     => NightAuditCheckType::UNSETTLED_DEPARTURES,
            'acted_by' => $checker->id,
        ]);
        NightAuditBlocker::factory()->forCheck($check)->create(['acted_by' => $blockerActor->id]);

        foreach ([$checker, $blockerActor] as $user) {
            try {
                DB::table('users')->where('id', $user->id)->delete();
                $this->fail('restrictOnDelete did not fire for user ' . $user->id);
            } catch (QueryException) {
                $this->assertDatabaseHas('users', ['id' => $user->id]);
            }
        }
    }

    public function test_audit_with_checks_cannot_be_deleted(): void
    {
        $audit = NightAudit::factory()->create();
        NightAuditCheck::factory()->for($audit, 'audit')->create();

        $this->expectException(QueryException::class);
        DB::table('night_audits')->where('id', $audit->id)->delete();
    }

    public function test_indexes_exist(): void
    {
        $this->assertHasIndex('night_audits', ['status']);
        $this->assertHasIndex('night_audits', ['opened_by']);
        $this->assertHasIndex('night_audits', ['closed_by']);
        $this->assertHasIndex('night_audit_checks', ['acted_by']);
        $this->assertHasIndex('night_audit_checks', ['night_audit_id', 'type']);
        $this->assertHasIndex('night_audit_blockers', ['night_audit_id']);
        $this->assertHasIndex('night_audit_blockers', ['acted_by']);
        $this->assertHasIndex('night_audit_blockers', ['night_audit_check_id']);
        // D-08 report indexes.
        $this->assertHasIndex('folio_items', ['created_at']);
        $this->assertHasIndex('payments', ['status', 'created_at']);
    }

    public function test_models_relations_and_casts(): void
    {
        $opener = User::factory()->create();
        $audit = NightAudit::factory()->create(['opened_by' => $opener->id, 'business_date' => '2026-10-03']);
        $check = NightAuditCheck::factory()->for($audit, 'audit')->create([
            'type'     => NightAuditCheckType::UNSETTLED_DEPARTURES,
            'blocking' => true,
            'evidence' => [['reservation_uuid' => 'x', 'booking_code' => 'B1']],
        ]);
        $blocker = NightAuditBlocker::factory()->forCheck($check)->create();

        $audit = $audit->fresh();
        $this->assertNotEmpty($audit->uuid);
        $this->assertInstanceOf(CarbonInterface::class, $audit->business_date);
        $this->assertSame('2026-10-03', $audit->business_date->toDateString());
        $this->assertCount(1, $audit->checks);
        $this->assertCount(1, $audit->blockers);
        $this->assertTrue($audit->opener->is($opener));
        $this->assertNull($audit->closer);

        $check = $check->fresh();
        $this->assertIsArray($check->evidence);
        $this->assertSame('B1', $check->evidence[0]['booking_code']);
        $this->assertTrue($check->blocking);
        $this->assertTrue($check->blocker->is($blocker));
        $this->assertSame(NightAuditCheckType::UNSETTLED_DEPARTURES, $check->type);

        $this->assertTrue($blocker->fresh()->check->is($check));
        $this->assertTrue($blocker->fresh()->audit->is($audit));

        foreach ([NightAuditState::class, NightAudit::class, NightAuditCheck::class, NightAuditBlocker::class] as $model) {
            $this->assertNotContains(SoftDeletes::class, class_uses_recursive($model), $model);
        }
    }

    public function test_activity_log_never_records_note_or_evidence(): void
    {
        $actor = User::factory()->create();
        $check = NightAuditCheck::factory()->create([
            'evidence' => [['room_uuid' => 'SENTINEL-EVIDENCE', 'number' => '101']],
        ]);

        $check->update([
            'status'   => NightAuditCheckStatus::RESOLVED,
            'note'     => 'SENTINEL-NOTE',
            'acted_by' => $actor->id,
            'acted_at' => now(),
        ]);

        $rows = DB::table('activity_log')->where('subject_type', $check->getMorphClass())->get();
        $this->assertGreaterThanOrEqual(1, $rows->count());

        $updated = $rows->firstWhere('event', 'updated');
        $this->assertNotNull($updated);
        $this->assertStringContainsString('"status"', (string) $updated->attribute_changes);

        foreach (DB::table('activity_log')->get() as $row) {
            foreach (['attribute_changes', 'properties'] as $column) {
                $logged = (string) $row->{$column};
                $this->assertStringNotContainsString('SENTINEL-NOTE', $logged);
                $this->assertStringNotContainsString('SENTINEL-EVIDENCE', $logged);
                $this->assertStringNotContainsString('"note"', $logged);
                $this->assertStringNotContainsString('"evidence"', $logged);
            }
        }
    }

    /** @param list<string> $columns */
    private function assertHasIndex(string $table, array $columns): void
    {
        $found = collect(Schema::getIndexes($table))->contains(fn (array $index) => $index['columns'] === $columns);
        $this->assertTrue($found, "{$table} has no index on [" . implode(',', $columns) . ']');
    }
}
