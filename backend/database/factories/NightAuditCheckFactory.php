<?php

namespace Database\Factories;

use App\Enums\NightAuditCheckStatus;
use App\Enums\NightAuditCheckType;
use App\Models\NightAudit;
use App\Models\NightAuditCheck;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NightAuditCheck>
 */
class NightAuditCheckFactory extends Factory
{
    protected $model = NightAuditCheck::class;

    public function definition(): array
    {
        return [
            'night_audit_id'     => NightAudit::factory(),
            'type'               => NightAuditCheckType::DIRTY_ROOMS,
            'blocking'           => false,
            'status'             => NightAuditCheckStatus::PENDING,
            'issue_count'        => 1,
            'evidence'           => [],
            'evidence_truncated' => false,
            'note'               => null,
            'acted_by'           => null,
            'acted_at'           => null,
        ];
    }

    public function ofType(NightAuditCheckType $type): static
    {
        return $this->state(['type' => $type, 'blocking' => $type->isBlocking()]);
    }

    public function passed(): static
    {
        return $this->state(['status' => NightAuditCheckStatus::PASSED, 'issue_count' => 0]);
    }
}
