<?php

namespace App\Support;

use App\Helpers\Qs;
use App\Helpers\Ui;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/**
 * Sidebar navigation for the React shell.
 *
 * Visibility rules mirror resources/views/partials/menu.blade.php exactly.
 * They are a convenience only: every route keeps its own middleware checks.
 */
class Navigation
{
    public static function build(): array
    {
        if (! Auth::check()) {
            return [];
        }

        $sections = [];

        $sections[] = self::section(null, [
            self::item('Dashboard', 'dashboard', 'dashboard', ['dashboard', 'home']),
        ]);

        /* Academics */
        $academics = [];

        if (Qs::userIsTeamSAT()) {
            $students = [self::item('All students', 'users', 'students.index', ['students.index', 'students.list', 'students.show', 'students.edit'])];
            if (Qs::userIsTeamSA()) {
                $students[] = self::item('Admit student', null, 'students.create');
                $students[] = self::item('Promote students', null, 'students.promotion');
                $students[] = self::item('Manage promotions', null, 'students.promotion_manage');
                $students[] = self::item('Graduated', null, 'students.graduated');
            }
            $academics[] = self::group('Students', 'graduation-cap', $students);

            $exams = [];
            if (Qs::userIsTeamSA()) {
                $exams[] = self::item('Exam list', null, 'exams.index', ['exams.index', 'exams.edit']);
                $exams[] = self::item('Grades', null, 'grades.index', ['grades.index', 'grades.edit']);
                $exams[] = self::item('Tabulation sheet', null, 'marks.tabulation');
                $exams[] = self::item('Recalculate totals', null, 'marks.batch_fix');
            }
            $exams[] = self::item('Marks entry', null, 'marks.index', ['marks.index', 'marks.manage']);
            $exams[] = self::item('Results by class', null, 'marks.bulk', ['marks.bulk', 'marks.show']);
            // Academic admin / admins design the report card per class type; class teachers can view theirs.
            if (Qs::userIsTeamSA() || \App\Support\TeacherScope::ownSectionIds()) {
                $exams[] = self::item('Report cards', null, 'reports.design');
            }
            $academics[] = self::group('Examinations', 'notebook-pen', $exams);
        }

        if (Qs::userIsTeamSAT()) {
            $academics[] = self::item('Attendance', 'clipboard-check', 'attendance.index');
        }

        if (Qs::userIsAcademic()) {
            $academics[] = self::item('Timetables', 'calendar-clock', 'tt.index', ['tt.index', 'ttr.edit', 'ttr.show', 'ttr.manage']);
        }

        if (Qs::userIsStudent()) {
            $academics[] = self::item('My marksheet', 'scroll-text', ['marks.year_selector', Qs::hash(Auth::id())], ['marks.show', 'marks.year_selector', 'pins.enter']);
        }

        if (Qs::userIsParent()) {
            $academics[] = self::item('My children', 'users', 'my_children');
        }

        if (Qs::userIsTeamSA()) {
            $academics[] = self::group('ClearEnroll', 'shield-check', [
                self::item('Verify student', null, 'clearenroll.student'),
                self::item('Verify teacher', null, 'clearenroll.teacher'),
                self::item('Fee status sync', null, 'clearenroll.sync'),
            ]);
        }
        if ($academics) {
            $sections[] = self::section('Academics', $academics);
        }

        /* Messages: parents, staff, a class */
        if (Qs::userIsTeamSAT()) {
            // Admins approve everything; the academic admin approves teachers' messages.
            $waiting = Qs::userIsTeamAdmin() ? \App\Models\Message::where('status', 'pending')->count()
                : (Qs::userIsAcademicAdmin() ? \App\Models\Message::where('status', 'pending')->whereIn('sent_by', \App\User::where('user_type', 'teacher')->pluck('id'))->count() : 0);
            $sections[] = self::section('Communication', [self::item($waiting ? "Messages ({$waiting} to approve)" : 'Messages', 'megaphone', 'messages.index')]);
        }

        /* Finance */
        if (Qs::userIsTeamAccount()) {
            // Feeding / Bus / Extra-curricular share one route, so "active" depends on the group.
            $service = function ($label, $group) {
                $item = self::item($label, null, ['finance.services', $group]);
                $item['active'] = Route::currentRouteName() === 'finance.services' && request()->route('group') === $group;
                return $item;
            };
            $sections[] = self::section('Finance', [
                self::item('Finance dashboard', 'chart', 'finance.dashboard'),
                self::item('Student payments', 'wallet', 'payments.manage', ['payments.manage', 'payments.invoice', 'payments.receipts']),
                self::item('Fee setup', 'banknote', 'payments.index', ['payments.index', 'payments.edit', 'payments.show', 'payments.create']),
                self::item('Income & expenses', 'receipt', 'finance.transactions', ['finance.transactions', 'finance.transactions.edit']),
                self::item('Ledger', 'scroll-text', 'finance.ledger'),
                self::item('Audit trail', 'shield-check', 'finance.audit'),
                self::item('Fee breakdown', 'layers', 'finance.fee_breakdown'),
                self::item('Sales & inventory', 'shopping-bag', 'finance.sales'),
                self::group('Services', 'utensils', [
                    $service('Feeding', 'feeding'),
                    $service('Bus', 'bus'),
                    $service('Extra-curricular', 'extracurricular'),
                ]),
                self::item('Finance configuration', 'sliders', 'finance.config'),
            ]);
        }

        /* Administration */
        if (Qs::userIsTeamSA()) {
            $admin = array_filter([
                Qs::userIsTeamAdmin() ? self::item('Users', 'user-cog', 'users.index', ['users.index', 'users.show', 'users.edit'])
                    : (Qs::userIsAcademicAdmin() ? self::item('Teachers', 'user-cog', 'users.index', ['users.index', 'users.show', 'users.edit']) : null),
                self::item('Classes', 'school', 'classes.index', ['classes.index', 'classes.edit']),
                self::item('Sections', 'layers', 'sections.index', ['sections.index', 'sections.edit']),
                self::item('Subjects', 'book-open', 'subjects.index', ['subjects.index', 'subjects.edit']),
                self::item('Dormitories', 'bed-double', 'dorms.index', ['dorms.index', 'dorms.edit']),
            ]);

            if (Qs::userIsSuperAdmin()) {
                $admin[] = self::group('Result pins', 'key-round', [
                    self::item('Generate pins', null, 'pins.create'),
                    self::item('View pins', null, 'pins.index'),
                ]);
                $admin[] = self::item('Settings', 'settings', 'settings');
            }

            $sections[] = self::section('Administration', array_values($admin));
        }

        return array_values(array_filter($sections, function ($s) {
            return count($s['items']) > 0;
        }));
    }

    protected static function section(?string $label, array $items): array
    {
        return ['label' => $label, 'items' => $items];
    }

    protected static function group(string $label, string $icon, array $children): array
    {
        $active = collect($children)->contains('active', true);

        return ['label' => $label, 'icon' => $icon, 'children' => $children, 'active' => $active];
    }

    /**
     * @param  string|array  $route  route name, or [name, ...params]
     */
    protected static function item(string $label, ?string $icon, $route, array $activeRoutes = []): array
    {
        $name = is_array($route) ? $route[0] : $route;
        $params = is_array($route) ? array_slice($route, 1) : [];
        $current = Route::currentRouteName();

        return [
            'label' => $label,
            'icon' => $icon,
            'href' => route($name, $params),
            // Converted pages are visited through Inertia, the rest with a normal page load.
            'spa' => Ui::isConverted($name),
            'active' => in_array($current, $activeRoutes ?: [$name], true),
        ];
    }
}
