<?php

namespace App\Support;

use App\Helpers\Qs;
use App\Models\Department;
use App\Models\Exam;
use App\Models\Mark;
use App\Models\MarkApproval;
use App\Models\Subject;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Heads of department check and approve marks (schools with the hod_marks_approval switch).
 *
 *  - The subject teacher saves marks as usual, then submits the subject's sheet to the HOD.
 *  - While submitted or approved, the sheet is locked: nobody can change a mark.
 *  - The HOD (of the subject's department; not for a subject they teach themselves), the academic admin or an admin approves it, or returns it
 *    with a note saying what to correct; an approved sheet can also be returned for a correction.
 *    A returned sheet is open again for the teacher, who corrects it and submits it again.
 *  - A teacher can withdraw a sheet they submitted (to correct it) until it is approved.
 *  - Report cards are not sent to parents until every subject with marks in that class is approved.
 *
 * Every step is kept in the sheet's history (who, when, note).
 */
class MarkApprovals
{
    const SUBMITTED = 'submitted';
    const APPROVED = 'approved';
    const RETURNED = 'returned';

    public static function on(): bool
    {
        return School::has('hod_marks_approval');
    }

    public static function sheet($examId, $classId, $sectionId, $subjectId, $year): ?MarkApproval
    {
        return MarkApproval::where(['exam_id' => $examId, 'my_class_id' => $classId, 'section_id' => $sectionId, 'subject_id' => $subjectId, 'year' => $year])->first();
    }

    /** Departments the signed-in user heads. */
    public static function headedDepartmentIds(?int $userId = null): array
    {
        return Department::where('hod_id', $userId ?: Auth::id())->pluck('id')->map(function ($v) { return (int) $v; })->all();
    }

    /** Schools whose departments cover class types (Lower Grade, Upper Grade …) rather than subjects. */
    public static function byClassType(): bool
    {
        return School::has('departments_by_class_type');
    }

    /** Class types covered by the departments the user heads. */
    public static function headedClassTypeIds(?int $userId = null): array
    {
        return Department::where('hod_id', $userId ?: Auth::id())->get()->flatMap(function ($d) { return (array) $d->class_type_ids; })
            ->map(function ($v) { return (int) $v; })->unique()->values()->all();
    }

    /** The department a subject's marks go to: by its class type, or by the subject. */
    public static function departmentFor($subject): ?Department
    {
        if (!$subject) return null;
        if (self::byClassType()) {
            $type = (int) DB::table('my_classes')->where('id', $subject->my_class_id)->value('class_type_id');
            return Department::with('hod')->get()->first(function ($d) use ($type) { return in_array($type, array_map('intval', (array) $d->class_type_ids), true); });
        }
        return $subject->department_id ? Department::with('hod')->find($subject->department_id) : null;
    }

    public static function isHod(?int $userId = null): bool
    {
        return self::on() && count(self::headedDepartmentIds($userId)) > 0;
    }

    /** May the signed-in user approve or return this subject's marks? HOD of its department, the academic admin or an admin. */
    public static function canReview($subjectId): bool
    {
        if (!self::on()) return false;
        if (Qs::userIsTeamSA()) return true;
        $subject = Subject::find($subjectId, ['id', 'department_id', 'teacher_id', 'my_class_id']);
        $dept = self::departmentFor($subject);

        // An HOD does not approve their own marks: those go to the academic admin.
        return $subject && $dept && (int) $dept->hod_id === (int) Auth::id() && (int) $subject->teacher_id !== (int) Auth::id()
            && !in_array((int) $subjectId, SubjectTeachers::subjectIdsOf((int) Auth::id()), true);
    }

    /** Submitted or approved sheets cannot be changed. */
    public static function locked(?MarkApproval $sheet): bool
    {
        return self::on() && $sheet && in_array($sheet->status, [self::SUBMITTED, self::APPROVED], true);
    }

    /** Status for the marks page: draft, submitted, approved or returned, with the last note and the history. */
    public static function forPage($examId, $classId, $sectionId, $subjectId, $year, bool $canMark): ?array
    {
        if (!self::on()) return null;
        $s = self::sheet($examId, $classId, $sectionId, $subjectId, $year);
        $dept = self::departmentFor(Subject::find($subjectId));
        $status = $s->status ?? 'draft';

        return [
            'status' => $status,
            'note' => $s->note ?? null,
            'department' => optional($dept)->name,
            'hod' => optional(optional($dept)->hod)->name,
            'history' => array_reverse((array) ($s->history ?? [])),
            'canSubmit' => $canMark && in_array($status, ['draft', self::RETURNED], true),
            'canWithdraw' => $canMark && $status === self::SUBMITTED,
            'canApprove' => self::canReview($subjectId) && $status === self::SUBMITTED,
            'canReturn' => self::canReview($subjectId) && in_array($status, [self::SUBMITTED, self::APPROVED], true),
        ];
    }

    /** Apply submit / withdraw / approve / return; returns an error message, or null when done. */
    public static function act(string $action, $examId, $classId, $sectionId, $subjectId, $year, bool $canMark, ?string $note): ?string
    {
        $page = self::forPage($examId, $classId, $sectionId, $subjectId, $year, $canMark);
        $allowed = ['submit' => 'canSubmit', 'withdraw' => 'canWithdraw', 'approve' => 'canApprove', 'return' => 'canReturn'];
        if (!$page || !isset($allowed[$action]) || !$page[$allowed[$action]]) {
            return 'This step is not possible for these marks now. Reload the page.';
        }
        $note = trim((string) $note) ?: null;
        if ($action === 'return' && !$note) {
            return 'Say what needs to be corrected.';
        }
        $where = ['exam_id' => $examId, 'my_class_id' => $classId, 'section_id' => $sectionId, 'subject_id' => $subjectId, 'year' => $year];
        if ($action === 'submit') {
            $tex = 'tex'.(int) Exam::where('id', $examId)->value('term');
            if (!Mark::where($where)->whereNotNull($tex)->exists()) {
                return 'Enter and save the marks before submitting them.';
            }
        }

        $s = MarkApproval::firstOrNew($where);
        $now = now();
        $status = ['submit' => self::SUBMITTED, 'withdraw' => self::RETURNED, 'approve' => self::APPROVED, 'return' => self::RETURNED][$action];
        $s->status = $status;
        if ($action === 'submit') {
            $s->submitted_by = Auth::id();
            $s->submitted_at = $now;
            $s->note = $note;
        } elseif ($action === 'withdraw') {
            $s->note = $note ?: 'Withdrawn by the teacher to correct.';
        } else {
            $s->reviewed_by = Auth::id();
            $s->reviewed_at = $now;
            $s->note = $note;
        }
        $label = ['submit' => 'Submitted to HOD', 'withdraw' => 'Withdrawn for correction', 'approve' => 'Approved', 'return' => $page['status'] === self::APPROVED ? 'Reopened for correction' : 'Returned for correction'][$action];
        $s->history = array_merge((array) ($s->history ?? []), [['action' => $label, 'by' => optional(Auth::user())->name, 'at' => $now->toDateTimeString(), 'note' => $action === 'withdraw' ? $s->note : $note]]);
        $s->save();

        return null;
    }

    /** Subjects of a class section with marks for this exam that are not approved yet (empty when the switch is off). */
    public static function unapproved($examId, $classId, $sectionId, $year): array
    {
        if (!self::on()) return [];
        $tex = 'tex'.(int) Exam::where('id', $examId)->value('term');
        $withMarks = Mark::where(['exam_id' => $examId, 'my_class_id' => $classId, 'section_id' => $sectionId, 'year' => $year])
            ->whereNotNull($tex)->distinct()->pluck('subject_id');
        $approved = MarkApproval::where(['exam_id' => $examId, 'my_class_id' => $classId, 'section_id' => $sectionId, 'year' => $year, 'status' => self::APPROVED])->pluck('subject_id');

        return Subject::whereIn('id', $withMarks->diff($approved))->orderBy('name')->pluck('name')->all();
    }
}
