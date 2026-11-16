<?php

namespace App\Http\Controllers\SupportTeam;

use App\Helpers\Qs;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** The remarks a grade can carry (Excellent, Very Good …): each school keeps its own list, offered when setting up grades. */
class GradeRemarkController extends Controller
{
    public function __construct()
    {
        $this->middleware('academicLead');
    }

    public function index()
    {
        return \Inertia\Inertia::render('Classes/GradeRemarks', [
            'session' => Qs::getCurrentSession(),
            'remarks' => DB::table('grade_remarks')->orderBy('id')->get()->map(function ($r) {
                return ['id' => $r->id, 'name' => $r->name, 'grades' => DB::table('grades')->where('remark', $r->name)->count()];
            })->values(),
            'urls' => ['store' => route('grade_remarks.store'), 'update' => route('grade_remarks.update', ':id'), 'destroy' => route('grade_remarks.destroy', ':id')],
        ]);
    }

    public function store(Request $req)
    {
        $now = now();
        DB::table('grade_remarks')->insert(['name' => $this->validated($req), 'created_at' => $now, 'updated_at' => $now]);

        return Qs::json('Remark added.', true);
    }

    /** Renaming a remark renames it on the grades that use it. */
    public function update(Request $req, $id)
    {
        $old = DB::table('grade_remarks')->where('id', (int) $id)->value('name');
        abort_unless($old !== null, 404);
        $name = $this->validated($req, (int) $id);
        DB::transaction(function () use ($id, $old, $name) {
            DB::table('grade_remarks')->where('id', (int) $id)->update(['name' => $name, 'updated_at' => now()]);
            DB::table('grades')->where('remark', $old)->update(['remark' => $name]);
        });

        return Qs::json('Remark saved.', true);
    }

    public function destroy($id)
    {
        $name = DB::table('grade_remarks')->where('id', (int) $id)->value('name');
        abort_unless($name !== null, 404);
        if (DB::table('grades')->where('remark', $name)->exists()) {
            return Qs::json('Grades still use this remark. Change them first.', false);
        }
        DB::table('grade_remarks')->where('id', (int) $id)->delete();

        return Qs::json('Remark deleted.', true);
    }

    protected function validated(Request $req, ?int $id = null): string
    {
        $req->merge(['name' => trim((string) $req->input('name'))]);

        return $req->validate(['name' => ['required', 'string', 'max:60', Rule::unique('grade_remarks', 'name')->ignore($id)]], [], ['name' => 'Remark'])['name'];
    }
}
