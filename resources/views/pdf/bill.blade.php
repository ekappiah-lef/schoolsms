<html>
<head>
    <meta charset="utf-8">
    <title>{{ $word }} · {{ $invoice['label'] }}</title>
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #1f2333; margin: 30px 34px; }
        .head { border-bottom: 2px solid #1f2333; padding-bottom: 8px; margin-bottom: 16px; }
        .school { font-size: 17px; font-weight: bold; }
        .muted { color: #6b6f80; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; font-size: 9.5px; text-transform: uppercase; color: #6b6f80; border-bottom: 1px solid #d9dbe3; padding: 7px 5px; }
        td { border-bottom: 1px solid #eceef3; padding: 7px 5px; }
        .r { text-align: right; }
        .b { font-weight: bold; }
        .fwd { color: #b91c1c; }
        .total td { border-top: 2px solid #1f2333; border-bottom: 0; font-size: 13px; font-weight: bold; padding-top: 9px; }
    </style>
</head>
<body>
    @php($next = !empty($invoice['next']))
    @php($span = $next ? 1 : 3)
    <div class="head">
        <div class="school">{{ $school }}</div>
        <div class="muted">{{ $word }} · {{ $invoice['label'] }}</div>
    </div>
    <p style="margin: 0 0 4px;"><span class="b">{{ $student }}</span></p>
    @if($parentName)<p class="muted" style="margin: 0 0 14px;">{{ $parentName }}</p>@endif

    <table>
        <tr>
            <th>Item</th>
            <th class="r">Amount</th>
            @unless($next)<th class="r">Paid</th><th class="r">Balance</th>@endunless
        </tr>
        @foreach($invoice['table'] as $r)
            <tr class="{{ !empty($r['forward']) ? 'fwd' : '' }}">
                <td>{{ $r['label'] }}</td>
                <td class="r">{{ empty($r['credit']) ? \App\Support\Money::fmt($r['amount']) : '' }}</td>
                @unless($next)
                    <td class="r">{{ $r['paid'] ? \App\Support\Money::fmt($r['paid']) : '' }}</td>
                    <td class="r">{{ empty($r['credit']) ? \App\Support\Money::fmt($r['balance']) : 'Credit '.\App\Support\Money::fmt($r['credit']) }}</td>
                @endunless
            </tr>
        @endforeach
        @if(($invoice['credit'] ?? 0) > 0)
            <tr><td colspan="{{ $span }}">Credit</td><td class="r">GHS {{ \App\Support\Money::fmt($invoice['credit']) }}</td></tr>
        @endif
        <tr class="total"><td colspan="{{ $span }}">Total due</td><td class="r">GHS {{ \App\Support\Money::fmt($invoice['total']) }}</td></tr>
    </table>

    @if($instructions)
        <p class="b" style="margin: 22px 0 4px;">How to pay</p>
        <p style="margin: 0; white-space: pre-line;">{{ $instructions }}</p>
    @endif
    <p class="muted" style="margin-top: 22px; font-size: 9.5px;">All fees paid are non-refundable, except where the school states otherwise in writing.@if($phone) Questions? Call {{ $phone }}.@endif</p>
</body>
</html>
