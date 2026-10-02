<?php

namespace Tests\Unit\Support;

use App\Exceptions\QueueItemAlreadyClaimedException;
use App\Models\HousekeepingTask;
use App\Models\ServiceRequest;
use App\Models\Ticket;
use App\Models\User;
use App\Support\ClaimGuard;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * ClaimGuard (Phase 7, D-22, council A8): the mine / nobody's / someone
 * else's decision every assign writer makes inside its own lock.
 */
class ClaimGuardTest extends TestCase
{
    use RefreshDatabase;

    public static function types(): array
    {
        return [
            'ticket'            => [Ticket::class],
            'service request'   => [ServiceRequest::class],
            'housekeeping task' => [HousekeepingTask::class],
        ];
    }

    /** @param class-string<Model> $class */
    private function item(string $class, ?User $assignee): Model
    {
        return $class::factory()->create(['assigned_user_id' => $assignee?->id]);
    }

    #[DataProvider('types')]
    public function test_unassigned_item_is_claimable(string $class): void
    {
        $this->assertFalse(ClaimGuard::check($this->item($class, null), User::factory()->create()));
    }

    #[DataProvider('types')]
    public function test_own_item_is_a_no_op(string $class): void
    {
        $actor = User::factory()->create();

        $this->assertTrue(ClaimGuard::check($this->item($class, $actor), $actor));
    }

    #[DataProvider('types')]
    public function test_someone_elses_item_throws_409(string $class): void
    {
        $other = User::factory()->create();
        $item  = $this->item($class, $other);

        try {
            ClaimGuard::check($item, User::factory()->create());
            $this->fail('Expected QueueItemAlreadyClaimedException');
        } catch (QueueItemAlreadyClaimedException $e) {
            $this->assertSame(409, $e->statusCode());
            $this->assertSame('queue_item_already_claimed', $e->errorCode());
            $this->assertSame(['assigned_user_uuid' => $other->uuid], $e->context());
        }
    }
}
