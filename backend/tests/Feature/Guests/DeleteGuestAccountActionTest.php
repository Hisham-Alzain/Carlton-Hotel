<?php

namespace Tests\Feature\Guests;

use App\Actions\Guest\DeleteGuestAccountAction;
use App\Contracts\FirebaseServiceInterface;
use App\Enums\ConversationStatus;
use App\Enums\FolioStatus;
use App\Enums\GuestAccountStatus;
use App\Models\Conversation;
use App\Models\DeviceToken;
use App\Models\EventInquiry;
use App\Models\Folio;
use App\Models\FolioItem;
use App\Models\Guest;
use App\Models\GuestDocument;
use App\Models\GuestNote;
use App\Models\GuestNotification;
use App\Models\Message;
use App\Models\OtpCode;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\Review;
use App\Models\ServiceBooking;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\RecordsRowLocks;
use Tests\Support\FakeFirebaseService;
use Tests\TestCase;

/**
 * Phase 9.1 (D-05, D-08, D-09): the anonymize / retain / delete matrix of
 * DeleteGuestAccountAction, one test per row group.
 *
 * After-commit work: RefreshDatabase wraps each test in a transaction, and
 * Laravel runs `DB::afterCommit` callbacks when the action's own (outermost
 * application-level) transaction commits — so the file deletions and the
 * Firestore re-mirror have happened by the time `handle()` returns.
 */
class DeleteGuestAccountActionTest extends TestCase
{
    use RecordsRowLocks;
    use RefreshDatabase;

    private const OLD_PHONE = '+963944111222';
    private const NEW_PHONE = '+963944333444';
    private const OLD_EMAIL = 'erase.me.first@example.com';
    private const NEW_EMAIL = 'erase.me.second@example.com';
    private const FIRST     = 'Zenobiaqx';
    private const LAST      = 'Palmyrenkq';

