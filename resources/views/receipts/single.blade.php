@php
    // The PDF font has no cedi sign, so PDFs use the currency code.
    $money = function ($n) use ($pdf) { return ($pdf ? 'GHS ' : 'GH₵ ').number_format((int) $n); };
@endphp
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Receipt {{ $r['number'] }}</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; padding: {{ $pdf ? '0' : '32px 16px' }}; background: {{ $pdf ? '#fff' : '#f4f5fb' }}; font-family: {{ $pdf ? "'DejaVu Sans', sans-serif" : "Inter, Arial, sans-serif" }}; color: #131b2e; font-size: 12px; }
        .sheet { max-width: 520px; margin: 0 auto; background: #fff; padding: 28px; {{ $pdf ? '' : 'border-radius: 8px; box-shadow: 0 1px 3px rgba(19,27,46,.08);' }} }
        table { width: 100%; border-collapse: collapse; }
        .muted { color: #6b6a7b; }
        .label { font-size: 9px; letter-spacing: 1.2px; text-transform: uppercase; color: #8a8898; font-weight: bold; }
        .head td { vertical-align: top; }
        .school { font-size: 15px; font-weight: bold; }
        .rule { border-top: 1px solid #eceaf4; margin: 16px 0; }
        .rows td { padding: 7px 0; border-bottom: 1px solid #f1f0f6; }
        .rows td.r { text-align: right; }
        .paid { font-size: 22px; font-weight: bold; color: #006e4b; }
        .bal { font-weight: bold; color: {{ $r['balance'] > 0 ? '#ba1a1a' : '#006e4b' }}; }
        .stamp { display: inline-block; padding: 3px 8px; border-radius: 4px; font-size: 10px; font-weight: bold; background: {{ $r['balance'] > 0 ? '#fff4de' : '#d6faea' }}; color: {{ $r['balance'] > 0 ? '#92400e' : '#005338' }}; }
        .actions { max-width: 520px; margin: 0 auto 12px; text-align: right; }
        .actions a, .actions button { font: 500 13px Inter, Arial, sans-serif; background: #fff; border: 0; box-shadow: 0 0 0 1px #dcdbe6; border-radius: 6px; padding: 7px 12px; color: #131b2e; text-decoration: none; cursor: pointer; margin-left: 6px; }
        @media print { .actions { display: none; } body { background: #fff; padding: 0; } .sheet { box-shadow: none; } }
    </style>
</head>
<body>
@unless($pdf)
    <div class="actions">
        <a href="{{ route('receipts.pdf', [$r['kind'], \App\Helpers\Qs::hash($r['id'])]) }}">Download PDF</a>
        <button type="button" onclick="window.print()">Print</button>
    </div>
@endunless
<div class="sheet">
    <table class="head">
        <tr>
            <td style="width: 56px;">
                @if($pdf && $r['school']['logo_path'])
                    <img src="{{ $r['school']['logo_path'] }}" style="width: 48px;">
                @elseif(!$pdf && $r['school']['logo_url'])
                    <img src="{{ $r['school']['logo_url'] }}" style="width: 48px;">
                @endif
            </td>
            <td>
                <div class="school">{{ $r['school']['name'] }}</div>
                <div class="muted">{{ $r['school']['address'] }}</div>
                <div class="muted">{{ collect([$r['school']['phone'], $r['school']['email']])->filter()->implode(' · ') }}</div>
            </td>
            <td style="text-align: right;">
                <div class="label">Receipt</div>
                <div style="font-weight: bold; font-size: 13px;">{{ $r['number'] }}</div>
                <div class="muted">{{ optional($r['date'])->format('d/m/Y H:i') }}</div>
            </td>
        </tr>
    </table>

    <div class="rule"></div>

    <table>
        <tr>
            <td><div class="label">Received from / for</div><div style="font-weight: bold;">{{ $r['student']['name'] }}</div><div class="muted">{{ $r['student']['adm_no'] }} · {{ $r['student']['class'] }}</div></td>
            <td style="text-align: right;"><div class="label">Amount received</div><div class="paid">{{ $money($r['amount']) }}</div></td>
        </tr>
    </table>

    <div class="rule"></div>

    <table class="rows">
        <tr><td class="muted">Payment for</td><td class="r">{{ $r['item'] }}</td></tr>
        <tr><td class="muted">Category</td><td class="r">{{ $r['category'] }}</td></tr>
        <tr><td class="muted">Year</td><td class="r">{{ $r['year'] }}</td></tr>
        @if($r['ref'])<tr><td class="muted">Reference</td><td class="r">{{ $r['ref'] }}</td></tr>@endif
        <tr><td class="muted">Amount due</td><td class="r">{{ $money($r['owed']) }}</td></tr>
        <tr><td class="muted">Paid to date</td><td class="r">{{ $money($r['paid_to_date']) }}</td></tr>
        <tr><td style="font-weight: bold;">Balance</td><td class="r bal">{{ $r['balance'] > 0 ? $money($r['balance']) : 'Fully paid' }}</td></tr>
    </table>

    <div style="margin-top: 16px;"><span class="stamp">{{ $r['balance'] > 0 ? 'PART PAYMENT' : 'PAID IN FULL' }}</span></div>
    <p class="muted" style="margin-top: 18px; font-size: 10px;">Thank you. Fees paid are non-refundable unless the school states otherwise in writing. Keep this receipt for your records.</p>
</div>
</body>
</html>
