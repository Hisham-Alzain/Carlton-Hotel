<?php

namespace Tests\Feature\Service;

use App\Models\Transfer;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Transfers carry a bilingual description and a passenger cap (IT6-03).
 *
 * @group p7
 */
class TransferContractTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function staff(string ...$permissions): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo($permissions);

        return $user;
    }

    private function payload(array $extra = []): array
    {
        return array_merge([
            'name' => ['en' => 'Airport Pickup', 'ar' => 'استقبال المطار'],
            'price_usd' => 25,
        ], $extra);
    }

    public function test_cms_create_stores_description_and_max_passengers(): void
    {
        $description = ['en' => 'Private sedan.', 'ar' => 'سيارة سيدان خاصة.'];

        $this->actingAs($this->staff('cms.edit'), 'users')
            ->postJson('/api/cms/transfers', $this->payload(['description' => $description, 'max_passengers' => 4]))
            ->assertCreated()
            ->assertJsonPath('data.description', $description)
            ->assertJsonPath('data.max_passengers', 4);

        $this->assertDatabaseHas('transfers', ['max_passengers' => 4]);
    }

    public function test_cms_create_without_new_fields_still_works_and_returns_nulls(): void
    {
        $this->actingAs($this->staff('cms.edit'), 'users')
            ->postJson('/api/cms/transfers', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.description', null)
            ->assertJsonPath('data.max_passengers', null);
    }

    public function test_cms_update_changes_and_clears_the_new_fields(): void
    {
        $admin = $this->staff('cms.edit');
        $transfer = Transfer::factory()->create();

        $this->actingAs($admin, 'users')
            ->putJson("/api/cms/transfers/{$transfer->uuid}", $this->payload([
                'max_passengers' => 6,
                'description' => ['en' => 'x', 'ar' => 'y'],
            ]))
            ->assertOk()
            ->assertJsonPath('data.max_passengers', 6)
            ->assertJsonPath('data.description', ['en' => 'x', 'ar' => 'y']);

        $this->actingAs($admin, 'users')
            ->putJson("/api/cms/transfers/{$transfer->uuid}", $this->payload([
                'max_passengers' => null,
                'description' => ['en' => null, 'ar' => null],
            ]))
            ->assertOk()
            ->assertJsonPath('data.max_passengers', null)
            ->assertJsonPath('data.description', null);
    }

    public function test_public_list_returns_description_and_max_passengers(): void
    {
        Transfer::factory()->create([
            'description' => ['en' => 'Van', 'ar' => 'فان'],
            'max_passengers' => 8,
        ]);

        $this->getJson('/api/public/transfers')
            ->assertOk()
            ->assertJsonPath('data.items.0.description', ['en' => 'Van', 'ar' => 'فان'])
            ->assertJsonPath('data.items.0.max_passengers', 8);
    }

    public function test_public_list_returns_nulls_for_rows_without_the_fields(): void
    {
        Transfer::factory()->create(['description' => null, 'max_passengers' => null]);

        $this->getJson('/api/public/transfers')
            ->assertOk()
            ->assertJsonPath('data.items.0.description', null)
            ->assertJsonPath('data.items.0.max_passengers', null);
    }

    public function test_invalid_values_fail_validation(): void
    {
        $admin = $this->staff('cms.edit');

        foreach ([0, 'abc', 101] as $bad) {
            $this->actingAs($admin, 'users')
                ->postJson('/api/cms/transfers', $this->payload(['max_passengers' => $bad]))
                ->assertStatus(422)
                ->assertJsonPath('error_code', 'validation_failed')
                ->assertJsonValidationErrors(['max_passengers'], 'errors');
        }

        $this->actingAs($admin, 'users')
            ->postJson('/api/cms/transfers', $this->payload(['description' => 'plain string']))
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_failed')
            ->assertJsonValidationErrors(['description'], 'errors');

        $this->actingAs($admin, 'users')
            ->postJson('/api/cms/transfers', $this->payload(['description' => ['en' => str_repeat('a', 2001)]]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['description.en'], 'errors');
    }

    public function test_unauthenticated_write_is_401(): void
    {
        $this->postJson('/api/cms/transfers', $this->payload(['max_passengers' => 4]))->assertUnauthorized();
    }

    public function test_staff_without_cms_edit_cannot_write(): void
    {
        $this->actingAs($this->staff('cms.view'), 'users')
            ->postJson('/api/cms/transfers', $this->payload(['max_passengers' => 4]))
            ->assertForbidden();
    }
}
