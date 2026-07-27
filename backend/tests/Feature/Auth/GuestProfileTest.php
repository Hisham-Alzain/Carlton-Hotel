<?php

namespace Tests\Feature\Auth;

use App\Models\Guest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuestProfileTest extends TestCase
{
    use RefreshDatabase;

    // ── Completing the profile after sign-in ──────────────────────────────

    public function test_guest_can_set_names_and_fill_in_the_missing_contact(): void
    {
        // Signed in by phone; email was never captured.
        $guest = Guest::factory()->create([
            'phone'             => '+963991234567',
            'phone_verified_at' => now(),
            'email'             => null,
            'email_verified_at' => null,
            'first_name'        => null,
            'last_name'         => null,
        ]);

        $this->actingAs($guest, 'guests')
            ->putJson('/api/auth/guest/profile', [
                'first_name' => 'Layla',
                'last_name'  => 'Haddad',
                'email'      => 'Layla.Haddad@Example.com',
            ])
            ->assertOk()
            ->assertJsonPath('data.first_name', 'Layla')
            ->assertJsonPath('data.last_name', 'Haddad')
            // Normalized to lower case before storage.
            ->assertJsonPath('data.email', 'layla.haddad@example.com');

        $this->assertSame('Layla Haddad', $guest->fresh()->name);
    }

    public function test_phone_is_normalized_to_e164(): void
    {
        $guest = Guest::factory()->create(['phone' => null, 'phone_verified_at' => null]);

        $this->actingAs($guest, 'guests')
            ->putJson('/api/auth/guest/profile', ['phone' => '0991234567'])
            ->assertOk()
            ->assertJsonPath('data.phone', '+963991234567');
    }

    public function test_unparseable_phone_is_rejected(): void
    {
        $guest = Guest::factory()->create(['phone' => null, 'phone_verified_at' => null]);

        $this->actingAs($guest, 'guests')
            ->putJson('/api/auth/guest/profile', ['phone' => 'not-a-number'])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_failed');
    }

    public function test_partial_update_leaves_other_fields_alone(): void
    {
        $guest = Guest::factory()->create(['first_name' => 'Layla', 'last_name' => 'Haddad']);

        $this->actingAs($guest, 'guests')
            ->putJson('/api/auth/guest/profile', ['preferred_locale' => 'ar'])
            ->assertOk()
            ->assertJsonPath('data.first_name', 'Layla')
            ->assertJsonPath('data.preferred_locale', 'ar');
    }

    // ── Verified contacts are locked ──────────────────────────────────────

    public function test_changing_a_verified_phone_is_refused(): void
    {
        $guest = Guest::factory()->create([
            'phone'             => '+963991234567',
            'phone_verified_at' => now(),
        ]);

        $this->actingAs($guest, 'guests')
            ->putJson('/api/auth/guest/profile', ['phone' => '+963997654321'])
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'verified_contact_immutable');

        $this->assertSame('+963991234567', $guest->fresh()->phone);
    }

    public function test_changing_a_verified_email_is_refused(): void
    {
        $guest = Guest::factory()->create([
            'email'             => 'old@example.com',
            'email_verified_at' => now(),
        ]);

        $this->actingAs($guest, 'guests')
            ->putJson('/api/auth/guest/profile', ['email' => 'new@example.com'])
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'verified_contact_immutable');
    }

    public function test_resending_the_same_verified_contact_is_a_no_op(): void
    {
        $guest = Guest::factory()->create([
            'email'             => 'same@example.com',
            'email_verified_at' => now(),
        ]);

        $this->actingAs($guest, 'guests')
            ->putJson('/api/auth/guest/profile', ['email' => 'same@example.com', 'first_name' => 'Nour'])
            ->assertOk()
            ->assertJsonPath('data.first_name', 'Nour');
    }

    // ── Uniqueness + auth ─────────────────────────────────────────────────

    public function test_email_already_taken_by_another_guest_is_rejected(): void
    {
        Guest::factory()->create(['email' => 'taken@example.com']);
        $guest = Guest::factory()->create(['email' => null, 'email_verified_at' => null]);

        $this->actingAs($guest, 'guests')
            ->putJson('/api/auth/guest/profile', ['email' => 'taken@example.com'])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_failed');
    }

    public function test_unauthenticated_cannot_update_a_profile(): void
    {
        $this->putJson('/api/auth/guest/profile', ['first_name' => 'X'])->assertStatus(401);
    }

    public function test_staff_token_cannot_reach_the_guest_profile_endpoint(): void
    {
        $token = \App\Models\User::factory()->create()->createToken('t')->plainTextToken;

        $this->withToken($token)
            ->putJson('/api/auth/guest/profile', ['first_name' => 'X'])
            ->assertStatus(401);
    }
}
