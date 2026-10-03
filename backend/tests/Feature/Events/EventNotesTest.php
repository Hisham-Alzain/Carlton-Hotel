<?php

namespace Tests\Feature\Events;

use App\Models\EventInquiry;
use App\Models\User;
use App\Services\Events\EventInquiryService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

/**
 * EVENT-03 (D-01, D-20): internal `staff_notes`; the guest's `notes` is never touched.
 */
class EventNotesTest extends TestCase
{
    use RefreshDatabase;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->token = User::factory()->create()->assignRole('events')->createToken('t')->plainTextToken;
    }

    private function patchNotes(EventInquiry $inquiry, array $body, ?string $token = null)
    {
        return $this->withToken($token ?? $this->token)
            ->patchJson("/api/cms/event-inquiries/{$inquiry->uuid}/notes", $body);
    }

    public function test_set_staff_notes_leaves_guest_notes(): void
    {
        $inquiry = EventInquiry::factory()->create(['notes' => 'Garden wedding brief']);

        $this->patchNotes($inquiry, ['staff_notes' => 'Call back Tuesday'])
            ->assertOk()
            ->assertJsonPath('message', 'Notes updated.')
            ->assertJsonPath('data.staff_notes', 'Call back Tuesday')
            ->assertJsonPath('data.notes', 'Garden wedding brief')
            ->assertJsonCount(5, 'data.checklist');

        $this->assertSame('Garden wedding brief', $inquiry->fresh()->notes);
    }

    public function test_null_clears_staff_notes(): void
    {
        $inquiry = EventInquiry::factory()->create(['staff_notes' => 'Old note']);

        $this->patchNotes($inquiry, ['staff_notes' => null])->assertOk()->assertJsonPath('data.staff_notes', null);

        $this->assertNull($inquiry->fresh()->staff_notes);
    }

    public static function invalidBodies(): array
    {
        return [
            'missing key' => [[]],
            'too long'    => [['staff_notes' => str_repeat('a', 5001)]],
            'array'       => [['staff_notes' => ['a']]],
        ];
    }

    #[DataProvider('invalidBodies')]
    public function test_invalid_bodies_are_422(array $body): void
    {
        $inquiry = EventInquiry::factory()->create();

        $this->patchNotes($inquiry, $body)
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_failed')
            ->assertJsonValidationErrors(['staff_notes']);
    }

    public function test_5000_chars_is_accepted(): void
    {
        $inquiry = EventInquiry::factory()->create();

        $this->patchNotes($inquiry, ['staff_notes' => str_repeat('a', 5000)])->assertOk();
    }

    public function test_allowed_on_a_cancelled_inquiry(): void
    {
        $inquiry = EventInquiry::factory()->cancelled()->create();

        $this->patchNotes($inquiry, ['staff_notes' => 'Post-mortem: lost to competitor'])
            ->assertOk()
            ->assertJsonPath('data.staff_notes', 'Post-mortem: lost to competitor');
    }

    public function test_activity_log_records_the_change(): void
    {
        $inquiry = EventInquiry::factory()->create();

        $this->patchNotes($inquiry, ['staff_notes' => 'Audited'])->assertOk();

        $activity = Activity::where('subject_type', EventInquiry::class)
            ->where('subject_id', $inquiry->id)->latest('id')->first();

        $this->assertNotNull($activity);
        // Spatie v5 keeps model diffs in `attribute_changes`.
        $this->assertSame('Audited', $activity->attribute_changes['attributes']['staff_notes'] ?? null);
    }

    public function test_patch_read_budget(): void
    {
        $inquiry = EventInquiry::factory()->create();

        $selects = 0;
        DB::listen(function ($event) use (&$selects): void {
            if (str_starts_with(strtolower(ltrim($event->sql)), 'select')) {
                $selects++;
            }
        });

        app(EventInquiryService::class)->updateStaffNotes($inquiry, 'x');

        $this->assertLessThanOrEqual(9, $selects, "notes PATCH ran {$selects} reads (D-30 cap 9)");
    }

    public function test_requires_a_token(): void
    {
        $inquiry = EventInquiry::factory()->create();

        $this->patchJson("/api/cms/event-inquiries/{$inquiry->uuid}/notes", ['staff_notes' => 'x'])->assertStatus(401);
    }

    public function test_events_view_alone_is_403(): void
    {
        $inquiry = EventInquiry::factory()->create();
        $this->app['auth']->forgetGuards();
        $viewer = User::factory()->create();
        $viewer->givePermissionTo('events.view');

        $this->patchNotes($inquiry, ['staff_notes' => 'x'], $viewer->createToken('t')->plainTextToken)->assertStatus(403);
        $this->assertNull($inquiry->fresh()->staff_notes);
    }
}
