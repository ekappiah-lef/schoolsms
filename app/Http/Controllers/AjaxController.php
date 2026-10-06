<?php

namespace App\Http\Controllers;

use App\Helpers\Qs;
use App\Repositories\LocationRepo;
use App\Repositories\MyClassRepo;
use Illuminate\Support\Facades\Auth;

class AjaxController extends Controller
{
    protected $loc, $my_class;

    public function __construct(LocationRepo $loc, MyClassRepo $my_class)
    {
        $this->loc = $loc;
        $this->my_class = $my_class;
    }

    public function get_lga($state_id)
    {
//        $state_id = Qs::decodeHash($state_id);
//        return ['id' => Qs::hash($q->id), 'name' => $q->name];

        $lgas = $this->loc->getLGAs($state_id);
        return $data = $lgas->map(function($q){
            return ['id' => $q->id, 'name' => $q->name];
        })->all();
    }

    public function get_class_sections($class_id)
    {
        $sections = $this->my_class->getClassSections($class_id);
        return $sections = $sections->map(function($q){
            return ['id' => $q->id, 'name' => $q->name];
        })->all();
    }

    public function get_class_subjects($class_id)
    {
        $sections = $this->my_class->getClassSections($class_id);
        $subjects = $this->my_class->findSubjectByClass($class_id);

        // Teachers: their own section (all subjects) and, as subject teacher, only the subjects they teach.
        $bySection = null;
        if (\App\Support\TeacherScope::applies()) {
            $bySection = \App\Support\TeacherScope::markAccess((int) $class_id);
            $sections = $sections->filter(function ($s) use ($bySection) { return isset($bySection[$s->id]); });
            $allowed = collect($bySection)->flatten()->unique()->all();
            $subjects = $subjects->filter(function ($s) use ($allowed) { return in_array((int) $s->id, $allowed, true); });
        }

        $d['sections'] = $sections->map(function($q){
            return ['id' => $q->id, 'name' => $q->name];
        })->values()->all();
        if ($bySection !== null) {
            $d['bySection'] = $bySection;
        }
        $d['subjects'] = $subjects->map(function($q){
            return ['id' => $q->id, 'name' => $q->name];
        })->values()->all();

        return $d;
    }

}
