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
        return ClassTeachers::sectionIdsOf((int) ($teacherId ?: Auth::id()));
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

        // Every teacher, class teacher or not, enters marks only for the subjects they teach.
        $access = [];
        if ($mine) {
            foreach (Section::where('my_class_id', $classId)->pluck('id') as $id) {
                $access[(int) $id] = $mine;
            }
        }
        // Subjects taught in particular sections only (subject_teachers).
        foreach (SubjectTeachers::accessIn($classId, (int) $tid) as $sectionId => $ids) {
            $access[$sectionId] = array_values(array_unique(array_merge($access[$sectionId] ?? [], $ids)));
        }

        return $access;
    }

    /** Classes the teacher has anything to mark in. */
    public static function markClassIds(?int $teacherId = null): array
    {
        $tid = $teacherId ?: Auth::id();

        return collect(Section::whereIn('id', ClassTeachers::sectionIdsOf((int) $tid))->pluck('my_class_id'))
            ->merge(Subject::where('teacher_id', $tid)->orWhereIn('id', SubjectTeachers::subjectIdsOf((int) $tid))->pluck('my_class_id'))
            ->map(function ($v) { return (int) $v; })->unique()->values()->all();
    }

    public static function canMark(int $classId, int $sectionId, int $subjectId): bool
    {
        if (!self::applies()) {
            return true;
        }

        return in_array($subjectId, self::markAccess($classId)[$sectionId] ?? [], true);
    }

    /**
     * Sections a teacher may look at (not mark or change): their own, and, for the head of a department that covers
     * class types (departments_by_class_type), every section of those classes — results and attendance.
     */
    public static function viewSectionIds(?int $teacherId = null): array
    {
        $tid = (int) ($teacherId ?: Auth::id());
        $ids = self::ownSectionIds($tid);
        $types = self::headedClassTypeIds($tid);
        if ($types) {
            $ids = array_merge($ids, Section::whereIn('my_class_id', \App\Models\MyClass::whereIn('class_type_id', $types)->pluck('id'))->pluck('id')->all());
        }

        return array_values(array_unique(array_map('intval', $ids)));
    }

    /** Class types of the departments this teacher heads (schools whose departments cover class types). */
    public static function headedClassTypeIds(?int $teacherId = null): array
    {
        return MarkApprovals::byClassType() ? MarkApprovals::headedClassTypeIds((int) ($teacherId ?: Auth::id())) : [];
    }

    /** Head of a department (by class type) who is a teacher: sees those classes' results and attendance. */
    public static function isDepartmentHead(): bool
    {
        return self::applies() && count(self::headedClassTypeIds()) > 0;
    }

    /** May the signed-in user see this student's details? Teachers: their own section's students (an HOD: their department's). */
    public static function canSeeStudent(StudentRecord $sr): bool
    {
        return !self::applies() || in_array((int) $sr->section_id, self::viewSectionIds(), true);
    }
}
