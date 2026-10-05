<?php

namespace App\Services\Loyalty;

use App\Actions\Loyalty\RedeemRewardAction;
use App\Base\BaseService;
use App\Filters\LoyaltyVoucherFilter;
use App\Models\Guest;
use App\Models\LoyaltyReward;
use App\Models\LoyaltyVoucher;

/**
 * A guest's loyalty vouchers (Phase 10, LOY-13/LOY-14): redeeming a reward and
 * listing the caller's own vouchers. The caller picks the guest; this class
 * never reads the request.
 */
class LoyaltyVoucherService extends BaseService
{
    protected string $model = LoyaltyVoucher::class;

    protected ?string $filter = LoyaltyVoucherFilter::class;

    /** Column-limited: the resource needs only these reservation fields. */
    protected array $with = ['reservation:id,uuid,booking_code'];

    public function __construct(private readonly RedeemRewardAction $redeemReward) {}

    /**
     * The guest's vouchers, newest first, narrowed by `LoyaltyVoucherFilter`.
     *
     * @param  array<string, mixed>  $params  the query string, via the controller's indexParams()
     */
    public function indexForGuest(Guest $guest, array $params = [], ?int $perPage = null): array
    {
        $query = $this->query()
            ->where('guest_id', $guest->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        $this->makeFilter($params)?->apply($query);

        return ['data' => $query->paginate($this->resolvePerPage($perPage)), 'code' => 200];
    }

    /**
     * Spend the reward's points on a voucher, once per `$key`.
     *
     * @return array{data: LoyaltyVoucher, code: int}
     */
    public function redeem(Guest $guest, LoyaltyReward $reward, string $key): array
    {
        return $this->redeemReward->handle($guest, $reward, $key);
    }
}
