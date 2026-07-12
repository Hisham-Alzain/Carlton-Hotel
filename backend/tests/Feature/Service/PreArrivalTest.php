<?php

namespace Tests\Feature\Service;

use App\Models\Guest;
use App\Models\Reservation;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * @group p7
 */
class PreArrivalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        Storage::fake('public');
    }

    private function bookedGuest(): array
    {
        $guest = Guest::factory()->create();
        $reservation = Reservation::factory()->confirmed()->create(['guest_id' => $guest->id]);
        return [$guest, $reservation];
    }

    private function makeApprover(): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo('reservations.create');
        return $user;
    }

    public function test_guest_uploads_documents_creating_pending_approval(): void
    {
        [$guest, $reservation] = $this->bookedGuest();

        $this->actingAs($guest, 'guests')
             ->postJson('/api/pre-arrival/documents', [
                 'documents' => [
                     ['type' => 'passport', 'file' => UploadedFile::fake()->create('passport.pdf', 200)],
                 ],
             ])
             ->assertCreated();

        $this->assertDatabaseHas('guest_documents', ['guest_id' => $guest->id, 'type' => 'passport']);
        $this->assertDatabaseHas('check_in_approvals', ['reservation_id' => $reservation->id, 'status' => 'pending']);
    }

    public function test_multiple_documents_uploaded_in_one_request(): void
    {
        [$guest, ] = $this->bookedGuest();

        $this->actingAs($guest, 'guests')
             ->postJson('/api/pre-arrival/documents', [
                 'documents' => [
                     ['type' => 'passport', 'file' => UploadedFile::fake()->create('passport.pdf', 100)],
                     ['type' => 'visa',     'file' => UploadedFile::fake()->create('visa.pdf', 100)],
                 ],
             ])
             ->assertCreated();

        $this->assertDatabaseCount('guest_documents', 2);
    }

    public function test_admin_can_approve_check_in(): void
    {
        [$guest, $reservation] = $this->bookedGuest();
        $this->actingAs($guest, 'guests')
             ->postJson('/api/pre-arrival/documents', [
                 'documents' => [['type' => 'passport', 'file' => UploadedFile::fake()->create('p.pdf', 100)]],
             ])->assertCreated();

        $approver = $this->makeApprover();

        $this->actingAs($approver, 'users')
             ->patchJson("/api/cms/check-in-approvals/{$reservation->uuid}/approve", ['status' => 'approved'])
             ->assertOk()
             ->assertJsonPath('data.status', 'approved')
             ->assertJsonCount(1, 'data.documents');
    }

    public function test_admin_can_reject_check_in(): void
    {
        [$guest, $reservation] = $this->bookedGuest();
        $this->actingAs($guest, 'guests')
             ->postJson('/api/pre-arrival/documents', [
                 'documents' => [['type' => 'passport', 'file' => UploadedFile::fake()->create('p.pdf', 100)]],
             ])->assertCreated();

        $approver = $this->makeApprover();

        $this->actingAs($approver, 'users')
             ->patchJson("/api/cms/check-in-approvals/{$reservation->uuid}/approve", [
                 'status' => 'rejected',
                 'notes'  => 'Blurry passport photo',
             ])
             ->assertOk()
             ->assertJsonPath('data.status', 'rejected');
    }

    public function test_approve_without_documents_submitted_returns_404(): void
    {
        [, $reservation] = $this->bookedGuest();
        $approver = $this->makeApprover();

        $this->actingAs($approver, 'users')
             ->patchJson("/api/cms/check-in-approvals/{$reservation->uuid}/approve", ['status' => 'approved'])
             ->assertStatus(404);
    }

    public function test_permission_gate_blocks_unpermitted_approver(): void
    {
        [$guest, $reservation] = $this->bookedGuest();
        $this->actingAs($guest, 'guests')
             ->postJson('/api/pre-arrival/documents', [
                 'documents' => [['type' => 'passport', 'file' => UploadedFile::fake()->create('p.pdf', 100)]],
             ])->assertCreated();

        $user = User::factory()->create();

        $this->actingAs($user, 'users')
             ->patchJson("/api/cms/check-in-approvals/{$reservation->uuid}/approve", ['status' => 'approved'])
             ->assertForbidden();
    }
}
