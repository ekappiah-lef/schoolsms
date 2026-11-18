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
    $nextFees = $nextFees ?? null;
    $dark = 'background:#2f2f2f; color:#fff;';
    $cell = 'border:1px solid #9a9a9a; padding:4px 6px !important;';
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

    @php
        // Student details, three to a row; each one follows the report design (attendance, next term, totals).
        $cm = $tpl['comments'];
        $nextTerm = $cm['next_term_begins'] ? \App\Support\ReportCard::nextTerm($s['term_begins'] ?? null) : null;
        $items = array_filter([
            ['Student', strtoupper($sr->user->name)],
            ['Class', strtoupper($my_class->name).($sectionName ? ' - '.strtoupper($sectionName) : '')],
            $tpl['attendance'] && $att ? ['Attendance', $att['present'].' out of '.$att['days']] : null,
            ['Academic year', str_replace('-', ' - ', $ex->year).' Academic Year'],
            ['Current term', 'Term '.$ex->term],
            $nextTerm ? ['Next term', $nextTerm] : null,
            !empty($extra['promoted']) ? ['Promoted To', strtoupper($extra['promoted'])] : null,
            $tpl['summary'] ? ['Overall Score', $num($exr->total)] : null,
            $tpl['summary'] ? ['Average Score', $num($exr->ave)] : null,
            $tpl['summary'] ? ['Average Score %', $num($exr->ave)] : null,
            $tpl['summary'] ? ['Class Average Score', $num($extra['class_total'] ?? null)] : null,
            $tpl['summary'] ? ['Class Average Score %', $num($extra['class_ave'] ?? null)] : null,
            $tpl['summary'] && !\App\Support\School::has('subject_positions_only') && ($exr->pos ?? null) ? ['Position', strip_tags(Mk::getSuffix($exr->pos))] : null,
            ['Number On Roll', $extra['on_roll'] ?? '-'],
        ]);
    @endphp
    <table width="100%" class="td-left" style="border-collapse:collapse; line-height: 1.5;">
        @foreach(array_chunk(array_values($items), 3) as $row)
            <tr>
                @foreach($row as [$label, $val])
                    <td style="width: 33%; vertical-align: top;">{{ $label }}: <strong>{{ $val }}</strong></td>
                @endforeach
                @for($i = count($row); $i < 3; $i++)<td style="width: 33%;"></td>@endfor
            </tr>
        @endforeach
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
                <tr><td colspan="{{ $cols->count() + 2 }}" style="{{ $cell }} font-weight:bold; background:#e6e6e6; text-align:left;">{{ strtoupper($group) }}</td></tr>
                @php $lastGroup = $group; @endphp
            @endif
            <tr>
                <td style="{{ $cell }} text-align:left;">{{ strtoupper($sub->name) }}</td>
                <td style="{{ $cell }}">{{ strtoupper($sub->slug ?? '') }}</td>
                @foreach($cols as $c)
                    <td style="{{ $cell }}">{!! $value($c['key'], $mk) !!}</td>
                @endforeach
            </tr>
        @endforeach
        </tbody>
    </table>

    @php
        $remarks = array_filter([
            $cm['conduct'] ? [strtoupper($cm['conduct_label']), $exr->conduct ?? ''] : null,
            $cm['interests'] ? [strtoupper($cm['interests_label']), $exr->interests ?? ''] : null,
            $cm['teacher'] ? [strtoupper($cm['teacher_label']), $exr->t_comment ?? ''] : null,
            $cm['teacher'] ? ["TEACHER'S NAME", strtoupper($extra['teacher'] ?? '')] : null,
            $cm['head'] ? [strtoupper($cm['head_label']), $exr->p_comment ?? ''] : null,
            $cm['head'] ? ["HEADTEACHER'S NAME", strtoupper(\App\Support\ReportTemplate::headName($tpl))] : null,
        ]);
        $fees = $cm['next_term_fees'] ? ($nextFees ?? null) : null;
        $written = (array) json_decode((string) ($exr->assessments ?? ''), true);
        $assess = $tpl['assessments'] ?? [];
    @endphp
    @if(count($remarks) || $fees || count($assess))
        <table width="100%" class="td-left" style="border-collapse:collapse; margin-top: 14px;">
            <tr style="{{ $dark }}"><td style="{{ $cell }} font-weight:bold;">General remarks &amp; comments</td></tr>
            @foreach($assess as $a)
                <tr><td style="{{ $cell }}">{{ strtoupper($a) }}:<br><strong>{{ $written[$a] ?? '' }}</strong></td></tr>
            @endforeach
            @foreach($remarks as [$label, $val])
                <tr><td style="{{ $cell }}">{{ $label }}: <strong>{{ $val }}</strong></td></tr>
            @endforeach
            @if($fees)
                <tr><td style="{{ $cell }}">NEXT TERM FEES: <strong>
                    @if($fees['set'])GHS {{ number_format($fees['total'], 2) }} ({{ $fees['label'] }})@else{{ $fees['label'] }} fees not yet set @endif
                </strong></td></tr>
            @endif
        </table>
    @endif

    {{-- Behaviour and skills ratings, when the design shows them. --}}
    <div style="margin-top: 10px;">@include('pages.support_team.marks.print.skills')</div>
    <div style="clear: both;"></div>

    @if(count($tpl['signatures'] ?? []))
        <table class="td-left" style="border-collapse:collapse; margin-top: 18px;">
            <tr>
                @foreach($tpl['signatures'] as $sig)
                    <td style="vertical-align: bottom; padding-right: 10px;">{{ strtoupper($sig) }}</td>
                    <td style="width: 160px; height: 50px; vertical-align: bottom; padding-right: 30px;"><div style="border-bottom: 1px solid #000; height: 40px;"></div></td>
                @endforeach
            </tr>
        </table>
    @endif

    @if(($tpl['grading'] ?? false) && $grades->count())
        <table width="100%" class="td-left" style="border-collapse:collapse; margin-top: 18px;">
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
