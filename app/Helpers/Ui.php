<?php

namespace App\Helpers;

use Inertia\Inertia;

/**
 * Chooses between the new React (Inertia) page and the original Blade view.
 *
 * The Blade views are kept during the migration so users can switch back to
 * the classic interface (/ui/classic) until every screen has feature parity.
 */
class Ui
{
    /** Route names whose pages have a React implementation. */
    const CONVERTED = [
        'home', 'dashboard',
        'students.index', 'students.list', 'students.show', 'students.create', 'students.edit', 'students.graduated',
        'payments.index', 'payments.show', 'payments.create', 'payments.edit', 'payments.manage', 'payments.invoice',
        'marks.index', 'marks.manage',
        'tt.index', 'ttr.edit',
        'classes.index', 'classes.edit', 'sections.index', 'sections.edit', 'subjects.index', 'subjects.edit',
        'dorms.index', 'dorms.edit', 'exams.index', 'exams.edit', 'grades.index', 'grades.edit', 'exams.show',
        'users.index', 'users.show', 'users.edit', 'attendance.index', 'messages.index', 'settings', 'my_account', 'pins.index', 'pins.create', 'pins.enter', 'students.promotion', 'students.promotion_manage', 'my_children', 'marks.show', 'marks.year_selector', 'marks.tabulation', 'marks.bulk', 'marks.batch_fix', 'ttr.manage', 'ttr.show', 'ts.edit',
        'finance.config', 'finance.dashboard', 'finance.ledger', 'finance.audit', 'clearenroll.index', 'finance.fee_breakdown', 'finance.sales', 'finance.transactions', 'finance.transactions.edit', 'finance.services',
    ];

    public static function isClassic(): bool
    {
        return session('ui_mode') === 'classic';
    }

    public static function isConverted(?string $routeName): bool
    {
        return ! self::isClassic() && in_array($routeName, self::CONVERTED, true);
    }

    /**
     * @param  string  $component  Inertia page component
     * @param  array|\Closure  $props  Props for React (a closure is only evaluated when React is used)
     * @param  string|null  $legacyView  Original Blade view
     * @param  array|\Closure  $legacyData  Data for the Blade view
     */
    public static function render(string $component, $props, ?string $legacyView = null, $legacyData = [])
    {
        if ($legacyView && self::isClassic()) {
            return view($legacyView, value($legacyData));
        }

        return Inertia::render($component, value($props));
    }
}
