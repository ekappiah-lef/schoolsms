<?php

use App\Support\Assessment;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Weighted schemes (e.g. 50% class + 50% exam): the total becomes the sum of the two rounded halves; grades, positions and averages follow. */
class WeightedTotalsSumOfHalves extends Migration
{
    public function up()
    {
        $w = Assessment::scheme()['weights'] ?? null;
        if (!$w) return;

        $marks = app(\App\Repositories\MarkRepo::class);
        $exams = app(\App\Repositories\ExamRepo::class);
        $terms = DB::table('exams')->pluck('term', 'id');
        $types = DB::table('my_classes')->pluck('class_type_id', 'id');
        $touched = [];

        foreach (DB::table('marks')->where(function ($q) { $q->whereNotNull('tca')->orWhereNotNull('exm'); })->get() as $mk) {
            $col = 'tex'.($terms[$mk->exam_id] ?? 1);
            if ($mk->{$col} === null) continue;
            $total = Assessment::weightedTotal($mk->tca, $mk->exm, $w);
            if ((int) $mk->{$col} === $total) continue;
            $grade = $marks->getGrade($total, $types[$mk->my_class_id] ?? null);
            DB::table('marks')->where('id', $mk->id)->update([$col => $total, 'grade_id' => $grade ? $grade->id : null]);
            $touched[$mk->exam_id.'|'.$mk->my_class_id.'|'.$mk->section_id.'|'.$mk->subject_id.'|'.$mk->year] = true;
        }

        // Positions and averages, as saving the sheet does.
        foreach (array_keys($touched) as $k) {
            [$examId, $classId, $sectionId, $subjectId, $year] = explode('|', $k);
            $exam = $exams->find($examId);
            $rows = DB::table('marks')->where(['exam_id' => $examId, 'my_class_id' => $classId, 'section_id' => $sectionId, 'subject_id' => $subjectId, 'year' => $year])->get();
            foreach ($rows as $mk) {
                DB::table('marks')->where('id', $mk->id)->update(['sub_pos' => $marks->getSubPos($mk->student_id, $exam, $classId, $subjectId, $year)]);
            }
            foreach ($rows->pluck('student_id')->unique() as $st) {
                $exams->updateRecord(['exam_id' => $examId, 'my_class_id' => $classId, 'section_id' => $sectionId, 'year' => $year, 'student_id' => $st], [
                    'total' => $marks->getExamTotalTerm($exam, $st, $classId, $year),
                    'ave' => $marks->getExamAvgTerm($exam, $st, $classId, $sectionId, $year),
                    'class_ave' => $marks->getClassAvg($exam, $classId, $year),
                    'pos' => $marks->getPos($st, $exam, $classId, $sectionId, $year),
                ]);
            }
        }
    }

    public function down()
    {
    }
}
