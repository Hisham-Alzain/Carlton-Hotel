<?php

namespace Tests\Feature\Cms;

use App\Models\Promotion;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PromotionTest extends TestCase
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

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'title'       => ['en' => 'Summer Deal', 'ar' => 'عرض الصيف'],
            'description' => ['en' => '20% off on all rooms', 'ar' => 'خصم ٢٠٪ على جميع الغرف'],
            'valid_from'  => '2026-07-01',
            'valid_until' => '2026-08-31',
            'is_active'   => true,
        ], $overrides);
    }

    public function test_admin_can_crud_promotion(): void
    {
        $token = $this->editorToken();

        $uuid = $this->withToken($token)->postJson('/api/cms/promotions', $this->payload())
            ->assertStatus(201)->json('data.uuid');

        $this->withToken($token)->getJson("/api/cms/promotions/{$uuid}")->assertOk();

        $this->withToken($token)->putJson("/api/cms/promotions/{$uuid}", ['title' => ['en' => 'Winter Deal', 'ar' => 'عرض الشتاء']])
            ->assertOk()->assertJsonPath('data.title.en', 'Winter Deal');

        $this->withToken($token)->deleteJson("/api/cms/promotions/{$uuid}")->assertStatus(204);
    }

    public function test_staff_without_permission_cannot_manage_promotions(): void
    {
        $token = User::factory()->create()->createToken('t')->plainTextToken;
        $this->withToken($token)->postJson('/api/cms/promotions', $this->payload())->assertStatus(403);
    }

    public function test_public_index_hides_inactive_promotions(): void
    {
        Promotion::factory()->count(2)->create(['is_active' => true]);
        Promotion::factory()->create(['is_active' => false]);

        $res = $this->getJson('/api/public/promotions')->assertOk();
        $this->assertCount(2, $res->json('data.items'));
    }

    public function test_public_show_inactive_promotion_returns_404(): void
    {
        $p = Promotion::factory()->create(['is_active' => false]);
        $this->getJson("/api/public/promotions/{$p->uuid}")->assertNotFound();
    }

    public function test_valid_until_must_be_after_valid_from(): void
    {
        $this->withToken($this->editorToken())
            ->postJson('/api/cms/promotions', array_merge($this->payload(), [
                'valid_from'  => '2026-08-31',
                'valid_until' => '2026-07-01',
            ]))
            ->assertStatus(422);
    }

    public function test_admin_can_upload_image_to_promotion(): void
    {
        Storage::fake('public');
        $p = Promotion::factory()->create();
        $this->withToken($this->editorToken())
            ->postJson("/api/cms/promotions/{$p->uuid}/images", [
                'image' => UploadedFile::fake()->image('promo.jpg'),
            ])
            ->assertStatus(201);
        $this->assertDatabaseCount('media', 1);
    }
}
