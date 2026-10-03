<?php

namespace App\Http\Requests\Concerns;

/**
 * The `Idempotency-Key` header as a validated `idempotency_key` field (Phase 5
 * D-08, shared with the Phase 8 event deposit). The header always overwrites a
 * body field and becomes null when absent, blank or whitespace, so pair it with
 * `'idempotency_key' => ['required', 'string', 'max:64']` and merge
 * `idempotencyKeyMessages()` into `messages()`.
 */
trait ReadsIdempotencyKey
{
    public function prepareForValidation(): void
    {
        $key = trim((string) $this->header('Idempotency-Key', ''));
        $this->merge(['idempotency_key' => $key === '' ? null : $key]);
    }

    /** @return array<string, string> */
    protected function idempotencyKeyMessages(): array
    {
        return ['idempotency_key.required' => __('custom.errors.idempotency_key_required')];
    }
}
