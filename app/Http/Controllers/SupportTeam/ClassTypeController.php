<?php

namespace App\Http\Controllers\SupportTeam;

use App\Helpers\Qs;
use App\Http\Controllers\Controller;
use App\Models\ClassType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Class types (e.g. Preschool, Lower Grade, Junior High): each school names its own. A class type has its
 * own grade bands and report-card design, and its short code goes into admission numbers (DIS/J/2026/…).
 */
class ClassTypeController extends Controller
{
    public function __construct()
    {
        $this->middleware('teamSA');
        $this->middleware('super_admin', ['only' => ['destroy']]);
    }

    public function store(Request $req)
    {
        $d = $this->validated($req);
        ClassType::create($d);

        return Qs::json('Class type added.', true);
    }

    public function update(Request $req, $id)
    {
        $type = ClassType::findOrFail($id);
        // Renaming keeps the short code (it is part of admission numbers already given).
        $type->update(['name' => $this->validated($req, $type->id)['name']]);

        return Qs::json('Class type saved.', true);
    }

    public function destroy($id)
    {
        $type = ClassType::findOrFail($id);
        if (DB::table('my_classes')->where('class_type_id', $type->id)->exists()) {
            return Qs::json('Classes still use this class type. Move them to another type first.', false);
        }
        DB::transaction(function () use ($type) {
            DB::table('grades')->where('class_type_id', $type->id)->delete();
            DB::table('report_templates')->where('class_type_id', $type->id)->delete();
            $type->delete();
        });

        return Qs::json('Class type deleted.', true);
    }

    protected function validated(Request $req, ?int $id = null): array
    {
        $req->merge(['name' => trim((string) $req->input('name'))]);
        $d = $req->validate([
            'name' => ['required', 'string', 'max:50', Rule::unique('class_types', 'name')->ignore($id)],
        ]);

        // The short code (used in admission numbers) is the initials of the name (Lower Grade → LG), made unique.
        $base = strtoupper(collect(preg_split('/\s+/', $d['name']))->map(function ($w) { return mb_substr($w, 0, 1); })->implode('')) ?: 'CT';
        $base = preg_replace('/[^A-Z0-9]/', '', $base) ?: 'CT';
        $code = $base; $n = 2;
        while (ClassType::where('code', $code)->when($id, function ($q) use ($id) { $q->where('id', '!=', $id); })->exists()) $code = $base.$n++;
        $d['code'] = $code;

        return $d;
    }
}
