<?php

namespace App\Support;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * The report-card design for a class type. Each class type (Creche, Nursery, KG, Primary, JHS …) has
 * its own: title, which subject-table columns show and what they are called, the rating tables and
 * their items (e.g. no "Alertness" for the creche), attendance, comments, next-term lines, signatures.
 *
 * The defaults reproduce the original report card, so nothing changes until someone edits a design.
 * Ratings are stored per exam record in the order of the items, so the rating screen and the report
 * card both read the items from here.
 */
class ReportTemplate
{
    const COLUMNS = [
        'ca1' => 'CA1 (20)', 'ca2' => 'CA2 (20)', 'ca_total' => 'TOTAL (40)', 'exam' => 'EXAM (60)',
        'total' => 'FINAL MARKS (100%)', 'grade' => 'GRADE', 'position' => 'SUBJECT POSITION', 'remark' => 'REMARKS',
    ];

    public static function defaults(): array
    {
        $skills = function ($type) {
            return DB::table('skills')->where('skill_type', $type)->whereNull('class_type')->orderBy('name')->pluck('name')->all();
        };

        return [
            'title' => 'REPORT SHEET',
            // The assessment scheme may rename or hide score columns (e.g. CLASS SCORE (50%) instead of CA1 / CA2).
            'columns' => collect(self::COLUMNS)->map(function ($label, $key) {
                $r = Assessment::scheme()['report'][$key] ?? null;
                return ['key' => $key, 'label' => is_string($r) ? $r : $label, 'show' => $r !== false];
            })->values()->all(),
            'summary' => true,
            'groups' => [
                ['key' => 'af', 'title' => 'AFFECTIVE TRAITS', 'show' => true, 'items' => $skills('AF')],
                ['key' => 'ps', 'title' => 'PSYCHOMOTOR', 'show' => true, 'items' => $skills('PS')],
            ],
            'key' => ['show' => true, 'scale' => ['5 - Excellent', '4 - Very Good', '3 - Good', '2 - Fair', '1 - Poor']],
            'attendance' => false,
            'comments' => [
                'teacher' => true, 'teacher_label' => "CLASS TEACHER'S COMMENT",
                'head' => true, 'head_label' => "PRINCIPAL'S COMMENT",
                'next_term_begins' => true, 'next_term_fees' => true,
            ],
            'signatures' => [],
            'footer' => '',
        ];
    }

    /** The design for a class type: what was saved, with anything missing filled from the defaults. */
    public static function for(?int $classTypeId): array
    {
        $saved = $classTypeId ? DB::table('report_templates')->where('class_type_id', $classTypeId)->value('settings') : null;
        $t = array_replace(self::defaults(), $saved ? (array) json_decode($saved, true) : []);
        $defaultCols = collect(self::defaults()['columns']);
        // keep every known column, in the saved order, adding any new ones at the end
        $known = collect($t['columns'])->keyBy('key');
        $t['columns'] = collect(self::COLUMNS)->keys()->sortBy(function ($k) use ($known) { $i = $known->keys()->search($k); return $i === false ? 99 : $i; })
            ->map(function ($k) use ($known, $defaultCols) {
                $d = $defaultCols->firstWhere('key', $k);
                return array_replace($d, (array) ($known[$k] ?? []));
            })->values()->all();
        $t['comments'] = array_replace(self::defaults()['comments'], (array) ($t['comments'] ?? []));
        $t['key'] = array_replace(self::defaults()['key'], (array) ($t['key'] ?? []));

        return $t;
    }

    public static function save(int $classTypeId, array $t): void
    {
        DB::table('report_templates')->updateOrInsert(['class_type_id' => $classTypeId], [
            'settings' => json_encode($t), 'updated_by' => Auth::id(), 'updated_at' => now(), 'created_at' => now(),
        ]);
    }

    /** Rating items for a group (af / ps) of a class type, in the order ratings are stored. */
    public static function items(?int $classTypeId, string $group): array
    {
        $g = collect(self::for($classTypeId)['groups'])->firstWhere('key', $group);

        return $g && ($g['show'] ?? true) ? array_values($g['items'] ?? []) : [];
    }
}
