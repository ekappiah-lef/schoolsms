@php
    // As a PDF (emailed report): styles are inlined and images read from disk.
    $pdf = $pdf ?? false;
    $img = function ($url) use ($pdf) {
        if (!$pdf) return $url;
        if (!$url || !extension_loaded('gd')) return null;
        if (preg_match('#/storage/(.+)$#', $url, $m)) $path = public_path('storage/'.ltrim(preg_replace('#/{2,}#', '/', $m[1]), '/'));
        elseif (preg_match('#^https?://[^/]+/(.+)$#', $url, $m)) $path = public_path($m[1]);
        else $path = null;
        return $path && is_file($path) ? $path : null;
    };
    $logoSrc = $img($s['logo'] ?? null);
    $photoSrc = $img($sr->user->photo);
    // Report-card design for this class type.
    $tpl = $tpl ?? \App\Support\ReportTemplate::for(optional($class_type)->id);
@endphp
<html>
<head>
    <title>Student Marksheet - {{ $sr->user->name }}</title>
    @if($pdf)
        <style>{!! @file_get_contents(public_path('assets/css/my_print.css')) !!} body{font-family: DejaVu Sans, sans-serif;} table{border-collapse:collapse;}</style>
    @else
        <link rel="stylesheet" type="text/css" href="{{ asset('assets/css/my_print.css') }}" />
    @endif
</head>
<body>
<div class="container">
    <div id="print" xmlns:margin-top="http://www.w3.org/1999/xhtml">
        {{--    Logo N School Details--}}
        <table width="100%">
            <tr>
                <td>@if($logoSrc)<img src="{{ $logoSrc }}" style="max-height : 100px;">@endif</td>

                <td style="text-align: center; ">
                    <strong><span style="color: #1b0c80; font-size: 25px;">{{ strtoupper(Qs::getSetting('system_name')) }}</span></strong><br/>
                   {{-- <strong><span style="color: #1b0c80; font-size: 20px;">MINNA, NIGER STATE</span></strong><br/>--}}
                    <strong><span
                                style="color: #000; font-size: 15px;"><i>{{ ucwords($s['address']) }}</i></span></strong><br/>
                    <strong><span style="color: #000; font-size: 15px;"> {{ strtoupper($tpl['title'] ?: 'REPORT SHEET') }} {{ '('.strtoupper($class_type->name).')' }}
                    </span></strong>
                </td>
                <td style="width: 100px; height: 100px; float: left;">
                    @if($photoSrc)<img src="{{ $photoSrc }}" alt="" width="100" height="100">@endif
                </td>
            </tr>
        </table>
        <br/>

        {{--Background Logo--}}
        <div style="position: relative;  text-align: center; ">
            @if($logoSrc && !$pdf)<img src="{{ $logoSrc }}"
                 style="max-width: 500px; max-height:600px; margin-top: 60px; position:absolute ; opacity: 0.2; margin-left: auto;margin-right: auto; left: 0; right: 0;" />@endif
        </div>

        {{--<!-- SHEET BEGINS HERE-->--}}
@include('pages.support_team.marks.print.sheet')

        {{--Key to Grading--}}
        {{--@include('pages.support_team.marks.print.grading')--}}

        {{-- TRAITS - PSCHOMOTOR & AFFECTIVE --}}
        @include('pages.support_team.marks.print.skills')

        <div style="margin-top: 25px; clear: both;"></div>

        {{--    COMMENTS & SIGNATURE    --}}
        @include('pages.support_team.marks.print.comments')

    </div>
</div>

@unless($pdf || ($preview ?? false))
<script>
    window.print();
</script>
@endunless
</body>

</html>
