<?php

namespace Tests\Unit\Loyalty;

use App\Actions\Loyalty\RedeemRewardAction;
use App\Models\Guest;
use App\Models\LoyaltyReward;
use App\Models\LoyaltyVoucher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsLoyaltyFixtures;
use Tests\TestCase;

/**
 * Phase 10 LOY-13: RedeemRewardAction returns the service-layer envelope, is
 * idempotent per key, and snapshots the reward onto the voucher.
 */
class RedeemRewardActionTest extends TestCase
{
    use BuildsLoyaltyFixtures;
    use RefreshDatabase;

    private function action(): RedeemRewardAction
    {
        return app(RedeemRewardAction::class);
    }

    public function test_it_returns_the_voucher_with_201_then_200_on_replay(): void
    {
        $guest = Guest::factory()->create();
        $this->grantPoints($guest, 3000);
        $reward = LoyaltyReward::factory()->create(['points_cost' => 2500]);

        $first = $this->action()->handle($guest, $reward, 'K-1');
        $replay = $this->action()->handle($guest, $reward, 'K-1');

        $this->assertSame(201, $first['code']);
        $this->assertInstanceOf(LoyaltyVoucher::class, $first['data']);
        $this->assertSame(200, $replay['code']);
        $this->assertTrue($first['data']->is($replay['data']));
        $this->assertSame(1, LoyaltyVoucher::count());
    }

    public function test_the_voucher_snapshot_survives_editing_the_reward(): void
    {
        $guest = Guest::factory()->create();
        $this->grantPoints($guest, 3000);
        $reward = LoyaltyReward::factory()->create([
            'name' => ['en' => 'Spa credit', 'ar' => 'رصيد السبا'],
            'points_cost' => 2500,
            'discount_usd' => '25.00',
        ]);

        $voucher = $this->action()->handle($guest, $reward, 'K-1')['data'];

        $this->assertSame(['en' => 'Spa credit', 'ar' => 'رصيد السبا'], $voucher->reward_name);

        $reward->update([
            'name' => ['en' => 'Renamed', 'ar' => 'اسم جديد'],
            'discount_usd' => '99.00',
            'points_cost' => 9999,
        ]);

        $fresh = $voucher->fresh();
        $this->assertSame(['en' => 'Spa credit', 'ar' => 'رصيد السبا'], $fresh->reward_name);
        $this->assertSame('25.00', $fresh->value_usd);
        $this->assertSame(2500, $fresh->points_spent);
    }
}
