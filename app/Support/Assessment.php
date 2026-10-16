<?php

namespace App\Support;

/**
 * The school's assessment scheme (config/school.php): which score boxes a teacher fills in for a subject
 * and how they make the subject total. The standard scheme keeps the original columns (1st CA, 2nd CA, Exam);
 * other schemes keep their class boxes in marks.parts, the class total in tca, the exam in exm, the total in tex{term}.
 */
class Assessment
{
    public static function scheme(): array
    {
        $all = config('school.schemes');

        return $all[config('school.assessment')] ?? $all['standard'];
    }

    public static function weighted(): bool
    {
        return (bool) (self::scheme()['weights'] ?? null);
    }

    /** For the mark-entry page: boxes, groups and weights. */
    public static function forPage(): array
    {
        $s = self::scheme();

        return ['parts' => $s['parts'], 'groups' => $s['groups'] ?? null, 'exam' => $s['exam'], 'weights' => $s['weights'] ?? null, 'title' => $s['title'] ?? null];
    }

    /** The score in each box for a mark row: [key => value|null]. */
    public static function scores($mk): array
    {
        $parts = $mk && $mk->parts ? (array) json_decode($mk->parts, true) : [];
        $out = [];
        foreach (self::scheme()['parts'] as $p) {
            $k = $p['key'];
            $out[$k] = in_array($k, ['t1', 't2', 't3', 't4', 'exm'], true) ? ($mk->$k ?? null) : ($parts[$k] ?? null);
        }

        return $out;
    }

    /**
     * Columns to save for one student from the boxes the teacher filled in. A score out of range (or a
     * standard total over 100) clears the row, as before. $term picks the total column (tex1/2/3).
     */
    public static function columns(array $in, int $term): array
    {
        $s = self::scheme();
        $tex = 'tex'.$term;
        $blank = ['t1' => null, 't2' => null, 't3' => null, 't4' => null, 'tca' => null, 'exm' => null, 'parts' => null, $tex => null];
        $vals = [];
        foreach ($s['parts'] as $p) {
            $v = $in[$p['key']] ?? null;
            if ($v === null || $v === '') { $vals[$p['key']] = null; continue; }
            if (!is_numeric($v) || $v < 0 || $v > $p['max']) return $blank;
            $vals[$p['key']] = $v + 0;
        }
        if (!array_filter($vals, function ($v) { return $v !== null; })) return $blank;

        $examKey = $s['exam'];
        $exam = $vals[$examKey];
        $classVals = array_diff_key($vals, [$examKey => 1]);
        $class = array_sum(array_map(function ($v) { return $v ?: 0; }, $classVals));

        if (!empty($s['weights'])) {
            $total = (int) round($class * $s['weights']['class'] + ($exam ?: 0) * $s['weights']['exam']);
            return array_replace($blank, ['tca' => $class, 'exm' => $exam, 'parts' => json_encode($classVals), $tex => $total]);
        }

        $total = $class + ($exam ?: 0);
        if ($total > 100) return $blank;

        return array_replace($blank, array_intersect_key($vals, array_flip(['t1', 't2', 't3', 't4'])), ['tca' => $class, 'exm' => $exam, $tex => $total]);
    }

    /** Columns shown on a student's result sheet (besides total, grade, position): [[key, label]]. */
    public static function summaryColumns(): array
    {
        $s = self::scheme();
        if (!empty($s['weights'])) {
            return [['key' => 'class', 'label' => 'Class score ('.($s['weights']['class'] * 100).'%)'], ['key' => 'exam', 'label' => 'Exam score ('.($s['weights']['exam'] * 100).'%)']];
        }

        return array_map(function ($p) { return ['key' => $p['key'], 'label' => $p['label']]; }, $s['parts']);
    }

    /** Values for summaryColumns() for one mark row. */
    public static function summary($mk): array
    {
        $s = self::scheme();
        if (!empty($s['weights'])) {
            return [
                'class' => $mk && $mk->tca !== null ? (int) round($mk->tca * $s['weights']['class']) : null,
                'exam' => $mk && $mk->exm !== null ? (int) round($mk->exm * $s['weights']['exam']) : null,
            ];
        }

        return self::scores($mk);
    }

    /** Report-card value for a column key (ca1, ca2, ca_total, exam), following the scheme. */
    public static function reportValue(string $key, $mk)
    {
        if (!$mk) return null;
        if (self::weighted()) {
            $sum = self::summary($mk);
            return ['ca_total' => $sum['class'], 'exam' => $sum['exam']][$key] ?? null;
        }

        return ['ca1' => $mk->t1, 'ca2' => $mk->t2, 'ca_total' => $mk->tca, 'exam' => $mk->exm][$key] ?? null;
    }
}
