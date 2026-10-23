@php
    // The PDF font has no cedi sign, so PDFs use the currency code.
    $money = function ($n) use ($pdf) { return ($pdf ? 'GHS ' : 'GH₵ ').\App\Support\Money::fmt($n); };
    $expense = $v['type'] === 'expense';
@endphp
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $v['number'] }}</title>
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
        .amount { font-size: 22px; font-weight: bold; color: {{ $expense ? '#ba1a1a' : '#006e4b' }}; }
        .sign td { padding-top: 38px; width: 50%; }
        .sign .line { border-top: 1px solid #131b2e; margin-right: 18px; padding-top: 4px; }
        .actions { max-width: 520px; margin: 0 auto 12px; text-align: right; }
        .actions a, .actions button { font: 500 13px Inter, Arial, sans-serif; background: #fff; border: 0; box-shadow: 0 0 0 1px #dcdbe6; border-radius: 6px; padding: 7px 12px; color: #131b2e; text-decoration: none; cursor: pointer; margin-left: 6px; }
        @media print { .actions { display: none; } body { background: #fff; padding: 0; } .sheet { box-shadow: none; } }
    </style>
</head>
<body>
@unless($pdf)
    <div class="actions">
        <a href="{{ $v['pdf_url'] }}">Download PDF</a>
        <button type="button" onclick="window.print()">Print</button>
    </div>
@endunless
<div class="sheet">
    <table class="head">
        <tr>
            <td style="width: 56px;">
                @if($pdf && $v['school']['logo_path'])
                    <img src="{{ $v['school']['logo_path'] }}" style="width: 48px;">
                @elseif(!$pdf && $v['school']['logo_url'])
                    <img src="{{ $v['school']['logo_url'] }}" style="width: 48px;">
                @endif
            </td>
            <td>
                <div class="school">{{ $v['school']['name'] }}</div>
                <div class="muted">{{ $v['school']['address'] }}</div>
                <div class="muted">{{ collect([$v['school']['phone'], $v['school']['email']])->filter()->implode(' · ') }}</div>
            </td>
            <td style="text-align: right;">
                <div class="label">{{ $expense ? 'Payment voucher' : 'Receipt' }}</div>
                <div style="font-weight: bold; font-size: 13px;">{{ $v['number'] }}</div>
                <div class="muted">{{ $v['date']->format('d/m/Y') }}</div>
            </td>
        </tr>
    </table>

    <div class="rule"></div>

    <table>
        <tr>
            <td><div class="label">{{ $expense ? 'Paid for' : 'Received for' }}</div><div style="font-weight: bold;">{{ $v['category'] }}</div>@if($v['description'])<div class="muted">{{ $v['description'] }}</div>@endif</td>
            <td style="text-align: right;"><div class="label">{{ $expense ? 'Amount paid' : 'Amount received' }}</div><div class="amount">{{ $money($v['amount']) }}</div></td>
        </tr>
    </table>

    <div class="rule"></div>

    <table class="rows">
        @if($v['method'])<tr><td class="muted">{{ $expense ? 'Paid by' : 'Received by' }}</td><td class="r">{{ $v['method'] }}</td></tr>@endif
        @if($v['reference'])<tr><td class="muted">Transaction ID</td><td class="r">{{ $v['reference'] }}</td></tr>@endif
        @if($v['by'])<tr><td class="muted">Recorded by</td><td class="r">{{ $v['by'] }}</td></tr>@endif
    </table>

    <table class="sign">
        <tr>
            <td><div class="line muted">{{ $expense ? 'Received by' : 'Paid by' }}</div></td>
            <td><div class="line muted">Approved by</div></td>
        </tr>
    </table>
</div>
</body>
</html>
