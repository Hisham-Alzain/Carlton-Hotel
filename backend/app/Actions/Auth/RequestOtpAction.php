<?php

namespace App\Actions\Auth;

use App\Exceptions\TooManyRequestsException;
use App\Models\OtpCode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

class RequestOtpAction
{
    public function __construct(private readonly OtpDispatcher $dispatcher) {}

    public function handle(string $identifier, string $channel, string $purpose): array
    {
        // Rate-limit: 1 per minute per identifier
        $minuteKey = "otp:min:{$identifier}:{$purpose}";
        if (RateLimiter::tooManyAttempts($minuteKey, 1)) {
            throw new TooManyRequestsException(__('custom.errors.too_many_requests'));
        }
        RateLimiter::hit($minuteKey, 60);

        // Rate-limit: 5 per hour per identifier
        $hourKey = "otp:hour:{$identifier}:{$purpose}";
        if (RateLimiter::tooManyAttempts($hourKey, 5)) {
            throw new TooManyRequestsException(__('custom.errors.too_many_requests'));
        }
        RateLimiter::hit($hourKey, 3600);

        // TODO: remove once a real SMS/WhatsApp/email provider is wired (OtpDispatcher
        // is still a stub) — static code makes manual/Postman testing possible without
        // reading storage/logs/laravel.log for every request.
        $code =  '000000';
        // str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        DB::transaction(function () use ($identifier, $channel, $purpose, $code) {
            // Invalidate any prior active codes for same identifier+purpose
            OtpCode::forIdentifier($identifier, $purpose)
                ->whereNull('consumed_at')
                ->update(['consumed_at' => now()]);

            OtpCode::create([
                'identifier' => $identifier,
                'channel'    => $channel,
                'code_hash'  => Hash::make($code),
                'purpose'    => $purpose,
                'attempts'   => 0,
                'expires_at' => now()->addMinutes(5),
                'consumed_at' => null,
            ]);
        });

        $this->dispatcher->send($identifier, $channel, $code);

        return [
            'data' => [
                'identifier' => $identifier,
                'channel'    => $channel,
                'expires_in' => 300, // seconds
            ],
            'code' => 200,
        ];
    }
}
