<?php

namespace App\Http\Controllers\SupportTeam;

use App\Helpers\Qs;
use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Subject;
use App\Support\MarkApprovals;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/**
 * Departments (e.g. Science, Languages), their head (HOD) and their subjects (switch hod_marks_approval).
 * Subjects are picked by name, so "Mathematics" covers Mathematics in every class.
 */
class DepartmentController extends Controller
{
    public function __construct()
    {
        $this->middleware('teamSA');
        $this->middleware(function ($req, $next) {
            return MarkApprovals::on() ? $next($req) : redirect()->route('dashboard');
        });
    }

    public function index()
    {
        $subjects = Subject::orderBy('name')->get(['name', 'department_id']);
        $types = DB::table('class_types')->orderBy('id')->pluck('name', 'id');

        return Inertia::render('Departments/Index', [
            'byClassType' => MarkApprovals::byClassType(),
            'classTypes' => $types->map(function ($n, $id) { return ['value' => (string) $id, 'label' => $n]; })->values(),
            'departments' => Department::with('hod')->orderBy('name')->get()->map(function ($d) use ($subjects, $types) {
                return [
                    'id' => $d->id, 'name' => $d->name, 'hod_id' => $d->hod_id ? (string) $d->hod_id : '', 'hod' => optional($d->hod)->name,
                    'subjects' => $subjects->where('department_id', $d->id)->pluck('name')->unique()->values(),
                    'class_types' => collect((array) $d->class_type_ids)->map(function ($v) { return (string) $v; })->values(),
                    'class_type_names' => collect((array) $d->class_type_ids)->map(function ($v) use ($types) { return $types[$v] ?? null; })->filter()->values(),
                ];
            }),
            'subjects' => $subjects->pluck('name')->unique()->values(),
            'teachers' => User::whereIn('user_type', ['teacher', 'academic_admin'])->orderBy('name')->get(['id', 'name'])->map(function ($u) { return ['value' => (string) $u->id, 'label' => $u->name]; }),
            'urls' => ['store' => route('departments.store'), 'update' => route('departments.update', ':id'), 'destroy' => route('departments.destroy', ':id')],
        ]);
    }

    public function store(Request $req)
    {
        $d = $this->validated($req);
        DB::transaction(function () use ($d) {
            $dept = Department::create(['name' => $d['name'], 'hod_id' => $d['hod_id'] ?: null, 'class_type_ids' => $d['class_types'] ?? []]);
            if (!MarkApprovals::byClassType()) $this->assign($dept, $d['subjects'] ?? []);
        });

        return Qs::jsonStoreOk();
    }

    public function update(Request $req, $id)
    {
        $dept = Department::findOrFail($id);
        $d = $this->validated($req, $dept->id);
        DB::transaction(function () use ($dept, $d) {
            $dept->update(['name' => $d['name'], 'hod_id' => $d['hod_id'] ?: null, 'class_type_ids' => $d['class_types'] ?? []]);
            if (!MarkApprovals::byClassType()) $this->assign($dept, $d['subjects'] ?? []);
        });

        return Qs::jsonUpdateOk();
    }

    public function destroy($id)
    {
        $dept = Department::findOrFail($id);
        DB::transaction(function () use ($dept) {
            Subject::where('department_id', $dept->id)->update(['department_id' => null]);
            $dept->delete();
        });

        return Qs::json('Department deleted.', true);
    }

    protected function validated(Request $req, ?int $id = null): array
    {
        // The form sends the subject names as one comma-separated field.
        $req->merge(['subjects' => array_values(array_filter(array_map('trim', explode(',', (string) $req->input('subjects', ''))))),
            'class_types' => array_values(array_map('intval', array_filter(array_map('trim', explode(',', (string) $req->input('class_types', '')))))),
        ]);

        return $req->validate([
            'name' => 'required|string|max:100|unique:departments,name'.($id ? ','.$id : ''),
            'hod_id' => 'nullable|exists:users,id',
            'subjects' => 'array',
            'subjects.*' => 'string|max:100',
            'class_types' => 'array',
            'class_types.*' => 'integer|exists:class_types,id',
        ], [], ['hod_id' => 'head of department']);
    }

    /** The department gets exactly these subjects (by name, in every class); a subject belongs to one department. */
    protected function assign(Department $dept, array $names): void
    {
        Subject::where('department_id', $dept->id)->whereNotIn('name', $names ?: [''])->update(['department_id' => null]);
        if ($names) {
            Subject::whereIn('name', $names)->update(['department_id' => $dept->id]);
        }
    }
}