    private Guest $guest;
    private FakeFirebaseService $firebase;
    private Reservation $checkedOut;
    private Reservation $cancelled;
    private Folio $folio;
    private GuestDocument $keptDoc;
    private GuestDocument $droppedDoc;
    private Conversation $conversation;
    private Message $guestMessage;
    private Message $staffMessage;
    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->firebase = new FakeFirebaseService();
        $this->app->instance(FirebaseServiceInterface::class, $this->firebase);
        $this->furnish();
    }

    private function furnish(): void
    {
        $this->staff = User::factory()->create();
        $this->guest = Guest::factory()->create([
            'phone' => self::OLD_PHONE, 'email' => self::OLD_EMAIL,
            'first_name' => self::FIRST, 'last_name' => self::LAST, 'name' => self::FIRST.' '.self::LAST,
            'preferred_locale' => 'fr',
        ]);
        // Earlier profile edits, so activity_log carries old and new identifiers.
        $this->guest->update(['phone' => self::NEW_PHONE, 'email' => self::NEW_EMAIL]);

        $this->guest->createToken('phone-a');
        $this->guest->createToken('phone-b');
        DeviceToken::factory()->count(2)->create(['guest_id' => $this->guest->id]);
        GuestNotification::factory()->count(2)->create(['guest_id' => $this->guest->id]);
        GuestNote::factory()->create(['guest_id' => $this->guest->id, 'user_id' => $this->staff->id]);
        OtpCode::factory()->create(['identifier' => self::NEW_PHONE]);
        OtpCode::factory()->emailChannel()->create(['identifier' => self::NEW_EMAIL]);
        OtpCode::factory()->create(['identifier' => '+963944999000']);

        $this->checkedOut = Reservation::factory()->checkedOut()->create([
            'guest_id' => $this->guest->id, 'last_name' => self::LAST, 'phone' => self::NEW_PHONE,
        ]);
        $this->folio = Folio::factory()->create([
            'reservation_id' => $this->checkedOut->id, 'status' => FolioStatus::SETTLED, 'settled_at' => now(),
        ]);
        FolioItem::factory()->create(['folio_id' => $this->folio->id]);
        Payment::factory()->create(['payable_type' => Reservation::class, 'payable_id' => $this->checkedOut->id]);

        $this->cancelled = Reservation::factory()->cancelled()->create([
            'guest_id' => $this->guest->id, 'last_name' => self::LAST, 'phone' => self::NEW_PHONE,
        ]);

        Storage::disk('public')->put('guest-documents/kept.jpg', 'x');
        Storage::disk('public')->put('guest-documents/dropped.jpg', 'x');
        $this->keptDoc = GuestDocument::factory()->create([
            'guest_id' => $this->guest->id, 'reservation_id' => $this->checkedOut->id, 'file_path' => 'guest-documents/kept.jpg',
        ]);
        $this->droppedDoc = GuestDocument::factory()->create([
            'guest_id' => $this->guest->id, 'reservation_id' => $this->cancelled->id, 'file_path' => 'guest-documents/dropped.jpg',
        ]);

        $this->conversation = Conversation::factory()->create(['guest_id' => $this->guest->id]);
        Storage::disk('public')->put('chat-attachments/c/photo.jpg', 'x');
        $this->guestMessage = $this->message($this->guest, 'My room is 204, call me on '.self::NEW_PHONE, 'chat-attachments/c/photo.jpg');
        $this->staffMessage = $this->message($this->staff, 'We will call you shortly.', null);

        Review::factory()->create(['guest_id' => $this->guest->id]);
        Ticket::factory()->create(['guest_id' => $this->guest->id]);
        EventInquiry::factory()->create(['guest_id' => $this->guest->id]);
        ServiceBooking::factory()->create([
            'guest_id' => $this->guest->id, 'reservation_id' => $this->checkedOut->id,
            'scheduled_at' => now()->subDays(3), 'status' => 'completed',
        ]);
    }

    private function message(Guest|User $sender, string $body, ?string $attachment): Message
    {
        $message = new Message(['body' => $body, 'attachment_path' => $attachment]);
        $message->conversation()->associate($this->conversation);
        $message->sender()->associate($sender);
        $message->save();

        return $message;
    }

    private function erase(): array
    {
        return app(DeleteGuestAccountAction::class)->handle($this->guest);
    }

    /** @return array<string, string> table => md5 of all rows */
    private function hashes(): array
    {
        $out = [];
        foreach (['folios', 'folio_items', 'payments', 'reviews', 'tickets', 'event_inquiries', 'service_bookings', 'service_requests'] as $table) {
            $out[$table] = md5(json_encode(DB::table($table)->orderBy('id')->get()));
        }

        return $out;
    }

    public function test_returns_the_service_shape(): void
    {
        $this->assertSame(['data' => null, 'code' => 200], $this->erase());
    }

    public function test_guest_row_is_anonymized_not_deleted(): void
    {
        $this->erase();

        $guest = Guest::find($this->guest->id);
        $this->assertNotNull($guest);
        $this->assertSame(GuestAccountStatus::DELETED, $guest->account_status);
        $this->assertNotNull($guest->account_deleted_at);
        $this->assertSame($this->guest->uuid, $guest->uuid);
        $this->assertSame('fr', $guest->preferred_locale);
        foreach (['name', 'first_name', 'last_name', 'phone', 'phone_country', 'phone_verified_at', 'email',
            'email_verified_at', 'bed_type', 'pillow_type', 'floor_preference', 'preferences_other', 'preferences_updated_at'] as $column) {
            $this->assertNull($guest->getRawOriginal($column), "guests.{$column} should be null");
        }
    }

    public function test_access_is_revoked(): void
    {
        $this->erase();

        $this->assertSame(0, DB::table('personal_access_tokens')->where('tokenable_id', $this->guest->id)
            ->where('tokenable_type', Guest::class)->count());
        $this->assertSame(0, DeviceToken::where('guest_id', $this->guest->id)->count());
    }

    public function test_personal_rows_are_purged(): void
    {
        $this->erase();

        $this->assertSame(0, GuestNotification::where('guest_id', $this->guest->id)->count());
        $this->assertSame(0, GuestNote::where('guest_id', $this->guest->id)->count());
        $this->assertSame(0, OtpCode::whereIn('identifier', [self::NEW_PHONE, self::NEW_EMAIL])->count());
        $this->assertSame(1, OtpCode::where('identifier', '+963944999000')->count());
    }

    public function test_reservations_keep_identity_but_lose_the_phone(): void
    {
        $this->erase();

        foreach ([$this->checkedOut, $this->cancelled] as $reservation) {
            $fresh = $reservation->fresh();
            $this->assertSame($this->guest->id, $fresh->guest_id);
            $this->assertSame($reservation->booking_code, $fresh->booking_code);
            $this->assertSame(self::LAST, $fresh->last_name);
            $this->assertNull($fresh->phone);
        }
    }

    public function test_ledger_and_operational_records_are_untouched(): void
    {
        $before = $this->hashes();

        $this->erase();

        $this->assertSame($before, $this->hashes());
    }

    public function test_documents_are_kept_only_on_checked_out_stays_and_files_go_after_commit(): void
    {
        $this->erase();

        $this->assertNotNull(GuestDocument::find($this->keptDoc->id));
        $this->assertNull(GuestDocument::find($this->droppedDoc->id));
        Storage::disk('public')->assertExists('guest-documents/kept.jpg');
        Storage::disk('public')->assertMissing('guest-documents/dropped.jpg');
    }

    public function test_chat_is_closed_and_guest_messages_redacted(): void
    {
        $this->erase();

        $this->assertSame(ConversationStatus::CLOSED, $this->conversation->fresh()->status);
        $guestMessage = $this->guestMessage->fresh();
        $this->assertNull($guestMessage->body);
        $this->assertNull($guestMessage->attachment_path);
        $this->assertSame('We will call you shortly.', $this->staffMessage->fresh()->body);
        Storage::disk('public')->assertMissing('chat-attachments/c/photo.jpg');

        $this->assertCount(1, $this->firebase->mirrors);
        $mirror = $this->firebase->mirrors[0];
        $this->assertSame('chats', $mirror['collection']);
        $this->assertSame($this->guestMessage->uuid, $mirror['document']);
        $this->assertNull($mirror['data']['body']);
        $this->assertNull($mirror['data']['attachment_path']);
        $this->assertTrue($mirror['data']['redacted']);
    }

    public function test_activity_log_keeps_no_pii_and_records_the_deletion(): void
    {
        $this->erase();

        $rows = DB::table('activity_log')->get();
        foreach ($rows as $row) {
            $blob = $row->description.' '.$row->properties.' '.$row->attribute_changes;
            foreach ([self::OLD_PHONE, self::NEW_PHONE, self::OLD_EMAIL, self::NEW_EMAIL, self::FIRST, 'call me on'] as $needle) {
                $this->assertStringNotContainsString($needle, $blob, "activity_log #{$row->id} still holds PII");
            }
        }
        // The guest-subject rows also lose the last name (reservations keep it by design).
        DB::table('activity_log')->where('subject_type', Guest::class)->where('subject_id', $this->guest->id)->get()
            ->each(fn ($row) => $this->assertStringNotContainsString(self::LAST, (string) $row->attribute_changes));

        $entries = DB::table('activity_log')->where('description', 'guest.account_deleted')->get();
        $this->assertCount(1, $entries);
        $entry = $entries->first();
        $this->assertSame(Guest::class, $entry->subject_type);
        $this->assertSame($this->guest->id, (int) $entry->subject_id);
        $this->assertSame(Guest::class, $entry->causer_type);
        $this->assertSame($this->guest->id, (int) $entry->causer_id);
        $this->assertSame([
            'retained' => ['reservations' => 2, 'documents' => 1],
            'loyalty' => ['forfeited_points' => 0, 'expired_batches' => 0, 'closed_vouchers' => 0],
        ], json_decode($entry->properties, true));
    }

    public function test_the_scrub_itself_logs_nothing_but_the_one_entry(): void
    {
        $before = DB::table('activity_log')->count();

        $this->erase();

        $this->assertSame($before + 1, DB::table('activity_log')->count());
    }

    public function test_re_entry_is_a_no_op(): void
    {
        $this->erase();
        $count = DB::table('activity_log')->count();
        $mirrors = count($this->firebase->mirrors);

        $this->assertSame(['data' => null, 'code' => 200], app(DeleteGuestAccountAction::class)->handle($this->guest->fresh()));

        $this->assertSame($count, DB::table('activity_log')->count());
        $this->assertSame($mirrors, count($this->firebase->mirrors));
    }

    public function test_the_guest_row_is_locked_first(): void
    {
        $locked = $this->lockedSelects(fn () => $this->erase());

        $this->assertNotEmpty($locked);
        $this->assertStringContainsString('from "guests"', $locked[0]);
    }

    public function test_the_person_can_register_again(): void
    {
        $this->erase();

        $again = Guest::create(['phone' => self::NEW_PHONE, 'email' => self::NEW_EMAIL]);
        $this->assertNotSame($this->guest->id, $again->id);
    }
}
