<?php

namespace Tests\Feature\Database;

use App\Enums\EventChecklistItem;
use App\Enums\EventDepositStatus;
use App\Models\DiningVenue;
use App\Models\EventInquiry;
use App\Models\EventInquiryChecklistItem;
use App\Models\Media;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Phase 8 storage (D-01..D-08): additive columns, the lazy checklist table,
 * `media.collection` and the table-reservation index.
 */
class EventsDiningSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_event_inquiries_has_staff_and_deposit_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('event_inquiries', [
            'notes', 'staff_notes', 'deposit_status', 'deposit_paid_at',
        ]));
        // D-05 / D-06: no copied amount, no quote or due-date columns.
        foreach (['deposit_usd', 'deposit_amount', 'staff_notes_updated_by'] as $column) {
            $this->assertFalse(Schema::hasColumn('event_inquiries', $column), $column);
        }

        $inquiry = EventInquiry::factory()->create(['notes' => 'Guest brief'])->fresh();

        $this->assertSame(EventDepositStatus::UNPAID, $inquiry->deposit_status);
        $this->assertNull($inquiry->deposit_paid_at);
        $this->assertNull($inquiry->staff_notes);
        $this->assertSame('Guest brief', $inquiry->notes);
    }

    public function test_checklist_table_shape(): void
    {
        $this->assertTrue(Schema::hasColumns('event_inquiry_checklist_items', [
            'id', 'uuid', 'event_inquiry_id', 'item', 'completed_at', 'completed_by', 'created_at', 'updated_at',
        ]));

        $inquiry = EventInquiry::factory()->create();
        $user    = User::factory()->create();

        $row = EventInquiryChecklistItem::factory()->for($inquiry, 'inquiry')->completed($user)->create([
            'item' => EventChecklistItem::CONTRACT,
        ]);
        $this->assertNotEmpty($row->uuid);
        $this->assertSame(EventChecklistItem::CONTRACT, $row->fresh()->item);

        try {
            DB::table('event_inquiry_checklist_items')->insert([
                'uuid' => 'dup-uuid', 'event_inquiry_id' => $inquiry->id, 'item' => 'contract',
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $this->fail('Duplicate (event_inquiry_id, item) was accepted.');
        } catch (UniqueConstraintViolationException) {
            // expected
        }

        // completed_by is restrictOnDelete: staff are deactivated, never deleted.
        try {
            DB::table('users')->where('id', $user->id)->delete();
            $this->fail('Deleting a referenced completed_by user was accepted.');
        } catch (QueryException) {
            // expected
        }

        DB::table('event_inquiry_checklist_items')->where('id', $row->id)->update(['completed_by' => null]);
        $inquiry->delete();
        $this->assertDatabaseCount('event_inquiry_checklist_items', 0);
    }

    public function test_media_collection_defaults_to_images(): void
    {
        $media = Media::factory()->create();

        $this->assertSame('images', $media->fresh()->collection);

        $column = collect(Schema::getColumns('media'))->firstWhere('name', 'collection');
        $this->assertNotNull($column);
        $this->assertFalse($column['nullable']);

        $index = collect(Schema::getIndexes('media'))
            ->first(fn ($i) => $i['columns'] === ['mediable_type', 'mediable_id', 'collection']);
        $this->assertNotNull($index);
    }

    public function test_venue_images_exclude_menu_rows(): void
    {
        $venue = DiningVenue::factory()->create();

        Media::factory()->attachedTo($venue)->count(2)->create();
        $menu = Media::factory()->attachedTo($venue)->create(['collection' => 'menu', 'mime_type' => 'application/pdf']);

        $venue->refresh();
        $this->assertCount(2, $venue->images);
        $this->assertFalse($venue->images->contains('id', $menu->id));
        $this->assertSame($menu->id, $venue->menuFile->id);

        $newer = Media::factory()->attachedTo($venue)->create(['collection' => 'menu', 'mime_type' => 'application/pdf']);
        $this->assertSame($newer->id, $venue->fresh()->menuFile->id);

        // An image uploaded after the menu must not hide it (constraint inside ofMany).
        Media::factory()->attachedTo($venue)->create();
        $this->assertSame($newer->id, $venue->fresh()->menuFile->id);
        $this->assertSame($newer->id, DiningVenue::with('menuFile')->find($venue->id)->menuFile->id);
        $this->assertCount(3, $venue->fresh()->images);
    }

    public function test_event_inquiry_relations(): void
    {
        $inquiry = EventInquiry::factory()->create();
        $user    = User::factory()->create();

        EventInquiryChecklistItem::factory()->for($inquiry, 'inquiry')->create(['item' => EventChecklistItem::BEO]);
        $this->assertCount(1, $inquiry->checklistItems);

        $completed = Payment::factory()->create([
            'payable_type' => EventInquiry::class, 'payable_id' => $inquiry->id,
            'method' => 'cash', 'amount_usd' => '500.00', 'recorded_by' => $user->id, 'status' => 'completed',
        ]);
        Payment::factory()->create([
            'payable_type' => EventInquiry::class, 'payable_id' => $inquiry->id,
            'method' => 'cash', 'amount_usd' => '900.00', 'recorded_by' => $user->id, 'status' => 'failed',
        ]);

        $inquiry->refresh();
        $this->assertCount(2, $inquiry->payments);
        $this->assertSame($completed->id, $inquiry->depositPayment->id);
        $this->assertSame($user->id, $inquiry->depositPayment->recorder->id);
    }

    public function test_service_bookings_has_scheduled_index(): void
    {
        $index = collect(Schema::getIndexes('service_bookings'))
            ->first(fn ($i) => $i['columns'] === ['bookable_type', 'scheduled_at']);

        $this->assertNotNull($index);
    }

    public function test_with_deposit_factory_state_is_ledger_backed(): void
    {
        $user    = User::factory()->create();
        $inquiry = EventInquiry::factory()->quoted()->withDeposit($user, '750.00')->create()->fresh();

        $this->assertSame(EventDepositStatus::PAID, $inquiry->deposit_status);
        $this->assertNotNull($inquiry->deposit_paid_at);
        $this->assertSame('750.00', (string) $inquiry->depositPayment->amount_usd);
        $this->assertSame(EventInquiry::class, $inquiry->depositPayment->payable_type);
        $this->assertSame($user->id, (int) $inquiry->depositPayment->recorded_by);
    }
}
