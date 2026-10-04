<?php

namespace App\Models;

use App\Enums\NightAuditBlockerStatus;
use App\Enums\NightAuditStatus;
use App\Traits\HasUuid;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Support\LogOptions;

/**
 * One night audit per business date (Phase 9, D-07). Created lazily with its
 * five checks by `OpenNightAuditAction`; the snapshot columns
 * (`snapshot_basis`, `evaluated_at`) are never updated afterwards. Closed by
 * `CloseNightAuditAction`; there is no reopen and no delete.
 */
class NightAudit extends Model
{
    use HasFactory, HasUuid, LogsActivity;

    public const SNAPSHOT_BASIS = 'current_state_at_open';

    protected $fillable = [
        'business_date', 'status', 'snapshot_basis', 'evaluated_at',
        'opened_by', 'closed_by', 'closed_at',
    ];

    protected $casts = [
        'business_date' => 'date:Y-m-d',
        'status'        => NightAuditStatus::class,
        'evaluated_at'  => 'datetime',
        'closed_at'     => 'datetime',
    ];

    public function checks(): HasMany   { return $this->hasMany(NightAuditCheck::class)->orderBy('id'); }
    public function blockers(): HasMany { return $this->hasMany(NightAuditBlocker::class)->orderBy('id'); }
    public function opener(): BelongsTo { return $this->belongsTo(User::class, 'opened_by'); }
    public function closer(): BelongsTo { return $this->belongsTo(User::class, 'closed_by'); }

    public function isClosed(): bool
    {
        return $this->status === NightAuditStatus::CLOSED;
    }

    /**
     * Close readiness (D-12) from the already-loaded `checks` and `blockers`;
     * callers load them first (NightAuditService::payload), so no query runs.
     *
     * @return array{checks_pending: int, blockers_open: int, can_close: bool}
     */
    public function readiness(): array
    {
        $checksPending = $this->checks->filter(fn (NightAuditCheck $c) => ! $c->status->isTerminal())->count();
        $blockersOpen  = $this->blockers->filter(fn (NightAuditBlocker $b) => $b->status === NightAuditBlockerStatus::OPEN)->count();

        return [
            'checks_pending' => $checksPending,
            'blockers_open'  => $blockersOpen,
            'can_close'      => $checksPending === 0 && $blockersOpen === 0 && $this->status === NightAuditStatus::OPEN,
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'closed_by', 'closed_at'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
