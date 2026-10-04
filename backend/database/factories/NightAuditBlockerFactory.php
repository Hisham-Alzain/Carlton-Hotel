<?php

namespace Database\Factories;

use App\Enums\NightAuditBlockerStatus;
use App\Enums\NightAuditCheckType;
use App\Models\NightAuditBlocker;
use App\Models\NightAuditCheck;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NightAuditBlocker>
 */
class NightAuditBlockerFactory extends Factory
{
    protected $model = NightAuditBlocker::class;

    public function definition(): array
    {
        return [
            'night_audit_check_id' => NightAuditCheck::factory()->ofType(NightAuditCheckType::UNSETTLED_DEPARTURES),
            'night_audit_id'       => fn (array $attributes) => NightAuditCheck::query()
                ->whereKey($attributes['night_audit_check_id'])->value('night_audit_id'),
            'status'               => NightAuditBlockerStatus::OPEN,
            'note'                 => null,
            'acted_by'             => null,
            'acted_at'             => null,
        ];
    }

    /** Attach to an existing check (and its audit). */
    public function forCheck(NightAuditCheck $check): static
    {
        return $this->state([
            'night_audit_check_id' => $check->id,
            'night_audit_id'       => $check->night_audit_id,
        ]);
    }
}
