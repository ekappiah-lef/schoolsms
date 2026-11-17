<?php

namespace App\Support;

use App\Models\Section;
use Illuminate\Support\Facades\DB;

/**
 * Class teachers of a section. Normally one (sections.teacher_id); schools with the multiple_class_teachers
 * switch can give a section several (section_teachers), the first of them also kept in sections.teacher_id.
 */
class ClassTeachers
{
    public static function multiple(): bool
    {
        return School::has('multiple_class_teachers');
    }

    /** Sections the teacher is a class teacher of. */
    public static function sectionIdsOf(int $teacherId): array
    {
        return Section::where('teacher_id', $teacherId)->pluck('id')
            ->merge(DB::table('section_teachers')->where('user_id', $teacherId)->pluck('section_id'))
            ->map(function ($v) { return (int) $v; })->unique()->values()->all();
    }

    /** [section_id => [user_id, ...]] for the given sections (all sections when null). */
    public static function idsBySection(?array $sectionIds = null): array
    {
        $out = [];
        foreach (Section::when($sectionIds !== null, function ($q) use ($sectionIds) { $q->whereIn('id', $sectionIds); })->get(['id', 'teacher_id']) as $s) {
            if ($s->teacher_id) $out[$s->id][] = (int) $s->teacher_id;
        }
        foreach (DB::table('section_teachers')->when($sectionIds !== null, function ($q) use ($sectionIds) { $q->whereIn('section_id', $sectionIds); })->orderBy('id')->get() as $r) {
            $out[$r->section_id][] = (int) $r->user_id;
        }
        return array_map(function ($ids) { return array_values(array_unique($ids)); }, $out);
    }

    /** Names of a section's class teachers, joined with commas. */
    public static function names(int $sectionId): ?string
    {
        $ids = self::idsBySection([$sectionId])[$sectionId] ?? [];
        if (!$ids) return null;
        $names = DB::table('users')->whereIn('id', $ids)->pluck('name', 'id');

        return collect($ids)->map(function ($id) use ($names) { return $names[$id] ?? null; })->filter()->implode(', ');
    }

    /** Who can be a class teacher: teachers and academic heads. */
    const TYPES = ['teacher', 'academic_head'];

    /**
     * Set the sections a teacher is class teacher of (from the teacher's own page). A section that has one class
     * teacher gets this teacher in place of the previous one; with several class teachers they are added alongside.
     */
    public static function setSectionsOf(int $userId, array $sectionIds): void
    {
        $want = array_values(array_unique(array_filter(array_map('intval', $sectionIds))));
        $have = self::sectionIdsOf($userId);
        foreach (array_diff($have, $want) as $sid) {
            if (self::multiple()) {
                self::sync($sid, array_diff(self::idsBySection([$sid])[$sid] ?? [], [$userId]));
            } else {
                Section::where(['id' => $sid, 'teacher_id' => $userId])->update(['teacher_id' => null]);
                DB::table('section_teachers')->where(['section_id' => $sid, 'user_id' => $userId])->delete();
            }
        }
        foreach (array_diff($want, $have) as $sid) {
            if (!Section::where('id', $sid)->exists()) continue;
            if (self::multiple()) {
                self::sync($sid, array_merge(self::idsBySection([$sid])[$sid] ?? [], [$userId]));
            } else {
                Section::where('id', $sid)->update(['teacher_id' => $userId]);
            }
        }
    }

    /** Set a section's class teachers (the first is also the section's main teacher). */
    public static function sync(int $sectionId, array $teacherIds): void
    {
        $teacherIds = array_values(array_unique(array_filter(array_map('intval', $teacherIds))));
        DB::transaction(function () use ($sectionId, $teacherIds) {
            DB::table('section_teachers')->where('section_id', $sectionId)->delete();
            foreach ($teacherIds as $id) {
                DB::table('section_teachers')->insert(['section_id' => $sectionId, 'user_id' => $id, 'created_at' => now(), 'updated_at' => now()]);
            }
            Section::where('id', $sectionId)->update(['teacher_id' => $teacherIds[0] ?? null]);
        });
    }
}
