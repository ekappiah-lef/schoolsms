@php
    $tpl = $tpl ?? \App\Support\ReportTemplate::for(optional($class_type)->id);
    $groups = collect($tpl['groups'])->filter(function ($g) { return ($g['show'] ?? true) && count($g['items'] ?? []); })->values();
@endphp
@if($groups->count() || ($tpl['key']['show'] && count($tpl['key']['scale'])))
<div>
    @if($tpl['key']['show'] && count($tpl['key']['scale']))
        {{--KEYS TO RATING--}}
        <div style="float: left">
            <br>
            <strong style="text-decoration: underline;">KEY</strong> <br>
            @foreach($tpl['key']['scale'] as $k)
                <span>{{ $k }}</span> <br>
            @endforeach
        </div>
    @endif

    @foreach($groups as $g)
        @php $vals = $exr->{$g['key']} ? explode(',', $exr->{$g['key']}) : []; @endphp
        <table align="left" style="width:{{ $groups->count() > 1 ? '38%' : '50%' }}; border-collapse:collapse; border: 1px solid #000; margin:10px 20px;" border="1">
            <thead>
            <tr>
                <td><strong>{{ strtoupper($g['title']) }}</strong></td>
                <td><strong>RATING</strong></td>
            </tr>
            </thead>
            <tbody>
            @foreach ($g['items'] as $i => $item)
                <tr>
                    <td>{{ strtoupper($item) }}</td>
                    <td>{{ $vals[$i] ?? '' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endforeach
</div>
@endif
