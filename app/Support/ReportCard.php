<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Figures for the detailed report-card layout: number on roll, class averages, class teacher, the class the
 * student was promoted to (third term), next term's start and attendance.
 */
class ReportCard
{
    const TERMS = [1 => 'FIRST', 2 => 'SECOND', 3 => 'THIRD'];

    public static function extras($exr, $sr, $exam, string $year): array
    {
        $records = DB::table('exam_records')->where(['exam_id' => $exam->id, 'my_class_id' => $exr->my_class_id, 'year' => $year]);
        $classTotal = (clone $records)->avg('total');
        $classAve = (clone $records)->avg('ave');

        $teachers = \App\User::whereIn('id', ClassTeachers::idsBySection([(int) $exr->section_id])[(int) $exr->section_id] ?? [])->pluck('name');

        $promoted = null;
        if ((int) $exam->term === 3) {
            $to = DB::table('promotions')->where(['student_id' => $exr->student_id, 'from_session' => $year])->latest('id')->value('to_class');
            $promoted = $to ? DB::table('my_classes')->where('id', $to)->value('name') : null;
        }

        return [
            'on_roll' => DB::table('student_records')->where(['my_class_id' => $exr->my_class_id, 'section_id' => $exr->section_id, 'grad' => 0])->count(),
            'class_total' => $classTotal !== null ? round((float) $classTotal, 2) : null,
            'class_ave' => $classAve !== null ? round((float) $classAve, 2) : null,
            'teacher' => $teachers->implode(', '),
            'promoted' => $promoted,
            'section' => DB::table('sections')->where('id', $exr->section_id)->value('name'),
        ];
    }

    /** "Tue, Sep 1 2026" from the Settings date the next term begins. */
    public static function nextTerm(?string $date): ?string
    {
        return $date ? date('D, M j Y', strtotime($date)) : null;
    }
}
