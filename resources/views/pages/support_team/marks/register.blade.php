@php
    // The subject's assessment register in the school's layout (App\Support\Assessment), with the scores entered so far.
    $parts = collect($scheme['parts']);
    $examKey = $scheme['exam'];
    $groups = $scheme['groups'] ?? null;
    $w = $scheme['weights'] ?? null;
    $classParts = $parts->where('key', '!=', $examKey)->values();
    $examPart = $parts->firstWhere('key', $examKey);
    $logoSrc = null;
    if ($logo && extension_loaded('gd')) {
        if (preg_match('#/storage/(.+)$#', $logo, $m)) $lp = public_path('storage/'.ltrim($m[1], '/'));
        elseif (preg_match('#^https?://[^/]+/(.+)$#', $logo, $m)) $lp = public_path($m[1]);
        else $lp = null;
        $logoSrc = $lp && is_file($lp) ? $lp : null;
    }
    $cell = function ($v) { return $v === null || $v === '' ? '' : $v; };
@endphp
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $scheme['title'] ?? 'Assessment register' }}</title>
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 9px; color: #111; margin: 16px 18px; }
        .top { text-align: center; }
        .school { font-size: 15px; font-weight: bold; }
        .title { font-size: 12px; font-weight: bold; color: #b42318; margin-top: 2px; }
        .info { width: 100%; margin: 8px 0 6px; font-size: 9.5px; }
        .info td { padding: 2px 4px; }
        .info b { color: #1f3a8a; }
        table.reg { width: 100%; border-collapse: collapse; }
        .reg th, .reg td { border: 1px solid #555; padding: 3px 2px; text-align: center; }
        .reg th { font-size: 8px; }
        .reg td.name { text-align: left; padding-left: 4px; white-space: nowrap; }
        .sub { background: #f2f2f2; font-weight: bold; }
    </style>
</head>
<body>
    <div class="top">
        @if($logoSrc)<img src="{{ $logoSrc }}" style="height: 44px; float: left;">@endif
        <div class="school">{{ strtoupper($school) }}</div>
        <div class="title">{{ $scheme['title'] ?? 'ASSESSMENT REGISTER' }}</div>
    </div>
    <table class="info">
        <tr>
            <td><b>SUBJECT:</b> {{ optional($subject)->name }}</td>
            <td><b>TEACHER:</b> {{ optional(optional($subject)->teacher)->name }}</td>
            <td><b>TERM:</b> {{ $exam ? strip_tags(\App\Helpers\Mk::getSuffix($exam->term)) : '' }}</td>
            <td><b>YEAR:</b> {{ optional($exam)->year }}</td>
        </tr>
        <tr>
            <td><b>GRADE:</b> {{ $class }}</td>
            <td><b>NO. ON ROLL:</b> {{ $roll }}</td>
            <td><b>BOYS:</b> {{ $boys }}</td>
            <td><b>GIRLS:</b> {{ $girls }}</td>
        </tr>
    </table>

    <table class="reg">
        <thead>
            @if($groups)
                <tr>
                    <th rowspan="2" style="width: 18px;">No.</th>
                    <th rowspan="2">Name of Student</th>
                    @foreach($groups as $gk => $glabel)
                        <th colspan="{{ $classParts->where('group', $gk)->count() }}">{{ strtoupper($glabel) }}</th>
                        <th rowspan="2">TOTAL</th>
                    @endforeach
                    <th rowspan="2">TOTAL<br>100%</th>
                    @if($w)<th rowspan="2">{{ $w['class'] * 100 }}%</th>@endif
                    <th rowspan="2">{{ strtoupper($examPart['label']) }}<br>{{ $examPart['max'] }}%</th>
                    @if($w)<th rowspan="2">{{ $w['exam'] * 100 }}%</th>@endif
                    <th rowspan="2">Total<br>{{ $w ? ($w['class'] * 100).'%+'.($w['exam'] * 100).'%' : '' }}</th>
                </tr>
                <tr>
                    @foreach($groups as $gk => $glabel)
                        @foreach($classParts->where('group', $gk) as $p)
                            <th>{{ $p['label'] }}<br>{{ $p['max'] }}</th>
                        @endforeach
                    @endforeach
                </tr>
            @else
                <tr>
                    <th style="width: 18px;">No.</th>
                    <th>Name of Student</th>
                    @foreach($parts as $p)<th>{{ $p['label'] }}<br>{{ $p['max'] }}</th>@endforeach
                    <th>Total</th>
                </tr>
            @endif
        </thead>
        <tbody>
            @foreach($marks as $i => $mk)
                @php
                    $sc = \App\Support\Assessment::scores($mk);
                    $sum = function ($keys) use ($sc) { $v = collect($keys)->map(function ($k) use ($sc) { return $sc[$k]; })->filter(function ($x) { return $x !== null; }); return $v->count() ? $v->sum() : null; };
                    $classTotal = $sum($classParts->pluck('key'));
                @endphp
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td class="name">{{ optional($mk->user)->name }}</td>
                    @if($groups)
                        @foreach($groups as $gk => $glabel)
                            @foreach($classParts->where('group', $gk) as $p)<td>{{ $cell($sc[$p['key']]) }}</td>@endforeach
                            <td class="sub">{{ $cell($sum($classParts->where('group', $gk)->pluck('key'))) }}</td>
                        @endforeach
                        <td class="sub">{{ $cell($classTotal) }}</td>
                        @if($w)<td>{{ $classTotal === null ? '' : round($classTotal * $w['class']) }}</td>@endif
                        <td>{{ $cell($sc[$examKey]) }}</td>
                        @if($w)<td>{{ $sc[$examKey] === null ? '' : round($sc[$examKey] * $w['exam']) }}</td>@endif
                        <td class="sub">{{ $cell($mk->$tex) }}</td>
                    @else
                        @foreach($parts as $p)<td>{{ $cell($sc[$p['key']]) }}</td>@endforeach
                        <td class="sub">{{ $cell($mk->$tex) }}</td>
                    @endif
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
