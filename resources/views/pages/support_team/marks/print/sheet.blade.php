@php
    // Report-card design for this class type (App\Support\ReportTemplate).
    $tpl = $tpl ?? \App\Support\ReportTemplate::for(optional($class_type)->id);
    $cols = collect($tpl['columns'])->where('show', true)->values();
    $ca = $cols->whereIn('key', ['ca1', 'ca2', 'ca_total'])->values();
    $other = $cols->whereNotIn('key', ['ca1', 'ca2', 'ca_total'])->values();
    $value = function ($key, $mk) use ($tex) {
        if (!$mk) return '-';
        switch ($key) {
            case 'ca1': return $mk->t1 ?: '-';
            case 'ca2': return $mk->t2 ?: '-';
            case 'ca_total': return $mk->tca ?: '-';
            case 'exam': return $mk->exm ?: '-';
            case 'total': return $mk->$tex ?: '-';
            case 'grade': return $mk->grade ? $mk->grade->name : '-';
            case 'position': return $mk->grade ? Mk::getSuffix($mk->sub_pos) : '-';
            case 'remark': return $mk->grade ? $mk->grade->remark : '-';
        }
        return '';
    };
@endphp
{{--<!--NAME , CLASS AND OTHER INFO -->--}}
<table style="width:100%; border-collapse:collapse; ">
    <tbody>
    <tr>
        <td><strong>NAME:</strong> {{ strtoupper($sr->user->name) }}</td>
        <td><strong>ADM NO:</strong> {{ $sr->adm_no }}</td>
        <td><strong>HOUSE:</strong> {{ strtoupper($sr->house) }}</td>
        <td><strong>CLASS:</strong> {{ strtoupper($my_class->name) }}</td>
    </tr>
    <tr>
        <td><strong>REPORT SHEET FOR</strong> {!! strtoupper(Mk::getSuffix($ex->term)) !!} TERM </td>
        <td><strong>ACADEMIC YEAR:</strong> {{ $ex->year }}</td>
        <td><strong>AGE:</strong> {{ $sr->age ?: ($sr->user->dob ? date_diff(date_create($sr->user->dob), date_create('now'))->y : '-') }}</td>
    </tr>
    </tbody>
</table>

{{--Exam Table--}}
<table style="width:100%; border-collapse:collapse; border: 1px solid #000; margin: 10px auto;" border="1">
    <thead>
    <tr>
        <th rowspan="{{ $ca->count() ? 2 : 1 }}">SUBJECTS</th>
        @if($ca->count())
            <th colspan="{{ $ca->count() }}">CONTINUOUS ASSESSMENT</th>
        @endif
        @foreach($other as $c)
            <th rowspan="{{ $ca->count() ? 2 : 1 }}">{{ $c['label'] }}</th>
        @endforeach
    </tr>
    @if($ca->count())
        <tr>
            @foreach($ca as $c)
                <th>{{ $c['label'] }}</th>
            @endforeach
        </tr>
    @endif
    </thead>
    <tbody>
    @foreach($subjects as $sub)
        @php $mk = $marks->where('subject_id', $sub->id)->where('exam_id', $ex->id)->first(); @endphp
        <tr>
            <td style="font-weight: bold">{{ $sub->name }}</td>
            @foreach($ca as $c)
                <td>{!! $value($c['key'], $mk) !!}</td>
            @endforeach
            @foreach($other as $c)
                <td>{!! $value($c['key'], $mk) !!}</td>
            @endforeach
        </tr>
    @endforeach
    @if($tpl['summary'])
        @php $span = max(intdiv($cols->count() + 1, 3), 1); @endphp
        <tr>
            <td colspan="{{ $span }}"><strong>TOTAL SCORES OBTAINED: </strong> {{ $exr->total }}</td>
            <td colspan="{{ $span }}"><strong>FINAL AVERAGE: </strong> {{ $exr->ave }}</td>
            <td colspan="{{ $cols->count() + 1 - 2 * $span }}"><strong>CLASS AVERAGE: </strong> {{ $exr->class_ave }}</td>
        </tr>
    @endif
    </tbody>
</table>
