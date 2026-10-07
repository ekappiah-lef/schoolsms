@php
    $nextFees = $nextFees ?? null;
    $attendance = $attendance ?? null;
    $tpl = $tpl ?? \App\Support\ReportTemplate::for(optional($class_type)->id);
    $cm = $tpl['comments'];
@endphp
<div>
    <table class="td-left" style="border-collapse:collapse;">
        <tbody>
        @if($tpl['attendance'] && $attendance)
            <tr>
                <td><strong>ATTENDANCE:</strong></td>
                <td>Present {{ $attendance['present'] }} of {{ $attendance['days'] }} days marked{{ $attendance['late'] ? ' (late '.$attendance['late'].')' : '' }} · Absent {{ $attendance['absent'] }}</td>
            </tr>
        @endif
        @if($cm['teacher'])
            <tr>
                <td><strong>{{ strtoupper($cm['teacher_label']) }}:</strong></td>
                <td>  {{ $exr->t_comment ?: str_repeat('__', 40) }}</td>
            </tr>
        @endif
        @if($cm['head'])
            <tr>
                <td><strong>{{ strtoupper($cm['head_label']) }}:</strong></td>
                <td>  {{ $exr->p_comment ?: str_repeat('__', 40) }}</td>
            </tr>
        @endif
        @if($cm['next_term_begins'] && !empty($s['term_begins']))
            <tr>
                <td><strong>NEXT TERM BEGINS:</strong></td>
                <td>{{ date('l\, jS F\, Y', strtotime($s['term_begins'])) }}</td>
            </tr>
        @endif
        @if($cm['next_term_fees'])
            <tr>
                <td><strong>NEXT TERM FEES:</strong></td>
                <td>
                    @if($nextFees && $nextFees['set'])
                        <strong>GHS {{ number_format($nextFees['total']) }}</strong>
                        ({{ $nextFees['label'] }}: school fees {{ number_format($nextFees['school']) }}@if($nextFees['services']) + services {{ number_format($nextFees['services']) }}@endif @if($nextFees['owed']) + balance owed {{ number_format($nextFees['owed']) }}@endif)
                    @elseif($nextFees)
                        {{ $nextFees['label'] }} fees not yet set.@if($nextFees['owed']) Balance owed now: GHS {{ number_format($nextFees['owed']) }}.@endif
                    @endif
                </td>
            </tr>
        @endif
        </tbody>
    </table>

    @if(count($tpl['signatures'] ?? []))
        <table style="width:100%; margin-top: 40px; border-collapse:collapse;">
            <tr>
                @foreach($tpl['signatures'] as $sig)
                    <td style="text-align:center; padding: 0 12px;">
                        <div style="border-top: 1px solid #000; padding-top: 4px; font-weight: bold;">{{ strtoupper($sig) }}</div>
                    </td>
                @endforeach
            </tr>
        </table>
    @endif

    @if(!empty($tpl['footer']))
        <p style="margin-top: 20px; text-align:center; font-size: 12px;">{{ $tpl['footer'] }}</p>
    @endif
</div>
