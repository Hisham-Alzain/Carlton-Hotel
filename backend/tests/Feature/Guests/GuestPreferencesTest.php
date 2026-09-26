<?php

namespace Tests\Feature\Guests;

use App\Enums\BedType;
use App\Enums\FloorPreference;
use App\Enums\PillowType;
use App\Models\Guest;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * PATCH /api/auth/guest/preferences and PATCH /api/guests/{guest}/preferences
 * (Phase 4, GUEST-04, D-08, D-09, T-04-05).
 */
class GuestPreferencesTest extends TestCase
{
    use RefreshDatabase;

    private const KEYS = ['bed_type', 'pillow_type', 'floor_preference', 'other', 'updated_at'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->travelTo(Carbon::parse('2027-03-10 09:00:00'));
    }

    private function staffUser(string ...$permissions): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo($permissions);
        return $user;
    }

    private function staffToken(string ...$permissions): string
    {
        return $this->staffUser(...$permissions)->createToken('t')->plainTextToken;
    }

    private function guestPatch(Guest $guest, array $body)
    {
        return $this->withToken($guest->createToken('t')->plainTextToken)
            ->withHeaders(['Accept-Language' => 'en'])
            ->patchJson('/api/auth/guest/preferences', $body);
    }

    private function staffPatch(Guest $guest, array $body, ?string $token = null)
    {
        return $this->withToken($token ?? $this->staffToken('guests.edit'))
            ->withHeaders(['Accept-Language' => 'en'])
            ->patchJson("/api/guests/{$guest->uuid}/preferences", $body);
    }

    /** activity_log rows on this guest, oldest first, with decoded changes. */
    private function guestActivity(Guest $guest)
    {
        return DB::table('activity_log')
            ->where('subject_type', $guest->getMorphClass())
            ->where('subject_id', $guest->id)
            ->orderBy('id')
            ->get();
    }

    public function test_guest_saves_preferences(): void
    {
        $guest = Guest::factory()->create();

        $res = $this->guestPatch($guest, [
            'bed_type' => 'king', 'pillow_type' => 'firm', 'floor_preference' => 'high', 'other' => 'Near the lift',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Preferences updated.')
            ->assertJsonPath('data.bed_type', 'king')
            ->assertJsonPath('data.pillow_type', 'firm')
            ->assertJsonPath('data.floor_preference', 'high')
            ->assertJsonPath('data.other', 'Near the lift')
            ->assertJsonPath('data.updated_at', '2027-03-10T09:00:00+00:00');

        $this->assertSame(self::KEYS, array_keys($res->json('data')));

        $guest->refresh();
        $this->assertSame(BedType::KING, $guest->bed_type);
        $this->assertSame(PillowType::FIRM, $guest->pillow_type);
        $this->assertSame(FloorPreference::HIGH, $guest->floor_preference);
        $this->assertSame('Near the lift', $guest->preferences_other);
        $this->assertTrue($guest->preferences_updated_at->equalTo(now()));
    }

    public function test_staff_saves_preferences_for_a_guest(): void
    {
        $guest = Guest::factory()->create();
        $other = Guest::factory()->create();

        $res = $this->staffPatch($guest, ['bed_type' => 'twin', 'floor_preference' => 'low'])
            ->assertOk()
            ->assertJsonPath('message', 'Preferences updated.')
            ->assertJsonPath('data.bed_type', 'twin')
            ->assertJsonPath('data.floor_preference', 'low')
            ->assertJsonPath('data.pillow_type', null);

        $this->assertSame(self::KEYS, array_keys($res->json('data')));
        $this->assertSame(BedType::TWIN, $guest->refresh()->bed_type);
        $this->assertNull($other->refresh()->bed_type);
        $this->assertNull($other->preferences_updated_at);
    }

    public function test_patch_merges_and_null_clears(): void
    {
        $guest = Guest::factory()->create();
        $this->guestPatch($guest, [
            'bed_type' => 'queen', 'pillow_type' => 'medium', 'floor_preference' => 'high', 'other' => 'Quiet',
        ])->assertOk();
        $first = $guest->refresh()->preferences_updated_at;

        $this->travel(1)->minutes();
        $this->guestPatch($guest, ['pillow_type' => 'soft'])
            ->assertOk()
            ->assertJsonPath('data.bed_type', 'queen')
            ->assertJsonPath('data.pillow_type', 'soft')
            ->assertJsonPath('data.floor_preference', 'high')
            ->assertJsonPath('data.other', 'Quiet');
        $second = $guest->refresh()->preferences_updated_at;
        $this->assertTrue($second->greaterThan($first));

        $this->travel(1)->minutes();
        $this->guestPatch($guest, ['floor_preference' => null])
            ->assertOk()
            ->assertJsonPath('data.floor_preference', null)
            ->assertJsonPath('data.bed_type', 'queen')
            ->assertJsonPath('data.pillow_type', 'soft');
        $this->assertTrue($guest->refresh()->preferences_updated_at->greaterThan($second));

        $this->guestPatch($guest, ['other' => ''])->assertOk()->assertJsonPath('data.other', null);
        $this->assertNull($guest->refresh()->preferences_other);
        $this->assertSame(BedType::QUEEN, $guest->bed_type);
    }

    public function test_body_without_any_preference_key_is_rejected(): void
    {
        $guest = Guest::factory()->create();

        foreach ([[], ['color' => 'red']] as $body) {
            $this->guestPatch($guest, $body)
                ->assertStatus(422)
                ->assertJsonPath('error_code', 'validation_failed')
                ->assertJsonValidationErrors('preferences');
            $this->staffPatch($guest, $body)
                ->assertStatus(422)
                ->assertJsonPath('error_code', 'validation_failed')
                ->assertJsonValidationErrors('preferences');
        }

        $this->assertNull($guest->refresh()->preferences_updated_at);
    }

    public function test_enum_rules(): void
    {
        $guest = Guest::factory()->create();

        $this->guestPatch($guest, ['bed_type' => 'extra'])->assertStatus(422)->assertJsonValidationErrors('bed_type');
        $this->guestPatch($guest, ['bed_type' => 'waterbed'])->assertStatus(422)->assertJsonValidationErrors('bed_type');
        $this->guestPatch($guest, ['pillow_type' => 'stone'])->assertStatus(422)->assertJsonValidationErrors('pillow_type');
        $this->guestPatch($guest, ['floor_preference' => 'middle'])->assertStatus(422)->assertJsonValidationErrors('floor_preference');
        $this->guestPatch($guest, ['other' => str_repeat('a', 501)])->assertStatus(422)->assertJsonValidationErrors('other');
        $this->staffPatch($guest, ['bed_type' => 'extra'])->assertStatus(422)->assertJsonValidationErrors('bed_type');

        $this->guestPatch($guest, ['floor_preference' => 'any'])->assertOk()->assertJsonPath('data.floor_preference', 'any');
        $this->guestPatch($guest, ['other' => str_repeat('ض', 500)])->assertOk();
    }

    public function test_arabic_and_emoji_in_other_round_trip(): void
    {
        $guest = Guest::factory()->create();
        $text  = 'غرفة هادئة 🌙 بعيدة عن المصعد';

        $this->guestPatch($guest, ['other' => $text])->assertOk()->assertJsonPath('data.other', $text);
        $this->assertSame($text, $guest->refresh()->preferences_other);
    }

    public function test_me_includes_preferences(): void
    {
        $guest = Guest::factory()->create();
        $token = $guest->createToken('t')->plainTextToken;

        $patched = $this->withToken($token)
            ->patchJson('/api/auth/guest/preferences', ['bed_type' => 'double', 'floor_preference' => 'any'])
            ->assertOk()
            ->json('data');

        $me = $this->withToken($token)->getJson('/api/auth/guest/me')->assertOk();
        $this->assertSame($patched, $me->json('data.preferences'));
    }

    public function test_guest_patch_logs_the_guest_as_causer_and_hides_sensitive_values(): void
    {
        $guest  = Guest::factory()->create();
        $before = $this->guestActivity($guest)->count();

        $this->guestPatch($guest, [
            'bed_type' => 'king', 'pillow_type' => 'hypoallergenic', 'other' => 'ALLERGY-SENTINEL-3K',
        ])->assertOk();

        $rows = $this->guestActivity($guest);
        $this->assertCount($before + 1, $rows);
        $row = $rows->last();

        $this->assertSame($guest->getMorphClass(), $row->causer_type);
        $this->assertEquals($guest->id, $row->causer_id);

        $changes = (string) $row->attribute_changes;
        $this->assertStringContainsString('bed_type', $changes);
        foreach (['pillow_type', 'preferences_other', 'hypoallergenic', 'ALLERGY-SENTINEL-3K'] as $needle) {
            $this->assertStringNotContainsString($needle, $changes, $needle);
            $this->assertStringNotContainsString($needle, (string) $row->properties, $needle);
        }
    }

    public function test_staff_patch_logs_the_staff_user_as_causer(): void
    {
        $guest = Guest::factory()->create();
        $staff = $this->staffUser('guests.edit');

        $this->staffPatch($guest, ['bed_type' => 'single'], $staff->createToken('t')->plainTextToken)->assertOk();

        $row = $this->guestActivity($guest)->last();
        $this->assertSame($staff->getMorphClass(), $row->causer_type);
        $this->assertEquals($staff->id, $row->causer_id);
    }

    public function test_free_text_only_edit_logs_only_the_timestamp(): void
    {
        $guest = Guest::factory()->create();

        $this->guestPatch($guest, ['other' => 'x'])->assertOk();

        $changes = json_decode((string) $this->guestActivity($guest)->last()->attribute_changes, true);
        $this->assertSame(['preferences_updated_at'], array_keys($changes['attributes']));
    }

    public function test_gates(): void
    {
        $guest = Guest::factory()->create();
        $body  = ['bed_type' => 'king'];

        $this->patchJson('/api/auth/guest/preferences', $body)->assertStatus(401)->assertJsonPath('error_code', 'unauthorized');
        $this->patchJson("/api/guests/{$guest->uuid}/preferences", $body)->assertStatus(401)->assertJsonPath('error_code', 'unauthorized');

        $this->withToken($this->staffToken('guests.edit'))
            ->patchJson('/api/auth/guest/preferences', $body)->assertStatus(401);
        $this->withToken($guest->createToken('t')->plainTextToken)
            ->patchJson("/api/guests/{$guest->uuid}/preferences", $body)->assertStatus(401);

        $this->staffPatch($guest, $body, $this->staffToken('guests.view'))
            ->assertStatus(403)->assertJsonPath('error_code', 'forbidden');

        $this->withToken($this->staffToken('guests.edit'))
            ->patchJson('/api/guests/' . Str::uuid() . '/preferences', $body)
            ->assertStatus(404)->assertJsonPath('error_code', 'not_found');

        $this->assertNull($guest->refresh()->bed_type);
    }

    public function test_guest_route_only_touches_the_token_guest(): void
    {
        $a = Guest::factory()->create();
        $b = Guest::factory()->create();

        $this->guestPatch($a, ['bed_type' => 'king', 'guest_uuid' => $b->uuid, 'guest_id' => $b->id])->assertOk();

        $this->assertSame(BedType::KING, $a->refresh()->bed_type);
        $this->assertNull($b->refresh()->bed_type);
        $this->assertNull($b->preferences_updated_at);
    }
}
