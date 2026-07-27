@php
    $reservation = $receipt['reservation'];
    $folio       = $receipt['folio'];
    $guestName   = trim("{$reservation->guest?->first_name} {$reservation->guest?->last_name}") ?: $reservation->guest?->name;
    $align       = $isRtl ? 'right' : 'left';
    $alignEnd    = $isRtl ? 'left' : 'right';
@endphp
<!-- Inline styles only: mpdf resolves no external assets. -->
<style>
    body  { font-family: sans-serif; font-size: 11pt; color: #1a1a1a; }
    h1    { font-size: 18pt; margin: 0 0 2mm; }
    .muted{ color: #666; font-size: 9pt; }
    table { width: 100%; border-collapse: collapse; margin-top: 6mm; }
    th    { text-align: {{ $align }}; border-bottom: 1px solid #999; padding: 2mm 1mm; font-size: 10pt; }
    td    { padding: 2mm 1mm; border-bottom: 1px solid #eee; }
    .num  { text-align: {{ $alignEnd }}; }
    .total td { border-top: 1px solid #999; border-bottom: none; font-weight: bold; }
    .meta td  { border: none; padding: 1mm 0; }
</style>

<h1>{{ __('custom.receipt.title') }}</h1>
<div class="muted">{{ __('custom.receipt.booking_code') }}: {{ $reservation->booking_code }}</div>

<table class="meta">
    <tr>
        <td>{{ __('custom.receipt.guest') }}</td>
        <td class="num">{{ $guestName }}</td>
    </tr>
    <tr>
        <td>{{ __('custom.receipt.check_in') }}</td>
        <td class="num">{{ $reservation->check_in?->toDateString() }}</td>
    </tr>
    <tr>
        <td>{{ __('custom.receipt.check_out') }}</td>
        <td class="num">{{ $reservation->check_out?->toDateString() }}</td>
    </tr>
    <tr>
        <td>{{ __('custom.receipt.nights') }}</td>
        <td class="num">{{ $reservation->nights() }}</td>
    </tr>
</table>

<table>
    <thead>
        <tr>
            <th>{{ __('custom.receipt.description') }}</th>
            <th class="num">{{ __('custom.receipt.amount') }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($folio->items as $item)
            <tr>
                {{-- folio_items.description is a plain string, not {en, ar} --}}
                <td>{{ $item->description }}</td>
                <td class="num">{{ number_format((float) $item->amount_usd, 2) }} USD</td>
            </tr>
        @endforeach
        <tr class="total">
            <td>{{ __('custom.receipt.total') }}</td>
            <td class="num">{{ number_format((float) $folio->total_usd, 2) }} USD</td>
        </tr>
    </tbody>
</table>

@if ($receipt['payments']->isNotEmpty())
    <table>
        <thead>
            <tr>
                <th>{{ __('custom.receipt.payment_method') }}</th>
                <th class="num">{{ __('custom.receipt.amount') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($receipt['payments'] as $payment)
                <tr>
                    <td>{{ $payment->method }}</td>
                    <td class="num">{{ number_format((float) $payment->amount_usd, 2) }} USD</td>
                </tr>
            @endforeach
            <tr class="total">
                <td>{{ __('custom.receipt.balance_due') }}</td>
                <td class="num">{{ number_format($receipt['balance_due_usd'], 2) }} USD</td>
            </tr>
        </tbody>
    </table>
@endif

<p class="muted">{{ __('custom.receipt.footer') }}</p>
