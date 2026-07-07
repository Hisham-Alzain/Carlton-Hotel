<?php
namespace App\Models;

use App\Traits\HasUuid;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class Guest extends Authenticatable
{
    use HasApiTokens, HasFactory, HasUuid, LogsActivity;

    protected $fillable = [
        'uuid', 'name', 'phone', 'phone_country', 'phone_verified_at',
        'email', 'email_verified_at', 'first_name', 'last_name', 'preferred_locale',
    ];

    protected $hidden = [];

    protected function casts(): array
    {
        return [
            'phone_verified_at' => 'datetime',
            'email_verified_at' => 'datetime',
        ];
    }

    public function markPhoneVerified(): void
    {
        $this->phone_verified_at = now();
        $this->save();
    }

    public function markEmailVerified(): void
    {
        $this->email_verified_at = now();
        $this->save();
    }

    public function scopeByPhone($query, string $e164)
    {
        return $query->where('phone', $e164);
    }

    public function scopeByEmail($query, string $email)
    {
        return $query->where('email', $email);
    }
}
