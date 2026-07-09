<?php

namespace Tests\Feature\Cms;

use App\Models\Page;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PageTest extends TestCase
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
            'slug'       => 'about-us',
            'title'      => ['en' => 'About Us', 'ar' => 'من نحن'],
            'content'    => ['en' => 'We are a hotel.', 'ar' => 'نحن فندق.'],
            'is_active'  => true,
        ], $overrides);
    }

    public function test_admin_can_crud_page(): void
    {
        $token = $this->editorToken();

        $res = $this->withToken($token)->postJson('/api/cms/pages', $this->payload())
            ->assertStatus(201);
        $uuid = $res->json('data.uuid');
        $this->assertSame('about-us', $res->json('data.slug'));

        $this->withToken($token)->getJson("/api/cms/pages/{$uuid}")->assertOk();

        $this->withToken($token)->putJson("/api/cms/pages/{$uuid}", ['title' => ['en' => 'About Carlton', 'ar' => 'عن كارلتون']])
            ->assertOk()->assertJsonPath('data.title.en', 'About Carlton');

        $this->withToken($token)->deleteJson("/api/cms/pages/{$uuid}")->assertStatus(204);
    }

    public function test_slug_must_be_unique(): void
    {
        Page::factory()->create(['slug' => 'about-us']);
        $this->withToken($this->editorToken())
            ->postJson('/api/cms/pages', $this->payload())
            ->assertStatus(422);
    }

    public function test_staff_without_permission_cannot_manage_pages(): void
    {
        $token = User::factory()->create()->createToken('t')->plainTextToken;
        $this->withToken($token)->postJson('/api/cms/pages', $this->payload())->assertStatus(403);
    }

    public function test_public_can_fetch_active_page_by_slug(): void
    {
        Page::factory()->create(['slug' => 'privacy-policy', 'is_active' => true]);
        $this->getJson('/api/public/pages/privacy-policy')
            ->assertOk()
            ->assertJsonPath('data.slug', 'privacy-policy');
    }

    public function test_public_fetch_inactive_page_by_slug_returns_404(): void
    {
        Page::factory()->create(['slug' => 'draft-page', 'is_active' => false]);
        $this->getJson('/api/public/pages/draft-page')->assertNotFound();
    }

    public function test_public_fetch_nonexistent_slug_returns_404(): void
    {
        $this->getJson('/api/public/pages/does-not-exist')->assertNotFound();
    }

    public function test_locale_switching_returns_correct_translation(): void
    {
        Page::factory()->create([
            'slug'    => 'terms',
            'title'   => ['en' => 'Terms', 'ar' => 'الشروط'],
            'content' => ['en' => 'EN content', 'ar' => 'محتوى عربي'],
        ]);

        $res = $this->withHeaders(['Accept-Language' => 'ar'])
            ->getJson('/api/public/pages/terms')
            ->assertOk();

        $this->assertSame('الشروط', $res->json('data.title.ar'));
        $this->assertSame('محتوى عربي', $res->json('data.content.ar'));
    }
}
