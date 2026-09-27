<?php

namespace App\Http\Requests\Operations;

use App\Base\BaseRequest;
use App\Enums\ServiceBookingStatus;
use App\Enums\ServiceRequestStatus;
use App\Support\DepartureServiceProjection;
use App\Support\HotelClock;
use Closure;

/**
 * `GET /departure-services` query (Phase 6, D-21).
 *
 * `date` is a hotel-local Y-m-d within today ± 30 days (default today in the
 * service). `kind` and `status` accept a single value, a comma list, repeated
 * params or the `[in]` form; they are flattened to one list here so errors are
 * keyed by the param itself (`kind`, `status`), never `kind.0`.
 */
class IndexDepartureServicesRequest extends BaseRequest
{
    private const WINDOW_DAYS = 30;

    protected function prepareForValidation(): void
    {
        $merge = [];

        foreach (['kind', 'status'] as $key) {
            if ($this->has($key)) {
                $merge[$key] = $this->flatten($this->input($key));
            }
        }

        $this->merge($merge);
    }

    public function rules(): array
    {
        $today = HotelClock::today();

        return [
            'date'   => ['nullable', 'date_format:Y-m-d',
                'after_or_equal:' . $today->subDays(self::WINDOW_DAYS)->toDateString(),
                'before_or_equal:' . $today->addDays(self::WINDOW_DAYS)->toDateString()],
            'kind'   => ['nullable', 'array', $this->allOf(DepartureServiceProjection::KINDS)],
            'status' => ['nullable', 'array', $this->allOf(array_values(array_unique(array_merge(
                ServiceBookingStatus::values(),
                ServiceRequestStatus::values(),
            ))))],
        ];
    }

    /** @return list<string> */
    public function kinds(): array
    {
        return $this->validated('kind') ?? [];
    }

    /** @return list<string> */
    public function statuses(): array
    {
        return $this->validated('status') ?? [];
    }

    /** @param list<string> $allowed */
    private function allOf(array $allowed): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($allowed): void {
            foreach ((array) $value as $item) {
                if (! is_string($item) || ! in_array($item, $allowed, true)) {
                    $fail(__('custom.validation.in', ['attribute' => $attribute]));

                    return;
                }
            }
        };
    }

    /** @return list<mixed> */
    private function flatten(mixed $raw): array
    {
        $values = [];

        foreach ((array) $raw as $value) {
            if (is_array($value)) {
                $values = array_merge($values, $this->flatten($value));

                continue;
            }

            if (! is_scalar($value)) {
                $values[] = $value;

                continue;
            }

            foreach (explode(',', (string) $value) as $part) {
                if (($part = trim($part)) !== '') {
                    $values[] = $part;
                }
            }
        }

        return $values;
    }
}
