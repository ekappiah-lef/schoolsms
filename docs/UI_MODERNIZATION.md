# LAV SMS — UI Modernization Plan & Audit

This document records the Phase 1 audit and the decisions taken for the
Laravel + Inertia + React UI modernization. The backend (controllers, models,
repositories, validation, middleware) is treated as correct and is kept.

## 1. Existing application

| Item | Finding |
| --- | --- |
| Laravel | 8.42.1 (`laravel/framework ^8.40`) |
| PHP (local) | 8.5 — Laravel 8 runs but emits deprecation notices from vendor code |
| Database | MariaDB via XAMPP (`C:\xampp_sep`), database `lav_sms` |
| Frontend | Blade + Limitless/Bootstrap 4 admin theme, jQuery, Select2, DataTables, SweetAlert, PNotify, FullCalendar. Assets are pre-built static files in `public/assets` and `public/global_assets`. `webpack.mix.js` / old `package.json` (Mix 2, Vue 2) were unused leftovers. |
| Auth | `Auth::routes()` + `LoginController` logs in with **username or email** (`identity` field). |
| Roles | `users.user_type`: super_admin, admin, teacher, accountant, librarian, student, parent. Groups are defined in `App\Helpers\Qs` (teamSA, teamSAT, teamAccount, …). |
| Authorization | Route middleware `super_admin`, `teamSA`, `teamSAT`, `teamAccount`, `my_parent`, `examIsLocked` + in-controller checks (e.g. student profile visibility). **Kept unchanged.** |
| IDs in URLs | Hashids with a salt that changes daily (`Qs::hash`). Route params named `{id}` are auto-decoded in `RouteServiceProvider`. React receives already-hashed IDs from the server. |
| Form submissions | Most create/update endpoints return JSON `{ok, msg}` (`Qs::jsonStoreOk()`), and 422 JSON on validation failure. Deletes/resets use form POST + redirect with `flash_*` / `pop_*` session messages. |

### Modules and backing functionality

| Module | Routes / controller | Notes |
| --- | --- | --- |
| Dashboard | `HomeController@dashboard` | User counts by type + an (empty) FullCalendar. |
| Students | `StudentRecordController`, `PromotionController` | Admit (JSON), edit (JSON), list by class, profile, graduated, reset password, promotion. |
| Users | `UserController` | Staff/parent CRUD (JSON store/update). |
| Classes / Sections / Subjects / Dorms | resource controllers | Simple CRUD. |
| Exams / Grades / Marks | `ExamController`, `GradeController`, `MarkController` | Marks entry per exam/class/section/subject, tabulation, marksheets, batch fix, PIN-gated results. |
| Payments | `PaymentController` | Fee definitions per class/year, per-student records, part payments, receipts (HTML + PDF). |
| Timetables | `TimeTableController` | Timetables, records, time slots. |
| Pins | `PinController` | Result-checker PINs. |
| Settings | `SuperAdmin\SettingController` | School settings, logo, lock exam. |
| Parent | `MyParent\MyController@children` | Parent's children list. |

### Features the brief mentions that have **no backend** today

- **Attendance** — no model, table, routes or UI exist. It is not built or faked in the UI.
  A real attendance module needs a migration + controller; tracked as a follow-up.
- **Events / notice board** — the old dashboard calendar has no data source.
- **Discounts / scholarships** on fees — no fields exist; the fee UI shows amount, paid, balance.
- **Student documents** — only a single passport photo is stored.

### Existing bugs found during the audit (old UI)

- `students/edit.blade.php` pre-selects the parent using `$sr->parent_id` (column is `my_parent_id`), so saving an edit silently **removed the student's parent**. The React form pre-selects correctly.
- `StudentRepo::getRecord()` only returns non-graduated students, so graduated students' profile/edit pages error. Left as-is (backend behaviour).
- `students/reset_pass/{id}` and `users/reset_pass/{id}` change state over GET. Left as-is to avoid changing routes; flagged for a later hardening pass.

### Existing UI problems

- Colour-blocked stat cards, mixed icon fonts, inconsistent spacing.
- Students are only reachable via a per-class sub-menu (one DB query per page render in the sidebar); no global list or search.
- Long flat forms with Select2 that lose state on validation errors.
- DataTables duplicated per tab (one per section/class) — heavy DOM.
- Profile page is a single two-column table.

## 2. Design reference (`stitch_minimalist_logo_login_page`)

`clarity_edtech/DESIGN.md` defines the system (Linear/Stripe-like):
Inter, slate neutrals (`#0f172a` / `#475569` / `#94a3b8`), porcelain canvas
`#fbfcfd`, hairline `#e2e8f0` borders, indigo `#4f46e5` primary, 6–8px radii,
micro-shadows only, uppercase `label-sm` table headers, tabular numerals,
240px collapsible sidebar. The dashboard and timetable screens show the shell
(logo + sidebar, top bar with session pill, page eyebrow + title), segmented
tabs, toolbar + table, and form layout. The teal login is the only screen
using a gradient; the new login keeps its centred, minimal form but follows
the restrained palette.

