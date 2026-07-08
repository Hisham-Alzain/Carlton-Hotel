<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OtpCode extends Model
{
    use HasFactory;

    const CHANNEL_SMS       = 'sms';
    const CHANNEL_WHATSAPP  = 'whatsapp';
    const CHANNEL_EMAIL     = 'email';
    const PURPOSE_LOGIN        = 'login';
    const PURPOSE_REGISTER     = 'register';
    const PURPOSE_BOOKING_LINK         = 'booking_link';
    const PURPOSE_BOOKING_VERIFICATION = 'booking_verification';

    protected $fillable = ['identifier','channel','code_hash','purpose','attempts','expires_at','consumed_at'];
    protected $hidden   = ['code_hash'];

    protected function casts(): array
    {
        return [
            'expires_at'  => 'datetime',
            'consumed_at' => 'datetime',
            'attempts'    => 'integer',
        ];
    }

    public function scopeActive($query)
    {
        return $query->whereNull('consumed_at')->where('expires_at', '>', now());
    }

    public function scopeForIdentifier($query, string $identifier, string $purpose)
    {
        return $query->where('identifier', $identifier)->where('purpose', $purpose);
    }

    public function isExpired(): bool   { return $this->expires_at->isPast(); }
    public function isConsumed(): bool  { return $this->consumed_at !== null; }
    public function isLocked(int $max = 5): bool { return $this->attempts >= $max; }
}
