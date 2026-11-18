@php $gradeList = Mk::getGradeList(optional($class_type)->id)->sortByDesc('mark_from')->values(); @endphp
@if($gradeList->count())
<table style="width: 60%; margin: 12px auto 0; border-collapse: collapse; border: 1px solid #000; text-align: center;" border="1">
    <thead>
    <tr><td colspan="3"><strong>KEY TO THE GRADING</strong></td></tr>
    <tr><td><strong>RANGE</strong></td><td><strong>GRADE</strong></td><td><strong>REMARK</strong></td></tr>
    </thead>
    <tbody>
    @foreach($gradeList as $gr)
        <tr>
            <td>{{ $gr->mark_from.' - '.$gr->mark_to }}</td>
            <td>{{ $gr->name }}</td>
            <td>{{ strtoupper($gr->remark) }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
@endif
