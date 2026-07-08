<?php
namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * @group p4
 *
 * Placeholder for the real booking-code → guest device-link E2E flow.
 * The linking logic is built in P4.R (real Reservation model + pending_verification state).
 * In P2.5 the VerifyOtpAction booking_link branch is a guarded no-op that throws
 * booking_link_unavailable (see BookingCodeLinkTest::test_booking_link_verify_purpose_now_throws_unavailable).
 */
class BookingCodeLinkE2ETest extends TestCase
{
    use RefreshDatabase;

    public function test_booking_code_links_guest_end_to_end(): void
    {
        $this->markTestSkipped('Will be completed in P4.R');
    }
}