## 3. Architecture

- **Laravel + Inertia (`inertiajs/inertia-laravel` 0.6, Laravel 8 compatible) + React 18 + Vite** via `laravel-vite-plugin` 0.8 (Laravel 8 compatible).
- Converted pages call `App\Helpers\Ui::render()`, which renders the Inertia page, or the original Blade view when the user has switched to the classic UI (`/ui/classic`). Nothing is removed until parity is confirmed.
- JSON endpoints are reused as-is: React posts to them with axios and shows toasts / field errors. Redirect-based endpoints (delete, reset) are called with Inertia's router, and `flash_*` / `pop_*` session messages are shared as props and shown as toasts.
- Pages that are not converted yet keep rendering Blade; the React sidebar links to them with normal (full page) navigation.

## 4. Libraries

Tailwind CSS 3, Radix primitives (shadcn-style components written in-repo), lucide-react, TanStack Table, Recharts, Sonner, date-fns. No duplicate libraries.

## 5. Status (pages rendered by React)

| Design reference | Implemented in |
| --- | --- |
| `teal_gradient_login_page` | `pages/Auth/Login.jsx` |
| `lef_executive_school_management_dashboard` | `pages/Dashboard/Index.jsx` (capacity → real enrolment monitor; events → term dates + session exams) |
| `create_timetable_simple_minimal` + `manage_timetables_simple_minimal` | `components/app/module.jsx` pattern, used by Timetables, Classes, Sections, Subjects, Dormitories, Exams, Grades, Fees |

Also converted: Students (list, profile, admit, edit, graduated), Student payments, payment records, fee setup, marks entry.
Still Blade (with the modern skin): Users, promotions, tabulation/marksheets & printing, timetable period editor (`ttr.manage`), pins, settings, my account.

## 6. Admissions & finance configuration (2026-10-01)

Migration `2026_10_01_100000_admissions_and_finance_config` (additive only).

| Feature | Where |
| --- | --- |
| Admission form, 4 parts (Student → Enrolment & services → Parent/guardian → Review & submit), fields from the school's "Student Information Update" form | `components/students/student-form.jsx`, `StudentRecordController@store/update`, `Requests/Student/AdmissionRules` (only applied when the React form sends `form_version=2`; the classic Blade form is unchanged) |
| Date of admission dd/mm/yyyy | `student_records.admission_date`; `year_admitted` is derived from it |
| Class capacity "CLASS 6 A (18/24)" | `sections.capacity`, Sections page, `components/students/section-picker.jsx` |
| New parent during admission | Creates a `parent` user (username `CODE/PARENT/Y/m/####`, password `parent`) + `parent_details` (father, mother, guardian) |
| Optional services (feeding, bus in/out/both by location, extra-curricular, books) | `fee_options`, `bus_routes`, `optional_fee_charges`, `optional_fee_receipts`; `App\Support\Fees` |
| Finance configuration | `/finance/config` (`FinanceConfigController`, `pages/Finance/Config.jsx`) |
| School-fee breakdown + new/continuing students | `payment_items`, `payments.student_category`; Fee setup form. Class billing (`select_class`) and admission billing respect the category |
| Two invoices (school fees, optional fees), per-line payments, accumulated balance | `components/fees/fee-statement.jsx` on the student profile and Payments invoice |
| Admission notices: email (letter + school policy attachment, fees link) and SMS | `App\Support\Notices`, `App\Mail\*`, `config/sms.php` (`SMS_DRIVER=log|arkesel|hubtel`), log in `notification_logs`, resend from the profile's Guardian tab |
| Parent fees link (no login, signed, 90 days) | `/fees/statement/{student}`, `pages/Public/FeeStatement.jsx` |
| Uploaded photos not showing | `php artisan storage:link` (was missing) + photo URLs rebuilt against the current host (`Qs::localAsset`, `photoUrl()`) |

Not built: online card/MoMo payment (no gateway account); email/SMS need real credentials in `.env`.

### Finance (2026-10-01, part 2) — migration `2026_10_01_200000_discounts_and_cashflow`

