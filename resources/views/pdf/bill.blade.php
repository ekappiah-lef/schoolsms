@php
    $next = !empty($invoice['next']);
    $span = $next ? 1 : 3;
    // Images in PDFs need the GD extension; without it the PDF is made without the logo.
    $logo = extension_loaded('gd') ? \App\Support\Notices::logoPath() : null;
    $money = function ($n) { return \App\Support\Money::fmt($n); };
@endphp
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $word }} · {{ $invoice['label'] }}</title>
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #1f2333; margin: 26px 32px; }
        .head td { vertical-align: middle; }
        .school { color: #1b0c80; font-size: 20px; font-weight: bold; letter-spacing: 0.5px; }
        .sub { font-size: 11px; color: #3a3d4d; }
        .title { margin-top: 6px; font-size: 13px; font-weight: bold; letter-spacing: 1px; }
        .rule { border-top: 2px solid #1b0c80; margin: 12px 0 14px; }
        .muted { color: #6b6f80; }
        table { width: 100%; border-collapse: collapse; }
        .lines th { text-align: left; font-size: 9.5px; text-transform: uppercase; color: #6b6f80; border-bottom: 1px solid #d9dbe3; padding: 7px 5px; }
        .lines td { border-bottom: 1px solid #eceef3; padding: 7px 5px; }
        .r { text-align: right; }
        .b { font-weight: bold; }
        .fwd { color: #b91c1c; }
        .total td { border-top: 2px solid #1f2333; border-bottom: 0; font-size: 13px; font-weight: bold; padding-top: 9px; }
    </style>
</head>
<body>
    <table class="head">
        <tr>
            <td style="width: 90px;">@if($logo)<img src="{{ $logo }}" style="max-height: 80px; max-width: 85px;">@endif</td>
            <td style="text-align: center;">
                <div class="school">{{ strtoupper($school) }}</div>
                @if($address)<div class="sub"><i>{{ ucwords($address) }}</i></div>@endif
                @if($phone || $email)<div class="sub">{{ collect([$phone, $email])->filter()->implode('  ·  ') }}</div>@endif
                <div class="title">{{ strtoupper($word) }} · {{ strtoupper($invoice['label']) }}</div>
            </td>
            <td style="width: 90px;"></td>
        </tr>
    </table>
    <div class="rule"></div>

    <table style="margin-bottom: 14px;">
        <tr>
            <td><span class="muted">Student</span><br><span class="b">{{ $student }}</span></td>
            @if($parentName)<td class="r"><span class="muted">Parent / guardian</span><br><span class="b">{{ $parentName }}</span></td>@endif
        </tr>
    </table>

    <table class="lines">
        <tr>
            <th>Item</th>
            <th class="r">Amount</th>
            @unless($next)<th class="r">Paid</th><th class="r">Balance</th>@endunless
        </tr>
        @foreach($invoice['table'] as $r)
            <tr class="{{ !empty($r['forward']) ? 'fwd' : '' }}">
                <td>{{ $r['label'] }}</td>
                <td class="r">{{ empty($r['credit']) ? $money($r['amount']) : '' }}</td>
                @unless($next)
                    <td class="r">{{ $r['paid'] ? $money($r['paid']) : '' }}</td>
                    <td class="r">{{ empty($r['credit']) ? $money($r['balance']) : 'Credit '.$money($r['credit']) }}</td>
                @endunless
            </tr>
        @endforeach
        @if(($invoice['credit'] ?? 0) > 0)
            <tr><td colspan="{{ $span }}">Credit</td><td class="r">GHS {{ $money($invoice['credit']) }}</td></tr>
        @endif
        <tr class="total"><td colspan="{{ $span }}">Total due</td><td class="r">GHS {{ $money($invoice['total']) }}</td></tr>
    </table>

    @if($instructions)
        <p class="b" style="margin: 22px 0 4px;">How to pay</p>
        <p style="margin: 0; white-space: pre-line;">{{ $instructions }}</p>
    @endif
</body>
</html>
