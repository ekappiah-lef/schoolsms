<?php

namespace App\Support;

use App\Helpers\Qs;
use App\Models\Section;
use App\Models\StudentRecord;
use App\Models\Subject;
use Illuminate\Support\Facades\Auth;

/**
 * What a teacher may see and mark.
 *
 *  - Class teacher (sections.teacher_id): sees the students of their own section(s) and enters
 *    marks for every subject in that section. A Class 1 Gold teacher cannot see Class 1 Diamond.
 *  - Subject teacher (subjects.teacher_id, per class): enters marks for that subject only, in the
 *    sections of that class; the marks feed into each student's normal report.
 *  - In a class where the teacher is class teacher of a section, they get only their own section
 *    (subjects are recorded per class, not per section, so this keeps Class 1 Gold's teacher out
 *    of Class 1 Diamond even if they are listed as a subject teacher for Class 1).
 *
 * Admins and academic admins are not limited.
 */
class TeacherScope
{
    /** True when the signed-in user is a teacher, so the limits apply. */
    public static function applies(): bool
    {
        return Auth::check() && Qs::userIsTeacher();
    }

    /** Sections this teacher is class teacher of. */
    public static function ownSectionIds(?int $teacherId = null): array
    {
        return Section::where('teacher_id', $teacherId ?: Auth::id())->pluck('id')->map(function ($v) { return (int) $v; })->all();
    }

    /**
     * Sections of a class the teacher may mark, each with the subject ids allowed there:
     * [section_id => [subject_id, ...]].
     */
    public static function markAccess(int $classId, ?int $teacherId = null): array
    {
        $tid = $teacherId ?: Auth::id();
        $subjects = Subject::where('my_class_id', $classId)->get(['id', 'teacher_id']);
        $mine = $subjects->where('teacher_id', $tid)->pluck('id')->map(function ($v) { return (int) $v; })->values()->all();
        $all = $subjects->pluck('id')->map(function ($v) { return (int) $v; })->values()->all();

        $sections = Section::where('my_class_id', $classId)->get(['id', 'teacher_id']);
        $classTeacherHere = $sections->contains(function ($s) use ($tid) { return (int) $s->teacher_id === (int) $tid; });

        $access = [];
        foreach ($sections as $s) {
            if ((int) $s->teacher_id === (int) $tid) {
                $access[$s->id] = $all;          // class teacher: every subject in their section
            } elseif ($mine && !$classTeacherHere) {
                $access[$s->id] = $mine;         // subject teacher: only their subjects
            }
        }

        return $access;
    }

    /** Classes the teacher has anything to mark in. */
    public static function markClassIds(?int $teacherId = null): array
    {
        $tid = $teacherId ?: Auth::id();

        return collect(Section::where('teacher_id', $tid)->pluck('my_class_id'))
            ->merge(Subject::where('teacher_id', $tid)->pluck('my_class_id'))
            ->map(function ($v) { return (int) $v; })->unique()->values()->all();
    }

    public static function canMark(int $classId, int $sectionId, int $subjectId): bool
    {
        if (!self::applies()) {
            return true;
        }

        return in_array($subjectId, self::markAccess($classId)[$sectionId] ?? [], true);
    }

    /** May the signed-in user see this student's details? Teachers: only their own section's students. */
    public static function canSeeStudent(StudentRecord $sr): bool
    {
        return !self::applies() || in_array((int) $sr->section_id, self::ownSectionIds(), true);
    }
}