| Feature | Where |
| --- | --- |
| Tuition discounts (Director's special package 100%, 50%, 20%) | `fee_discounts`, `student_records.fee_discount_id`, `payment_records.discount`; `Fees::applyDiscount` (tuition items, or the whole fee if not itemised). Owed = payment amount − discount (also in `pay_now`). Finance configuration → Discounts; chosen on admission/edit |
| Finance dashboard | `/finance` (`FinanceDashboardController`, `App\Support\FinanceSummary`): cash position, income/expenses per period, fees billed/paid/due by class type → class, services, 12-month chart |
| Income & expenses | `/finance/transactions` (`finance_transactions`) |
| Feeding / Bus / Extra-curricular / Books rosters | `/finance/services/{group}` (`ServiceRosterController`, `pages/Finance/Services.jsx`), client-side filters, cards recompute |
| Single-payment receipts | `/receipts/{school|optional}/{id}` view, `/pdf`, `/send` (email with PDF + SMS); `ReceiptController`, `Notices::sendReceipt` |
| Class monitor | capacity card (enrolled/capacity) and per-section capacity on the dashboard |
| PDFs | `barryvdh/laravel-dompdf` 0.8 did not run on PHP 8 and newer versions need Laravel 9; replaced by `dompdf/dompdf` 3 via `App\Support\Pdf` (aliased `PDF`). Logos in PDFs need the PHP `gd` extension |

### Terms, shop, fee breakdown, families (2026-10-02) — migration `2026_10_02_100000_terms_inventory`

| Feature | Where |
| --- | --- |
| Terms | `payments.term` (1 Sep–Dec, 2 Jan–Apr, 3 May–Aug), chosen on the fee setup form. `FinancePeriod` accepts `term` (current) and `term:YYYY-YYYY:N` (in every period picker) |
| Total invoiced | `FinanceSummary::invoiced` (school fees net of discounts, plus optional services/sales with term 1). The ledger shows it in place of the opening balance for term/school-year periods; the finance dashboard is unchanged (opening balance) |
| Fee breakdown | `/finance/fee-breakdown` (`FeeBreakdownController`): a term-by-term table grouped by school year (`FinanceSummary::termly`: invoiced, received, still owed, expenses, term balance = received − expenses, cash at end; click a term to open its items), then fee items (`FinanceSummary::itemBreakdown`): every fee item (Tuition, P.T.A, Medicals, Uniform …) billed / paid / owed, per student and parent, filtered by year, term, class and status, CSV export. Part payments are shared across a bill's items in proportion; discounts come off tuition. |
| Sales & inventory | `/finance/sales` (`SalesController`): items with stock and reorder levels, restock (optional "Inventory purchases" expense), sell to a student (charged as optional group `sales`, pay now or later), hand-down from a sibling (no charge, no stock), return unpaid sales. `inventory_items`, `stock_movements`, `optional_fee_charges.inventory_item_id/qty/handed_down_from`. Books are no longer a fee option; shop items can still be picked on the admission form |
| Full classes | Full sections can still be chosen; the dashboard shows `24/24 +1` and "Full · 1 over capacity" |
| Families | Student profile lists brothers and sisters (same parent) with class; `/users/{id}` for a parent renders `Users/Parent` (children, class, status, what each owes, family totals) |

### Remaining pages converted (2026-10-02)

Users (list, create, edit, staff profile, parent page), Settings, My account, Exam pins (list, generate, enter pin), Promotions (promote, manage/reset), My children (parent), Results (`Marks/Sheet`: year switcher, scores, comments, skill ratings), Results tools (`Marks/Tools`: tabulation sheet, results by class, recalculate totals), Timetable builder (`Timetables/Manage`: time slots + click-a-cell grid) and timetable view. Print layouts (report card, tabulation, timetable, receipts) stay as printable Blade documents. Every sidebar link for every role now opens a React page.

Fixes found while converting: saving a staff member no longer replaces their username with a new random staff ID; demo exam pins use the real `XXXXX-XXXXX-XXXXXX` format.

Tables: every list shows 10 rows per page (`usePaged` in `components/app/data-table.jsx`, `RegistryCard`, students list `per_page` 10).

School year for finance = 1 August – 31 July (Term 1 Aug–Dec, Term 2 Jan–Apr, Term 3 May–Jul), so fees paid in late August count in the new year. Term bills: school fees by `payments.term`, optional services a third per term, shop sales by date sold. Fee breakdown term table splits **Bills** (invoiced = paid + still owed) from **Cash** (fees received, other income, expenses, term balance, cash at end).

## 7. Demo data

`php artisan db:seed --class=DemoSchoolSeeder` replaces all school data with a realistic demo school (re-run any time to reset; output is identical each run). It keeps the super admin accounts, settings and reference tables (regions, districts, nationalities, blood groups, skills).
The shop is simulated too: yearly restocks (recorded as expenses), uniforms/socks/books sold to new and continuing students, and a few books packs handed down between siblings. Fees carry their term.
Demo logins (password `cj`): `admin`, `teacher` (Class 6 Gold), `accountant`, `parent` (two children), `student` (JHS 2).
While `NOTICE_ALLOWLIST` is set in `.env`, emails/SMS only go to the contacts listed there; every other parent is logged as "not sent (demo)".

## 8. Phases

1. Audit  2. Foundation  3. Shell + login  4. Dashboard  5. Students (reference module)
6. Attendance — blocked on backend (see above)  7. Fees  8. Exams & results  9. Remaining modules.
