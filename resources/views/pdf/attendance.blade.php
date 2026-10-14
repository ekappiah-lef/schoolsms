@php
    // Logo read from disk for the PDF (only when the image library is available).
    $logoSrc = null;
    if (!empty($logo) && extension_loaded('gd')) {
        if (preg_match('#/storage/(.+)$#', $logo, $m)) $p = public_path('storage/'.ltrim($m[1], '/'));
        elseif (preg_match('#^https?://[^/]+/(.+)$#', $logo, $m)) $p = public_path($m[1]);
        else $p = null;
        $logoSrc = $p && is_file($p) ? $p : null;
    }
@endphp
<html>
<head>
    <meta charset="utf-8">
    <title>Attendance · {{ $student }}</title>
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #1f2333; margin: 28px 34px; }
        .head { border-bottom: 2px solid #1f2333; padding-bottom: 10px; margin-bottom: 16px; }
        .school { font-size: 16px; font-weight: bold; }
        h1 { font-size: 13px; margin: 2px 0 0; font-weight: normal; color: #555; }
        .who { font-size: 14px; font-weight: bold; margin: 0; }
        .muted { color: #6b6f80; }
        table { width: 100%; border-collapse: collapse; }
        .stats td { border: 1px solid #d9dbe3; padding: 8px; text-align: center; width: 25%; }
        .stats .n { font-size: 16px; font-weight: bold; display: block; }
        .list th { text-align: left; font-size: 10px; text-transform: uppercase; color: #6b6f80; border-bottom: 1px solid #d9dbe3; padding: 6px 4px; }
        .list td { border-bottom: 1px solid #eceef3; padding: 6px 4px; }
        .absent { color: #b42318; font-weight: bold; }
        .late { color: #b54708; font-weight: bold; }
    </style>
</head>
<body>
    <table class="head">
        <tr>
            @if($logoSrc)<td style="width: 60px;"><img src="{{ $logoSrc }}" style="max-height: 50px;"></td>@endif
            <td>
                <div class="school">{{ $school }}</div>
                <h1>Attendance report · {{ $period }}</h1>
            </td>
        </tr>
    </table>

    <p class="who">{{ $student }}</p>
    <p class="muted" style="margin: 2px 0 14px;">{{ $class }}</p>

    <table class="stats">
        <tr>
            <td><span class="n">{{ $days }}</span>School days</td>
            <td><span class="n">{{ $present }}</span>Present</td>
            <td><span class="n">{{ $late }}</span>Late</td>
            <td><span class="n">{{ $absent }}</span>Absent</td>
        </tr>
    </table>
    @if($rate !== null)
        <p style="margin: 10px 0 18px;">Attendance: <strong>{{ $rate }}%</strong></p>
    @endif

    @if(count($exceptions))
        <table class="list">
            <tr><th>Date</th><th>Status</th><th>Note</th></tr>
            @foreach($exceptions as $e)
                <tr>
                    <td>{{ $e['date'] }}</td>
                    <td class="{{ strtolower($e['status']) }}">{{ $e['status'] }}</td>
                    <td>{{ $e['note'] }}</td>
                </tr>
            @endforeach
        </table>
    @elseif($days)
        <p>Present every day.</p>
    @endif
</body>
</html>
