<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Subject teachers per section. Normally a subject has one teacher for the whole class (subjects.teacher_id);
 * schools with the subject_teachers_per_section switch can also give each section its own teacher(s)
 * (subject_teachers). A teacher enters marks for a subject only in the sections they teach it in.
 */
class SubjectTeachers
{
    public static function on(): bool
    {
        return School::has('subject_teachers_per_section');
    }

    /** Subject ids the teacher teaches in any section. */
    public static function subjectIdsOf(int $teacherId): array
    {
        return DB::table('subject_teachers')->where('user_id', $teacherId)->distinct()->pluck('subject_id')->map(function ($v) { return (int) $v; })->all();
    }

    /** [section_id => [subject_id, ...]] the teacher teaches in this class (per-section assignments only). */
    public static function accessIn(int $classId, int $teacherId): array
    {
        $out = [];
        foreach (DB::table('subject_teachers as st')->join('subjects as s', 's.id', '=', 'st.subject_id')
            ->where('s.my_class_id', $classId)->where('st.user_id', $teacherId)->get(['st.section_id', 'st.subject_id']) as $r) {
            $out[(int) $r->section_id][] = (int) $r->subject_id;
        }
        return $out;
    }

    /** "Name (A), Name (B)" for a subject's per-section teachers, or null when it has none. */
    public static function label(int $subjectId): ?string
    {
        $rows = DB::table('subject_teachers as st')->join('users as u', 'u.id', '=', 'st.user_id')->join('sections as sc', 'sc.id', '=', 'st.section_id')
            ->where('st.subject_id', $subjectId)->orderBy('sc.name')->orderBy('u.name')->get(['u.name', 'sc.name as section']);
        if ($rows->isEmpty()) return null;

        return $rows->groupBy('name')->map(function ($g, $name) { return $name.' ('.$g->pluck('section')->implode(', ').')'; })->implode('; ');
    }

    /** [section_id => [user_id, ...]] for a subject. */
    public static function bySection(int $subjectId): array
    {
        $out = [];
        foreach (DB::table('subject_teachers')->where('subject_id', $subjectId)->orderBy('id')->get() as $r) $out[(int) $r->section_id][] = (int) $r->user_id;
        return $out;
    }

    /** Replace a subject's per-section teachers: [section_id => [user_id, ...]]. */
    public static function sync(int $subjectId, array $bySection): void
    {
        DB::transaction(function () use ($subjectId, $bySection) {
            DB::table('subject_teachers')->where('subject_id', $subjectId)->delete();
            foreach ($bySection as $sectionId => $ids) {
                foreach (array_unique(array_filter(array_map('intval', (array) $ids))) as $uid) {
                    DB::table('subject_teachers')->insert(['subject_id' => $subjectId, 'section_id' => (int) $sectionId, 'user_id' => $uid, 'created_at' => now(), 'updated_at' => now()]);
                }
            }
        });
    }
}
