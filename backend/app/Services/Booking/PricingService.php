<?php

namespace App\Services\Booking;

use App\Actions\Booking\QuoteReservationAction;
use App\Enums\ModifierType;
use App\Models\RoomType;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class PricingService
{
    public function __construct(private readonly QuoteReservationAction $action) {}

    public function quote(string $roomTypeUuid, string $checkIn, string $checkOut, ?string $promoCode = null): array
    {
        $roomType = RoomType::where('uuid', $roomTypeUuid)->where('is_active', true)->firstOrFail();
        $pricing  = $this->action->handle($roomType, $checkIn, $checkOut, $promoCode);

        return ['data' => $pricing, 'code' => 200];
    }

    /**
     * Effective nightly rate of a room type on one date (Phase 2, D-10).
     *
     * A deliberate clone of QuoteReservationAction's rule loop, not a call into
     * it: the quote applies its rules to the whole stay, and making it per-night
     * is deferred. Until then the two loops must stay identical, so:
     * - rules are walked in the order the collection holds them, which is the
     *   natural SQL order of the caller's query, exactly as in the quote (the
     *   collection is never re-ordered here);
     * - percentage multiplies by (1 + v/100), anything else adds v, on floats;
     * - one round to 2 places after all modifiers;
     * - no day-of-week logic: a `weekend` rule applies on every date of its
     *   window, because the quote does the same.
     *
     * A rule applies when it belongs to the room type, is active, and
     * starts_on <= date <= ends_on (inclusive on both ends, D-10).
     *
     * @param  Collection<int, \App\Models\PricingRule>  $rules
     * @return array{rate_usd: float, rule_scope: ?string}
     */
    public function nightlyRate(RoomType $type, CarbonInterface $date, Collection $rules): array
    {
        $daily = (float) $type->base_price_usd;
        $scope = null;
        $day   = $date->toDateString();

        foreach ($rules as $rule) {
            if ((int) $rule->room_type_id !== (int) $type->id || ! $rule->is_active) {
                continue;
            }

            if ($rule->starts_on->toDateString() > $day || $rule->ends_on->toDateString() < $day) {
                continue;
            }

            if ($rule->modifier_type === ModifierType::PERCENTAGE) {
                $daily *= 1 + ((float) $rule->modifier_value / 100);
            } else {
                $daily += (float) $rule->modifier_value;
            }

            $scope = $rule->scope?->value;
        }

        return ['rate_usd' => round($daily, 2), 'rule_scope' => $scope];
    }
}
