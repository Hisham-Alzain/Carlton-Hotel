<?php

namespace App\Services\Currency;

use App\Actions\Currency\RecordExchangeRateAction;
use App\Base\BaseService;
use App\Filters\ExchangeRateFilter;
use App\Models\ExchangeRate;
use App\Models\User;
use App\Support\CurrencyConfig;

/**
 * Display exchange rates (Phase 9.1, D-14..D-18). No server cache (D-18): the
 * board is one latest-per-currency query (plus the eager `setBy` for staff).
 */
class ExchangeRateService extends BaseService
{
    protected string $model = ExchangeRate::class;

    protected ?string $filter = ExchangeRateFilter::class;

    protected array $with = ['setBy:id,uuid,name'];

    public function __construct(private readonly RecordExchangeRateAction $record) {}

    /**
     * One entry per configured currency, in config order; a currency with no
     * row yet carries a null `rate` (D-16). Shaped by ExchangeRateBoardResource.
     */
    public function board(bool $withStaffFields): array
    {
        $codes = CurrencyConfig::codes();

        $rows = ExchangeRate::latestPerCurrency($codes)
            ->when($withStaffFields, fn ($q) => $q->with($this->with))
            ->get()
            ->keyBy('currency');

        return [
            'data' => [
                'base' => CurrencyConfig::base(),
                'stale_after_hours' => CurrencyConfig::staleAfterHours(),
                'staff' => $withStaffFields,
                'rates' => array_map(fn (string $code) => [
                    'currency' => $code,
                    'display_decimals' => CurrencyConfig::displayDecimals($code),
                    'row' => $rows->get($code),
                ], $codes),
            ],
            'code' => 200,
        ];
    }

    /** Newest first (D-16). */
    public function history(array $params, ?int $perPage): array
    {
        $query = $this->query()->orderByDesc('id');
        $this->makeFilter($params)?->apply($query);

        return ['data' => $query->paginate($this->resolvePerPage($perPage)), 'code' => 200];
    }

    public function record(array $data, User $actor): array
    {
        return $this->record->handle($data, $actor);
    }
}
