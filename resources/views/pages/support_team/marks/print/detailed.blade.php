@php
    // Detailed layout (ReportTemplate 'layout' => 'detailed'): school header, student details in three columns,
    // the subject table, general remarks and the grading explanation.
    $extra = $extra ?? [];
    $cols = collect($tpl['columns'])->where('show', true)->whereNotIn('key', ['ca1', 'ca2'])->values();
    $value = function ($key, $mk) use ($tex) {
        if (!$mk) return '';
        switch ($key) {
            case 'ca_total': case 'exam':
                $v = \App\Support\Assessment::reportValue($key, $mk);
                return $v === null ? '' : $v + 0;
            case 'total': return $mk->$tex === null ? '' : $mk->$tex + 0;
            case 'grade': return $mk->grade ? $mk->grade->name : '';
            case 'position': return $mk->grade && $mk->sub_pos ? Mk::getSuffix($mk->sub_pos) : '';
            case 'remark': return $mk->grade ? strtoupper($mk->grade->remark) : '';
        }
        return '';
    };
    $num = function ($v) { return $v === null || $v === '' ? '-' : rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.'); };
    $grades = Mk::getGradeList(optional($class_type)->id)->sortByDesc('mark_from')->values();
    $termWord = \App\Support\ReportCard::TERMS[(int) $ex->term] ?? '';
    $title = trim(($tpl['title'] && strtoupper($tpl['title']) !== 'REPORT SHEET') ? $tpl['title'] : 'ACADEMIC REPORT');
    $sectionName = $extra['section'] ?? null;
    $att = $attendance ?? null;
    $dark = 'background:#2f2f2f; color:#fff;';
    $cell = 'border:1px solid #9a9a9a; padding:3px 6px;';
@endphp
<div style="font-family: Calibri, 'Segoe UI', Arial, sans-serif; font-size: 12px; color: #111;">
    <table width="100%" style="border-collapse:collapse;">
        <tr>
            <td style="width: 110px; vertical-align: top;">@if($logoSrc)<img src="{{ $logoSrc }}" style="max-height: 90px; max-width: 110px;">@endif</td>
            <td style="text-align: center; vertical-align: top; line-height: 1.5;">
                <div style="font-weight: bold; font-size: 14px;">{{ strtoupper(Qs::getSetting('system_name')) }}</div>
                @if(!empty($s['address']))<div>Location: <strong>{{ strtoupper($s['address']) }}</strong></div>@endif
                @if(!empty($s['phone']))<div>Phone number: {{ $s['phone'] }}</div>@endif
                @if(!empty($s['system_email']))<div>E-mail address: <strong>{{ $s['system_email'] }}</strong></div>@endif
            </td>
            <td style="width: 110px; text-align: right; vertical-align: top;">@if($photoSrc)<img src="{{ $photoSrc }}" style="width: 90px; height: 90px; object-fit: cover;">@endif</td>
        </tr>
    </table>

    <div style="text-align: center; font-weight: bold; font-size: 16px; margin: 10px 0 12px;">END OF {{ $termWord }} TERM {{ strtoupper($title) }}</div>

    <table width="100%" style="border-collapse:collapse; line-height: 1.9;">
        <tr>
            <td style="width: 36%; vertical-align: top;">Student: <strong>{{ strtoupper($sr->user->name) }}</strong></td>
            <td style="width: 34%; vertical-align: top;">Class: <strong>{{ strtoupper($my_class->name) }}{{ $sectionName ? ' - '.strtoupper($sectionName) : '' }}</strong></td>
            <td style="vertical-align: top;">@if($att)Attendance: <strong>{{ $att['present'] }} out of {{ $att['days'] }}</strong>@endif</td>
        </tr>
        <tr>
            <td>Academic year: <strong>{{ str_replace('-', ' - ', $ex->year) }} Academic Year</strong></td>
            <td>Current term: <strong>Term {{ $ex->term }}</strong></td>
            <td>@if(\App\Support\ReportCard::nextTerm($s['term_begins'] ?? null))Next term: <strong>{{ \App\Support\ReportCard::nextTerm($s['term_begins']) }}</strong>@endif</td>
        </tr>
        <tr>
            <td>@if(!empty($extra['promoted']))Promoted To: <strong>{{ strtoupper($extra['promoted']) }}</strong>@endif</td>
            <td>Overall Score: <strong>{{ $num($exr->total) }}</strong></td>
            <td>Average Score: <strong>{{ $num($exr->ave) }}</strong></td>
        </tr>
        <tr>
            <td>Average Score %: <strong>{{ $num($exr->ave) }}</strong></td>
            <td>Class Average Score: <strong>{{ $num($extra['class_total'] ?? null) }}</strong></td>
            <td>Class Average Score %: <strong>{{ $num($extra['class_ave'] ?? null) }}</strong></td>
        </tr>
        <tr>
            <td>Number On Roll: <strong>{{ $extra['on_roll'] ?? '-' }}</strong></td>
            <td></td>
            <td>@unless(\App\Support\School::has('subject_positions_only'))@if($exr->pos ?? null)Position: <strong>{!! Mk::getSuffix($exr->pos) !!}</strong>@endif @endunless</td>
        </tr>
    </table>

    <table width="100%" style="border-collapse:collapse; margin-top: 10px;">
        <thead>
        <tr style="{{ $dark }}">
            <th style="{{ $cell }} text-align:left;">Subject</th>
            <th style="{{ $cell }}">CODE</th>
            @foreach($cols as $c)
                <th style="{{ $cell }}">{{ $c['label'] }}</th>
            @endforeach
        </tr>
        </thead>
        <tbody>
        @php $lastGroup = null; @endphp
        @foreach(\App\Support\School::orderSubjects(\App\Support\ReportSubjects::filter($subjects)->values()) as $sub)
            @php
                $mk = $marks->where('subject_id', $sub->id)->where('exam_id', $ex->id)->first();
                $group = \App\Support\School::subjectGroup($sub);
            @endphp
            @if($group && $group !== $lastGroup)
                <tr><td colspan="{{ $cols->count() + 2 }}" style="{{ $cell }} font-weight:bold; background:#e6e6e6;">{{ strtoupper($group) }}</td></tr>
                @php $lastGroup = $group; @endphp
            @endif
            <tr>
                <td style="{{ $cell }}">{{ strtoupper($sub->name) }}</td>
                <td style="{{ $cell }}">{{ strtoupper($sub->slug ?? '') }}</td>
                @foreach($cols as $c)
                    <td style="{{ $cell }}">{!! $value($c['key'], $mk) !!}</td>
                @endforeach
            </tr>
        @endforeach
        </tbody>
    </table>

    <table width="100%" style="border-collapse:collapse; margin-top: 14px;">
        <tr style="{{ $dark }}"><td style="{{ $cell }} font-weight:bold;">General remarks &amp; comments</td></tr>
        <tr><td style="{{ $cell }}">CONDUCT: <strong>{{ strtoupper($exr->conduct ?? '') }}</strong></td></tr>
        <tr><td style="{{ $cell }}">INTEREST(S): <strong>{{ strtoupper($exr->interests ?? '') }}</strong></td></tr>
        <tr><td style="{{ $cell }}">TEACHER'S REMARKS: <strong>{{ strtoupper($exr->t_comment ?? '') }}</strong></td></tr>
        <tr><td style="{{ $cell }}">TEACHER'S NAME: <strong>{{ strtoupper($extra['teacher'] ?? '') }}</strong></td></tr>
        <tr><td style="{{ $cell }}">HEADTEACHER'S REMARKS: <strong>{{ strtoupper($exr->p_comment ?? '') }}</strong></td></tr>
        <tr><td style="{{ $cell }}">HEADTEACHER'S NAME: <strong>{{ strtoupper($tpl['head_name'] ?? '') }}</strong></td></tr>
    </table>

    <table style="border-collapse:collapse; margin-top: 18px;">
        <tr>
            <td style="vertical-align: bottom; padding-right: 10px;">HEADTEACHER'S SIGNATURE</td>
            <td style="width: 160px; height: 50px; vertical-align: bottom;">
                @php $sig = !empty($tpl['signature']) ? $img($tpl['signature']) : null; @endphp
                @if($sig)<img src="{{ $sig }}" style="max-height: 50px; max-width: 160px;">@else<div style="border-bottom: 1px solid #000; height: 40px;"></div>@endif
            </td>
        </tr>
    </table>

    @if($grades->count())
        <table width="100%" style="border-collapse:collapse; margin-top: 18px;">
            <tr style="{{ $dark }}"><td colspan="2" style="{{ $cell }} font-weight:bold;">Summative (Scoring) assessment explanation</td></tr>
            @foreach($grades->chunk(2) as $pair)
                <tr>
                    @foreach($pair as $g)
                        <td style="{{ $cell }} width: 50%;">{{ $g->name }}: {{ $g->mark_from }} - {{ $g->mark_to }} ({{ strtoupper($g->remark) }})</td>
                    @endforeach
                    @if($pair->count() < 2)<td style="{{ $cell }}"></td>@endif
                </tr>
            @endforeach
        </table>
    @endif

    @if(!empty($tpl['footer']))
        <p style="margin-top: 14px; text-align:center; font-size: 11px;">{{ $tpl['footer'] }}</p>
    @endif
</div>
