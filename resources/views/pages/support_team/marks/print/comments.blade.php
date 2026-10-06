@php($nextFees = $nextFees ?? null)
<div>
    <table class="td-left" style="border-collapse:collapse;">
        <tbody>
        <tr>
            <td><strong>CLASS TEACHER'S COMMENT:</strong></td>
            <td>  {{ $exr->t_comment ?: str_repeat('__', 40) }}</td>
        </tr>
        <tr>
            <td><strong>PRINCIPAL'S COMMENT:</strong></td>
            <td>  {{ $exr->p_comment ?: str_repeat('__', 40) }}</td>
        </tr>
        <tr>
            <td><strong>NEXT TERM BEGINS:</strong></td>
            <td>{{ date('l\, jS F\, Y', strtotime($s['term_begins'])) }}</td>
        </tr>
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
        </tbody>
    </table>
</div>
