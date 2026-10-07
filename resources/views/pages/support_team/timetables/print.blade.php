<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Timetable - {{ $ttr->name }} - {{ $ttr->year }}</title>
    <style>
        @page { size: A4 landscape; margin: 10mm; }
        body { font-family: Arial, Helvetica, sans-serif; color: #111; margin: 0; }
        h1 { font-size: 18px; margin: 0; text-align: center; }
        h2 { font-size: 14px; margin: 4px 0 12px; text-align: center; letter-spacing: 1px; }
        .sub { text-align: center; font-size: 11px; color: #444; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        th, td { border: 1.5px solid #111; text-align: center; vertical-align: middle; font-size: 11px; padding: 4px; }
        thead th { font-weight: 600; }
        .day { text-align: left; font-weight: bold; width: 95px; text-transform: uppercase; }
        .cell { height: 58px; text-transform: uppercase; }
        .break { width: 30px; background: #f2f2f2; padding: 0; }
        .break span { writing-mode: vertical-rl; letter-spacing: 6px; font-weight: bold; font-size: 13px; }
        .c0 { background: #dbeafe; } .c1 { background: #ffe4e6; } .c2 { background: #ecfccb; } .c3 { background: #fef3c7; } .c4 { background: #ede9fe; }
        .c5 { background: #ccfbf1; } .c6 { background: #ffedd5; } .c7 { background: #e0e7ff; } .c8 { background: #d1fae5; } .c9 { background: #fae8ff; }
        * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        @media screen { body { padding: 24px; } }
    </style>
</head>
<body>
    <h1>{{ strtoupper($s['system_name'] ?? config('app.name')) }}</h1>
    <div class="sub">{{ $s['address'] ?? '' }}</div>
    <h2>TIMETABLE — {{ strtoupper(optional($my_class)->name) }}{{ $exam ? ' · '.strtoupper($exam->name) : '' }} ({{ $ttr->year }})</h2>

    @if(count($rows) && count($slots))
        @php $n = 0; @endphp
        <table>
            <thead>
                <tr>
                    <th class="day" rowspan="2">{{ $isExam ? 'DATE' : 'DAY' }}</th>
                    @foreach($slots as $sl)
                        <th @if($sl['label']) class="break" @endif>{{ $sl['from'] }}<br>{{ $sl['to'] }}</th>
                    @endforeach
                </tr>
                <tr>
                    @foreach($slots as $sl)
                        <th @if($sl['label']) class="break" @endif>{{ $sl['label'] ? '' : ++$n }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach($rows as $r)
                    <tr>
                        <td class="day">
                            @if($isExam)
                                {{ date('l', strtotime($r['day'])) }}<br><span style="font-weight:normal">{{ date('d/m/Y', strtotime($r['day'])) }}</span>
                            @else
                                {{ $r['day'] }}
                            @endif
                        </td>
                        @foreach($r['cells'] as $c)
                            @if($c['kind'] === 'break')
                                <td class="break" rowspan="{{ $c['rowspan'] }}"><span>{{ strtoupper($c['text']) }}</span></td>
                            @else
                                <td class="cell {{ $c['kind'] === 'subject' ? 'c'.($c['colour'] % 10) : '' }}" colspan="{{ $c['colspan'] }}">{{ $c['text'] }}</td>
                            @endif
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <p style="text-align:center">Nothing scheduled yet.</p>
    @endif

    <script>window.addEventListener('load', function () { window.print(); });</script>
</body>
</html>
