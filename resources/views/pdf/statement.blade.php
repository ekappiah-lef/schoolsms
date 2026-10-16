<html>
<head>
    <meta charset="utf-8">
    <title>Statement · {{ $period }}</title>
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 10.5px; color: #1f2333; margin: 26px 30px; }
        .head { border-bottom: 2px solid #1f2333; padding-bottom: 8px; margin-bottom: 14px; }
        .school { font-size: 16px; font-weight: bold; }
        .muted { color: #6b6f80; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; font-size: 9.5px; text-transform: uppercase; color: #6b6f80; border-bottom: 1px solid #d9dbe3; padding: 6px 4px; }
        td { border-bottom: 1px solid #eceef3; padding: 5px 4px; }
        .r { text-align: right; }
        .b { font-weight: bold; }
        .sum td { border: 1px solid #d9dbe3; padding: 7px; text-align: center; width: 25%; }
        .sum .n { display: block; font-size: 13px; font-weight: bold; }
    </style>
</head>
<body>
    <div class="head">
        <div class="school">{{ $school }}</div>
        <div class="muted">Statement · {{ $period }}</div>
    </div>
    <p style="margin: 0 0 4px;"><span class="b">{{ $parent }}</span></p>
    <p class="muted" style="margin: 0 0 12px;">{{ implode(', ', $children) }}</p>

    <table class="sum">
        <tr>
            <td><span class="n">GHS {{ \App\Support\Money::fmt($st['opening']) }}</span>Opening balance</td>
            <td><span class="n">GHS {{ \App\Support\Money::fmt($st['billed']) }}</span>Billed</td>
            <td><span class="n">GHS {{ \App\Support\Money::fmt($st['paid']) }}</span>Paid</td>
            <td><span class="n">GHS {{ \App\Support\Money::fmt($st['closing']) }}</span>Closing balance</td>
        </tr>
    </table>
    <div style="height: 14px;"></div>

    <table>
        <tr>
            <th>Date</th>
            @if($many)<th>Child</th>@endif
            <th>Details</th>
            <th class="r">Billed</th>
            <th class="r">Paid</th>
            <th class="r">Balance</th>
        </tr>
        <tr>
            <td colspan="{{ $many ? 5 : 4 }}">Opening balance</td>
            <td class="r b">{{ \App\Support\Money::fmt($st['opening']) }}</td>
        </tr>
        @foreach($st['rows'] as $r)
            <tr>
                <td>{{ \Carbon\Carbon::parse($r['date'])->format('d/m/Y') }}</td>
                @if($many)<td>{{ $r['student'] }}</td>@endif
                <td>{{ $r['text'] }}@if($r['ref']) <span class="muted">{{ $r['ref'] }}</span>@endif</td>
                <td class="r">{{ $r['debit'] ? \App\Support\Money::fmt($r['debit']) : '' }}</td>
                <td class="r">{{ $r['credit'] ? \App\Support\Money::fmt($r['credit']) : '' }}</td>
                <td class="r b">{{ \App\Support\Money::fmt($r['balance']) }}</td>
            </tr>
        @endforeach
        <tr>
            <td colspan="{{ $many ? 5 : 4 }}" class="b">Closing balance</td>
            <td class="r b">{{ \App\Support\Money::fmt($st['closing']) }}</td>
        </tr>
    </table>
</body>
</html>
