<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `POST /api/auth/login` must be throttled.
 *
 * It was not, while the guest OTP route beside it carried `throttle:10,1`. A
 * staff account holds `cms.edit`, the reservations verbs and folio settlement, so
 * an unthrottled password endpoint is unlimited credential stuffing against the
 * highest-value identities in the system — and the only trace is 401s in a log
 * nobody reads. The backend skill lists `throttle` on OTP, login and password
 * reset as a hard requirement (§Security).
 *
 * The limit is 10/minute, matching the OTP route: `throttle` keys on the client
 * IP and a hotel back office is one NAT'd address, so anything tighter locks out
 * a shift handover.
 *
 * Delete the `->middleware('throttle:10,1')` from the login route and
 * `test_repeated_failed_logins_are_throttled` goes red — the eleventh attempt
 * answers 401 instead of 429.
 */
class StaffLoginThrottleTest extends TestCase
{
    use RefreshDatabase;

    /** Must match `throttle:10,1` on the route. */
    private const LIMIT = 10;

    public function test_repeated_failed_logins_are_throttled(): void
    {
        User::factory()->create(['email' => 'target@hotel.com', 'password' => bcrypt('right')]);

        for ($attempt = 1; $attempt <= self::LIMIT; $attempt++) {
            $this->postJson('/api/auth/login', ['email' => 'target@hotel.com', 'password' => "guess-{$attempt}"])
                ->assertStatus(401)
                ->assertJsonPath('error_code', 'unauthorized');
        }

        // One past the limit: the guessing stops, and it stops through the
        // project's own envelope rather than a bare Laravel 429 page.
        $this->postJson('/api/auth/login', ['email' => 'target@hotel.com', 'password' => 'guess-11'])
            ->assertStatus(429)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error_code', 'too_many_requests');
    }

    /**
     * The throttle counts requests, not failures, so a locked-out address cannot
     * be rescued by finally guessing right.
     */
    public function test_the_throttle_holds_even_once_the_credentials_are_correct(): void
    {
        User::factory()->create(['email' => 'target@hotel.com', 'password' => bcrypt('right')]);

        for ($attempt = 1; $attempt <= self::LIMIT; $attempt++) {
            $this->postJson('/api/auth/login', ['email' => 'target@hotel.com', 'password' => 'wrong'])
                ->assertStatus(401);
        }

        $this->postJson('/api/auth/login', ['email' => 'target@hotel.com', 'password' => 'right'])
            ->assertStatus(429)
            ->assertJsonPath('error_code', 'too_many_requests');
    }

    /**
     * The throttle must not be the kind of fix that breaks the endpoint it
     * protects: a real member of staff still logs in, and still logs in after a
     * handful of typos.
     */
    public function test_a_legitimate_login_still_succeeds(): void
    {
        $user = User::factory()->create(['email' => 'staff@hotel.com', 'password' => bcrypt('secret')]);

        $this->postJson('/api/auth/login', ['email' => 'staff@hotel.com', 'password' => 'secret'])
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.uuid', $user->uuid);
    }

    public function test_a_few_typos_do_not_lock_a_real_user_out(): void
    {
        User::factory()->create(['email' => 'staff@hotel.com', 'password' => bcrypt('secret')]);

        foreach (['secrt', 'secret1', 'Secret'] as $typo) {
            $this->postJson('/api/auth/login', ['email' => 'staff@hotel.com', 'password' => $typo])
                ->assertStatus(401);
        }

        $this->postJson('/api/auth/login', ['email' => 'staff@hotel.com', 'password' => 'secret'])
            ->assertStatus(200)
            ->assertJsonPath('success', true);
    }
}
