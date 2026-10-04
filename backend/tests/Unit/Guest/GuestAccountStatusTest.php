<?php

namespace Tests\Unit\Guest;

use App\Enums\GuestAccountStatus;
use App\Exceptions\DomainException;
use App\Exceptions\GuestAccountDeletedException;
use App\Exceptions\GuestAccountDeletionBlockedException;
use App\Models\Guest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** Phase 9.1 D-05, D-11, D-20: account status column, enum, exceptions, keys. */
class GuestAccountStatusTest extends TestCase
{
    use RefreshDatabase;

    private const LOCALES = ['en', 'ar', 'fr', 'tr', 'es'];

    public function test_a_new_guest_is_active(): void
    {
        $guest = Guest::factory()->create()->fresh();

        $this->assertSame(GuestAccountStatus::ACTIVE, $guest->account_status);
        $this->assertNull($guest->account_deleted_at);
        $this->assertFalse($guest->isDeleted());
    }

    public function test_deleted_factory_state_is_anonymized(): void
    {
        $guest = Guest::factory()->deleted()->create()->fresh();

        $this->assertTrue($guest->isDeleted());
        $this->assertSame(GuestAccountStatus::DELETED, $guest->account_status);
        $this->assertNotNull($guest->account_deleted_at);
        $this->assertNull($guest->phone);
        $this->assertNull($guest->email);
        $this->assertNull($guest->first_name);
        $this->assertNull($guest->last_name);
    }

    public function test_active_accounts_scope_excludes_deleted(): void
    {
        $active = Guest::factory()->create();
        Guest::factory()->deleted()->create();

        $this->assertSame([$active->id], Guest::activeAccounts()->pluck('id')->all());
    }

    public function test_status_columns_are_not_mass_assignable(): void
    {
        $guest = (new Guest)->fill(['account_status' => 'deleted', 'account_deleted_at' => now()]);

        $this->assertSame(GuestAccountStatus::ACTIVE, $guest->account_status);
        $this->assertNull($guest->account_deleted_at);
    }

    public function test_account_status_is_indexed(): void
    {
        $names = array_column(Schema::getIndexes('guests'), 'name');

        $this->assertContains('guests_account_status_index', $names);
    }

    public function test_exceptions_report_code_and_status(): void
    {
        $blocked = new GuestAccountDeletionBlockedException('', ['reasons' => ['open_folio'], 'booking_codes' => []]);
        $deleted = new GuestAccountDeletedException('');

        $this->assertInstanceOf(DomainException::class, $blocked);
        $this->assertSame('guest_account_deletion_blocked', $blocked->errorCode());
        $this->assertSame(422, $blocked->statusCode());
        $this->assertSame('guest_account_deleted', $deleted->errorCode());
        $this->assertSame(422, $deleted->statusCode());
    }

    /** @return array<string, array{string}> */
    public static function locales(): array
    {
        return array_combine(self::LOCALES, array_map(fn ($l) => [$l], self::LOCALES));
    }

    #[DataProvider('locales')]
    public function test_exceptions_render_translated_messages(string $locale): void
    {
        Route::middleware('api')->get('/api/_test/guest-account/blocked', fn () => throw new GuestAccountDeletionBlockedException(
            '', ['reasons' => ['active_reservation'], 'booking_codes' => ['CARL-AAAA']],
        ));
        Route::middleware('api')->get('/api/_test/guest-account/deleted', fn () => throw new GuestAccountDeletedException(''));

        $blocked = $this->withHeader('Accept-Language', $locale)->getJson('/api/_test/guest-account/blocked')
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'guest_account_deletion_blocked')
            ->assertJsonPath('context.reasons', ['active_reservation']);
        $this->assertSame(trans('custom.errors.guest_account_deletion_blocked', [], $locale), $blocked->json('message'));
        $this->assertNotSame('custom.errors.guest_account_deletion_blocked', $blocked->json('message'));

        $deleted = $this->withHeader('Accept-Language', $locale)->getJson('/api/_test/guest-account/deleted')
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'guest_account_deleted');
        $this->assertSame(trans('custom.errors.guest_account_deleted', [], $locale), $deleted->json('message'));
        $this->assertNotSame('custom.errors.guest_account_deleted', $deleted->json('message'));
    }

    #[DataProvider('locales')]
    public function test_new_keys_exist_in_every_locale(string $locale): void
    {
        foreach (['custom.auth.account_deleted', 'custom.attributes.confirm'] as $key) {
            $this->assertNotSame($key, trans($key, [], $locale), "{$key} missing in {$locale}");
        }
    }
}
