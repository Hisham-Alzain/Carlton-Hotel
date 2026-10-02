<?php

namespace Tests\Feature\Staff;

use App\Exceptions\AssigneeNotEligibleException;
use App\Models\User;
use App\Support\AssigneeEligibility;
use App\Support\OperationsQueueType;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Per-preset assignability (Phase 7, D-09, council A3, PR-5): which seeded
 * role presets can receive work of each queue type. A user is assignable when
 * they hold the type's work permission (service_requests.update |
 * tickets.respond | housekeeping.update). This table is the source for the
 * dashboard guide's "who becomes un-assignable" section (A3).
 */
class AssignabilityMatrixTest extends TestCase
{
    use RefreshDatabase;

    private const MATRIX = [
        //                   service-requests, tickets, housekeeping-tasks
        'reception'       => [true,  true,  false],
        'kitchen'         => [true,  false, false],
        'housekeeping'    => [true,  false, true],
        'concierge'       => [true,  true,  false],
        'events'          => [false, true,  false],
        'content_editor'  => [false, false, false],
        'content_manager' => [false, false, false],
    ];

    private const SEGMENTS = ['service-requests', 'tickets', 'housekeeping-tasks'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public static function cells(): array
    {
        $cells = [];

        foreach (self::MATRIX as $preset => $row) {
            foreach (self::SEGMENTS as $i => $segment) {
                $cells["{$preset} → {$segment}"] = [$preset, $segment, $row[$i]];
            }
        }

        return $cells;
    }

    #[DataProvider('cells')]
    public function test_preset_assignability(string $preset, string $segment, bool $eligible): void
    {
        $user       = User::factory()->create(['is_active' => true])->assignRole($preset);
        $permission = OperationsQueueType::fromSegment($segment)->statusPermission;

        try {
            AssigneeEligibility::assert($user->fresh(), $permission);
            $this->assertTrue($eligible, "{$preset} should NOT be assignable to {$segment}");
        } catch (AssigneeNotEligibleException) {
            $this->assertFalse($eligible, "{$preset} should be assignable to {$segment}");
        }
    }

    public function test_matrix_covers_every_seeded_preset(): void
    {
        $this->assertEqualsCanonicalizing(
            array_keys(self::MATRIX),
            \Spatie\Permission\Models\Role::where('guard_name', 'users')->pluck('name')->all(),
        );
    }

    public function test_super_admin_is_assignable_everywhere(): void
    {
        $admin = User::factory()->superAdmin()->create();

        foreach (self::SEGMENTS as $segment) {
            AssigneeEligibility::assert($admin, OperationsQueueType::fromSegment($segment)->statusPermission);
        }

        $this->addToAssertionCount(3);
    }
}
