<?php

namespace Tests\Feature\Cms;

use App\Models\RoomType;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `max_occupancy >= base_occupancy` must hold on `PUT`, not only on `POST`.
 *
 * `CreateRoomTypeRequest` states the invariant as `gte:base_occupancy`.
 * `UpdateRoomTypeRequest` did not state it at all, so a `PUT` could invert the
 * pair — a room type that sleeps at most one but is priced and booked for four.
 * Copying `gte` across would only half-fix it: Laravel resolves `gte`'s other
 * operand from the *request*, so on a partial `PUT` that sends only
 * `max_occupancy` the comparison has nothing to compare against and passes.
 *
 * Remove `withValidator()` from `UpdateRoomTypeRequest` and the four tests in the
 * first two sections go red:
 *
 *   - test_update_cannot_invert_the_occupancy_pair
 *   - test_lowering_max_below_the_stored_base_is_rejected
 *   - test_raising_base_above_the_stored_max_is_rejected
 *   - test_the_rejection_names_max_occupancy_and_the_effective_base
 */
class RoomTypeOccupancyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function editorToken(): string
    {
        $user = User::factory()->create();
        $user->givePermissionTo('cms.edit');

        return $user->createToken('t')->plainTextToken;
    }

    // ── Both fields in one payload ────────────────────────────────────────

    public function test_update_cannot_invert_the_occupancy_pair(): void
    {
        $roomType = RoomType::factory()->create(['base_occupancy' => 2, 'max_occupancy' => 4]);

        $this->withToken($this->editorToken())
            ->putJson("/api/cms/room-types/{$roomType->uuid}", [
                'base_occupancy' => 4,
                'max_occupancy'  => 2,
            ])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_failed')
            ->assertJsonValidationErrors(['max_occupancy']);

        $roomType->refresh();
        $this->assertSame(2, $roomType->base_occupancy);
        $this->assertSame(4, $roomType->max_occupancy);
    }

    public function test_the_right_way_round_is_accepted(): void
    {
        $roomType = RoomType::factory()->create(['base_occupancy' => 2, 'max_occupancy' => 4]);

        $this->withToken($this->editorToken())
            ->putJson("/api/cms/room-types/{$roomType->uuid}", [
                'base_occupancy' => 3,
                'max_occupancy'  => 6,
            ])
            ->assertOk();

        $roomType->refresh();
        $this->assertSame(3, $roomType->base_occupancy);
        $this->assertSame(6, $roomType->max_occupancy);
    }

    /** Equal is legal — a single that sleeps exactly one. */
    public function test_equal_occupancies_are_accepted(): void
    {
        $roomType = RoomType::factory()->create(['base_occupancy' => 2, 'max_occupancy' => 4]);

        $this->withToken($this->editorToken())
            ->putJson("/api/cms/room-types/{$roomType->uuid}", [
                'base_occupancy' => 1,
                'max_occupancy'  => 1,
            ])
            ->assertOk();

        $this->assertSame(1, $roomType->fresh()->max_occupancy);
    }

    // ── Partial updates: the missing side comes from the stored row ────────

    public function test_lowering_max_below_the_stored_base_is_rejected(): void
    {
        $roomType = RoomType::factory()->create(['base_occupancy' => 4, 'max_occupancy' => 6]);

        $this->withToken($this->editorToken())
            ->putJson("/api/cms/room-types/{$roomType->uuid}", ['max_occupancy' => 2])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['max_occupancy']);

        $this->assertSame(6, $roomType->fresh()->max_occupancy);
    }

    public function test_raising_base_above_the_stored_max_is_rejected(): void
    {
        $roomType = RoomType::factory()->create(['base_occupancy' => 2, 'max_occupancy' => 3]);

        $this->withToken($this->editorToken())
            ->putJson("/api/cms/room-types/{$roomType->uuid}", ['base_occupancy' => 5])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['max_occupancy']);

        $this->assertSame(2, $roomType->fresh()->base_occupancy);
    }

    public function test_a_partial_update_within_the_stored_bounds_is_accepted(): void
    {
        $roomType = RoomType::factory()->create(['base_occupancy' => 2, 'max_occupancy' => 6]);

        $this->withToken($this->editorToken())
            ->putJson("/api/cms/room-types/{$roomType->uuid}", ['max_occupancy' => 4])
            ->assertOk();

        $this->assertSame(4, $roomType->fresh()->max_occupancy);

        $this->withToken($this->editorToken())
            ->putJson("/api/cms/room-types/{$roomType->uuid}", ['base_occupancy' => 4])
            ->assertOk();

        $this->assertSame(4, $roomType->fresh()->base_occupancy);
    }

    /**
     * The message must say which number the caller is being measured against —
     * and it is the *effective* base (the stored 4), not something from a payload
     * that never mentioned it.
     */
    public function test_the_rejection_names_max_occupancy_and_the_effective_base(): void
    {
        $roomType = RoomType::factory()->create(['base_occupancy' => 4, 'max_occupancy' => 6]);

        $message = $this->withToken($this->editorToken())
            ->putJson("/api/cms/room-types/{$roomType->uuid}", ['max_occupancy' => 2])
            ->assertStatus(422)
            ->json('errors.max_occupancy.0');

        $this->assertSame(
            __('custom.validation.gte', ['attribute' => 'max_occupancy', 'value' => 4]),
            $message,
        );
        // Not a raw translation key — the same bar every other 422 clears.
        $this->assertDoesNotMatchRegularExpression('/^(custom\.)?validation\./', (string) $message);
    }

    /** In Arabic too: the check reports through `custom.validation.*` like the rest. */
    public function test_the_rejection_is_localized(): void
    {
        $roomType = RoomType::factory()->create(['base_occupancy' => 4, 'max_occupancy' => 6]);

        $message = $this->withToken($this->editorToken())
            ->withHeaders(['Accept-Language' => 'ar'])
            ->putJson("/api/cms/room-types/{$roomType->uuid}", ['max_occupancy' => 2])
            ->assertStatus(422)
            ->json('errors.max_occupancy.0');

        $this->assertMatchesRegularExpression('/\p{Arabic}/u', (string) $message);
    }

    // ── The pair is left alone when nothing touches it ────────────────────

    /**
     * A row can only be stored valid, so an update that mentions neither field
     * must not manufacture an error out of the stored pair.
     */
    public function test_an_update_touching_neither_field_is_accepted(): void
    {
        $roomType = RoomType::factory()->create(['base_occupancy' => 2, 'max_occupancy' => 4]);

        $this->withToken($this->editorToken())
            ->putJson("/api/cms/room-types/{$roomType->uuid}", ['sort_order' => 7])
            ->assertOk();

        $this->assertSame(7, $roomType->fresh()->sort_order);
    }

    /**
     * A non-integer is reported as a type error and not also as a comparison
     * error — one wrong field, one message.
     */
    public function test_a_non_integer_occupancy_is_reported_once(): void
    {
        $roomType = RoomType::factory()->create(['base_occupancy' => 2, 'max_occupancy' => 4]);

        $this->withToken($this->editorToken())
            ->putJson("/api/cms/room-types/{$roomType->uuid}", ['max_occupancy' => 'four'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['max_occupancy'])
            ->assertJsonCount(1, 'errors.max_occupancy');
    }

    // ── Create keeps its own rule ─────────────────────────────────────────

    public function test_create_still_rejects_an_inverted_pair(): void
    {
        $this->withToken($this->editorToken())
            ->postJson('/api/cms/room-types', [
                'name'           => ['en' => 'Inverted', 'ar' => 'مقلوب'],
                'description'    => ['en' => 'x', 'ar' => 'س'],
                'base_occupancy' => 4,
                'max_occupancy'  => 2,
                'base_price_usd' => 100,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['max_occupancy']);
    }
}
