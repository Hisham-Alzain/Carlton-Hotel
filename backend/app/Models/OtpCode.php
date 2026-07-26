<?php
namespace App\Models;

use App\Enums\OtpChannel;
use App\Enums\OtpPurpose;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OtpCode extends Model
{
    use HasFactory;

    protected $fillable = ['identifier','channel','code_hash','purpose','attempts','expires_at','consumed_at'];
    protected $hidden   = ['code_hash'];

    protected function casts(): array
    {
        return [
            'channel'     => OtpChannel::class,
            'purpose'     => OtpPurpose::class,
            'expires_at'  => 'datetime',
            'consumed_at' => 'datetime',
            'attempts'    => 'integer',
        ];
    }

    public function scopeActive($query)
    {
        return $query->whereNull('consumed_at')->where('expires_at', '>', now());
    }

    public function scopeForIdentifier($query, string $identifier, OtpPurpose|string $purpose)
    {
        return $query->where('identifier', $identifier)
            ->where('purpose', $purpose instanceof OtpPurpose ? $purpose->value : $purpose);
    }

    public function isExpired(): bool   { return $this->expires_at->isPast(); }
    public function isConsumed(): bool  { return $this->consumed_at !== null; }
    public function isLocked(int $max = 5): bool { return $this->attempts >= $max; }
}
