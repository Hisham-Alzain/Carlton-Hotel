<?php
namespace App\Actions\Auth;

use Illuminate\Support\Facades\Log;

class OtpDispatcher
{
    // Holds codes in memory for test assertions. Not for production use.
    protected static array $sent = [];

    public function send(string $identifier, string $channel, string $code): void
    {
        static::$sent[] = compact('identifier', 'channel', 'code');
        // TODO(P9): wire real SMS/WhatsApp/email provider
        if (app()->isLocal() || app()->environment('testing')) {
            Log::info('OTP dispatched', ['identifier' => $identifier, 'channel' => $channel]);
        }
    }

    public static function lastCode(): ?string
    {
        return empty(static::$sent) ? null : end(static::$sent)['code'];
    }

    public static function reset(): void { static::$sent = []; }

    public static function allSent(): array { return static::$sent; }
}
