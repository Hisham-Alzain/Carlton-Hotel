<?php

namespace Tests\Unit\Loyalty;

use App\Actions\Folio\GenerateFolioAction;
use App\Actions\Loyalty\EarnLoyaltyPointsAction;
use App\Actions\Loyalty\ReverseLoyaltyForFolioAction;
use App\Enums\LoyaltyEntryType;
use App\Models\Folio;
use App\Models\FolioItem;
use App\Models\Guest;
use App\Models\LoyaltyLedgerEntry;
use App\Models\Reservation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsLoyaltyFixtures;
use Tests\Concerns\RecordsRowLocks;
use Tests\TestCase;

/**
 * Phase 10 LOY-18 (Q1, Q2, M-6): the folio clawback seam the future folio
 * refund flow will call. Today only the reservation cancel reaches it.
 */
class ReverseLoyaltyForFolioActionTest extends TestCase
{
    use BuildsLoyaltyFixtures;
    use RecordsRowLocks;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureLoyalty(['earn_rate' => '1.0000']);
    }

    /** A folio with a 300-point stay earn and a 50-point service earn. */
    private function earnedFolio(?Guest $guest = null): Folio
    {
        $reservation = Reservation::factory()->confirmed()->create([
            'guest_id' => ($guest ?? Guest::factory()->create())->id,
            'total_usd' => '300.00',
        ]);
        $folio = app(GenerateFolioAction::class)->handle($reservation)['data'];
        FolioItem::factory()->create(['folio_id' => $folio->id, 'source_type' => 'service_request', 'amount_usd' => '50.00']);

        DB::transaction(fn () => app(EarnLoyaltyPointsAction::class)->handle(
            Folio::query()->whereKey($folio->id)->lockForUpdate()->firstOrFail(),
        ));

        return $folio->refresh();
    }

    public function test_every_earn_entry_of_the_folio_is_clawed_back(): void
    {
        $folio = $this->earnedFolio();
        $this->assertSame(2, LoyaltyLedgerEntry::where('type', LoyaltyEntryType::EARN->value)->count());

        $result = app(ReverseLoyaltyForFolioAction::class)->handle($folio);

        $this->assertSame(200, $result['code']);
        $this->assertCount(2, $result['data']);
        $this->assertSame([-300, -50], collect($result['data'])->pluck('points')->sort()->values()->all());
        $this->assertSame(
            2,
            LoyaltyLedgerEntry::where('type', LoyaltyEntryType::CLAWBACK->value)->where('folio_id', $folio->id)->count(),
        );
    }

    public function test_a_second_call_returns_the_same_entries_and_writes_nothing(): void
    {
        $folio = $this->earnedFolio();
        $first = app(ReverseLoyaltyForFolioAction::class)->handle($folio);
        $before = $this->loyaltyRowCounts();

        $second = app(ReverseLoyaltyForFolioAction::class)->handle($folio);

        $this->assertSame($before, $this->loyaltyRowCounts());
        $this->assertEqualsCanonicalizing(
            collect($first['data'])->pluck('id')->all(),
            collect($second['data'])->pluck('id')->all(),
        );
    }

    public function test_a_folio_without_earnings_returns_an_empty_list(): void
    {
        $reservation = Reservation::factory()->confirmed()->create(['total_usd' => '300.00']);
        $folio = app(GenerateFolioAction::class)->handle($reservation)['data'];
        $before = $this->loyaltyRowCounts();

        $this->assertSame(['data' => [], 'code' => 200], app(ReverseLoyaltyForFolioAction::class)->handle($folio));
        $this->assertSame($before, $this->loyaltyRowCounts());
    }

    public function test_a_folio_whose_reservation_has_no_guest_returns_an_empty_list(): void
    {
        $reservation = Reservation::factory()->confirmed()->create(['guest_id' => null, 'total_usd' => '300.00']);
        $folio = app(GenerateFolioAction::class)->handle($reservation)['data'];
        $before = $this->loyaltyRowCounts();

        $this->assertSame(['data' => [], 'code' => 200], app(ReverseLoyaltyForFolioAction::class)->handle($folio));
        $this->assertSame($before, $this->loyaltyRowCounts());
    }

    public function test_it_locks_the_folio_then_the_guest_then_the_batches(): void
    {
        $folio = $this->earnedFolio();

        $locked = $this->lockedSelects(fn () => app(ReverseLoyaltyForFolioAction::class)->handle($folio));

        $positions = [];
        foreach (['folios', 'guests', 'loyalty_earn_batches'] as $table) {
            $position = null;
            foreach ($locked as $index => $sql) {
                if (str_contains($sql, 'from "'.$table.'"')) {
                    $position = $index;
                    break;
                }
            }
            $this->assertNotNull($position, "no `for update` select on {$table}; saw:\n".implode("\n", $locked));
            $positions[] = $position;
        }

        $sorted = $positions;
        sort($sorted);
        $this->assertSame($sorted, $positions, 'lock order must be folios -> guests -> loyalty_earn_batches');
    }

    public function test_the_source_names_the_future_refund_flow_that_will_call_it(): void
    {
        $source = file_get_contents(app_path('Actions/Loyalty/ReverseLoyaltyForFolioAction.php'));

        $this->assertStringContainsString('TODO(refund flow)', $source);
    }
}
