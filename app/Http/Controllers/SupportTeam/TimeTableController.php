<?php

namespace App\Http\Controllers\SupportTeam;

use App\Helpers\Qs;
use App\Http\Requests\TimeTable\TSRequest;
use App\Http\Requests\TimeTable\TTRecordRequest;
use App\Http\Requests\TimeTable\TTRequest;
use App\Models\Setting;
use App\Repositories\ExamRepo;
use App\Repositories\MyClassRepo;
use App\Repositories\TimeTableRepo;
use App\Http\Controllers\Controller;
use App\Helpers\Ui;
use App\Support\ClassOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TimeTableController extends Controller
{
    protected $tt, $my_class, $exam, $year;

    public function __construct(TimeTableRepo $tt, MyClassRepo $mc, ExamRepo $exam)
    {
        $this->tt = $tt;
        $this->my_class = $mc;
        $this->exam = $exam;
        $this->year = Qs::getCurrentSession();
    }

    public function index()
    {
        $d['exams'] = $this->exam->getExam(['year' => $this->year]);
        $d['my_classes'] = $this->my_class->all();
        $d['tt_records'] = $this->tt->getAllRecords();

        return Ui::render('Timetables/Index', function () use ($d) {
            return $this->pageProps($d['tt_records']);
        }, 'pages.support_team.timetables.index', $d);
    }

    protected function pageProps($records, $editing = null): array
    {
        $periods = DB::table('time_tables')->groupBy('ttr_id')->select('ttr_id', DB::raw('count(*) as n'))->pluck('n', 'ttr_id');
        $slots = DB::table('time_slots')->groupBy('ttr_id')->select('ttr_id', DB::raw('count(*) as n'))->pluck('n', 'ttr_id');
        $isSA = Qs::userIsTeamSA();
        $classes = ClassOrder::sort($this->my_class->all()->load('class_type'));

        $rows = $records->sortByDesc('year')->map(function ($r) use ($periods, $slots, $isSA) {
            return [
                'id' => $r->id,
                'name' => $r->name,
                'class' => optional($r->my_class)->name,
                'type' => $r->exam_id ? 'exam' : 'class',
                'exam' => optional($r->exam)->name,
                'year' => $r->year,
                'periods' => (int) ($periods[$r->id] ?? 0),
                'slots' => (int) ($slots[$r->id] ?? 0),
                'updated' => optional($r->updated_at)->toIso8601String(),
                'urls' => array_filter([
                    'show' => route('ttr.show', $r->id),
                    'print' => route('ttr.print', $r->id),
                    'manage' => $isSA ? route('ttr.manage', $r->id) : null,
                    'edit' => $isSA ? route('ttr.edit', $r->id) : null,
                    'destroy' => Qs::userIsSuperAdmin() ? route('ttr.destroy', $r->id) : null,
                ]),
            ];
        })->values();

        $current = $rows->where('year', $this->year);

        return [
            'session' => $this->year,
            'canCreate' => $isSA,
            'records' => $rows,
            'summary' => [
                'periods' => (int) $current->sum('periods'),
                'classesCovered' => $current->pluck('class')->filter()->unique()->count(),
                'classes' => $classes->count(),
                'updated' => $rows->max('updated'),
            ],
            // Class options grouped by class type, like the design's <optgroup>s.
            'classGroups' => $classes->groupBy(function ($c) { return optional($c->class_type)->name ?? 'Other'; })->map(function ($g, $type) {
                return ['label' => $type, 'options' => $g->map(function ($c) { return ['id' => $c->id, 'name' => $c->name]; })->values()];
            })->values(),
            'exams' => $this->exam->getExam(['year' => $this->year])->map(function ($e) { return ['id' => $e->id, 'name' => $e->name, 'term' => $e->term]; })->values(),
            'editing' => $editing,
            'urls' => ['store' => route('ttr.store'), 'index' => route('tt.index')],
        ];
    }

    public function manage($ttr_id)
    {
        $d['ttr_id'] = $ttr_id;
        $d['ttr'] = $ttr = $this->tt->findRecord($ttr_id);
        $d['time_slots'] = $this->tt->getTimeSlotByTTR($ttr_id);
        $d['ts_existing'] = $this->tt->getExistingTS($ttr_id);
        $d['subjects'] = $this->my_class->getSubject(['my_class_id' => $ttr->my_class_id])->get();
        $d['my_class'] = $this->my_class->find($ttr->my_class_id);

        if($ttr->exam_id){
            $d['exam_id'] = $ttr->exam_id;
            $d['exam'] = $this->exam->find($ttr->exam_id);
        }

        $d['tts'] = $this->tt->getTimeTable(['ttr_id' => $ttr_id]);

        return Ui::render('Timetables/Manage', function () use ($ttr_id) { return $this->manageProps($ttr_id); }, 'pages.support_team.timetables.manage', $d);
    }

    /** Timetable builder: time slots, and the subject in each slot for each day (or exam date). */
    protected function manageProps($ttr_id, $editSlot = null): array
    {
        $ttr = $this->tt->findRecord($ttr_id);
        $isExam = (bool) $ttr->exam_id;
        $slots = $this->tt->getTimeSlotByTTR($ttr_id)->sortBy('timestamp_from')->values();
        $tts = $this->tt->getTimeTable(['ttr_id' => $ttr_id]);
        $parse = function ($t) {
            return preg_match('/^(\d{1,2}):(\d{2})\s*(AM|PM)$/i', trim((string) $t), $m) ? ['hour' => (string) (int) $m[1], 'min' => $m[2], 'meridian' => strtoupper($m[3])] : ['hour' => '', 'min' => '', 'meridian' => ''];
        };

        return [
            'record' => [
                'id' => $ttr->id, 'name' => $ttr->name, 'year' => $ttr->year, 'class' => optional($this->my_class->find($ttr->my_class_id))->name,
                'exam' => $isExam ? optional($this->exam->find($ttr->exam_id))->name : null,
            ],
            'isExam' => $isExam,
            'days' => $isExam ? $tts->pluck('exam_date')->filter()->unique()->sort()->values() : Qs::getDaysOfTheWeek(),
            'slots' => $slots->map(function ($t) use ($parse) {
                return ['id' => $t->id, 'full' => $t->full, 'from' => $parse($t->time_from), 'to' => $parse($t->time_to),
                    'urls' => ['update' => route('ts.update', $t->id), 'destroy' => route('ts.destroy', $t->id)]];
            })->values(),
            'entries' => $tts->map(function ($e) {
                return ['id' => $e->id, 'ts_id' => $e->ts_id, 'day' => $e->day, 'exam_date' => $e->exam_date, 'subject_id' => $e->subject_id, 'subject' => optional($e->subject)->name,
                    'urls' => ['update' => route('tt.update', $e->id), 'destroy' => route('tt.delete', $e->id)]];
            })->values(),
            'subjects' => $this->my_class->getSubject(['my_class_id' => $ttr->my_class_id])->get()->map(function ($x) { return ['id' => $x->id, 'name' => $x->name]; })->values(),
            'others' => $this->tt->getExistingTS($ttr_id)->map(function ($r) { return ['id' => $r->id, 'name' => $r->name]; })->values(),
            'canDelete' => Qs::userIsSuperAdmin(),
            'editSlot' => $editSlot,
            'urls' => [
                'index' => route('tt.index'), 'edit' => route('ttr.edit', $ttr->id), 'show' => route('ttr.show', $ttr->id), 'print' => route('ttr.print', $ttr->id),
                'storeSlot' => route('ts.store'), 'useSlots' => route('ts.use', $ttr->id), 'storeEntry' => route('tt.store'),
            ],
        ];
    }

    public function store(TTRequest $req)
    {
        $data = $req->all();
        $tms = $this->tt->findTimeSlot($req->ts_id);
        $d_date = $req->exam_date ?? $req->day;
        $data['timestamp_from'] = strtotime($d_date.' '.$tms->time_from);
        $data['timestamp_to'] = strtotime($d_date.' '.$tms->time_to);

        $this->tt->create($data);

        return Qs::jsonStoreOk();
    }

    public function update(TTRequest $req, $tt_id)
    {
        $data = $req->all();
        $tms = $this->tt->findTimeSlot($req->ts_id);
        $d_date = $req->exam_date ?? $req->day;
        $data['timestamp_from'] = strtotime($d_date.' '.$tms->time_from);
        $data['timestamp_to'] = strtotime($d_date.' '.$tms->time_to);

        $this->tt->update($tt_id, $data);

        return back()->with('flash_success', __('msg.update_ok'));

    }

    public function delete($tt_id)
    {
        $this->tt->delete($tt_id);
        return back()->with('flash_success', __('msg.delete_ok'));
    }

    /*********** TIME SLOTS *************/

    public function store_time_slot(TSRequest $req)
    {
        $data = $req->all();
        $data['time_from'] = $tf =$req->hour_from.':'.$req->min_from.' '.$req->meridian_from;
        $data['time_to'] = $tt = $req->hour_to.':'.$req->min_to.' '.$req->meridian_to;
        $data['timestamp_from'] = strtotime($tf);
        $data['timestamp_to'] = strtotime($tt);
        $data['full'] = $tf.' - '.$tt;

        if($tf == $tt){
            return response()->json(['msg' => __('msg.invalid_time_slot'), 'ok' => FALSE]);
        }

        $this->tt->createTimeSlot($data);
        return Qs::jsonStoreOk();
    }

    public function use_time_slot(Request $req, $ttr_id)
    {
        $this->validate($req, ['ttr_id' => 'required'], [], ['ttr_id' => 'TimeTable Record']);

        $d = [];  //  Empty Current Time Slot Before Adding New
        $this->tt->deleteTimeSlots(['ttr_id' => $ttr_id]);
        $time_slots = $this->tt->getTimeSlotByTTR($req->ttr_id)->toArray();

        foreach($time_slots as $ts){
            $ts['ttr_id'] = $ttr_id;
            $this->tt->createTimeSlot($ts);
        }

        return redirect()->route('ttr.manage', $ttr_id)->with('flash_success', __('msg.update_ok'));

    }

    public function edit_time_slot($ts_id)
    {
        $d['tms'] = $tms = $this->tt->findTimeSlot($ts_id);

        return Ui::render('Timetables/Manage', function () use ($tms) { return $this->manageProps($tms->ttr_id, (int) $tms->id); }, 'pages.support_team.timetables.time_slots.edit', $d);
    }

    public function update_time_slot(TSRequest $req, $ts_id)
    {
        $data = $req->all();
        $data['time_from'] = $tf =$req->hour_from.':'.$req->min_from.' '.$req->meridian_from;
        $data['time_to'] = $tt = $req->hour_to.':'.$req->min_to.' '.$req->meridian_to;
        $data['timestamp_from'] = strtotime($tf);
        $data['timestamp_to'] = strtotime($tt);
        $data['full'] = $tf.' - '.$tt;

        if($tf == $tt){
            return back()->with('flash_danger', __('msg.invalid_time_slot'));
        }

        $this->tt->updateTimeSlot($ts_id, $data);
        return redirect()->route('ttr.manage', $req->ttr_id)->with('flash_success', __('msg.update_ok'));
    }

    public function delete_time_slot($ts_id)
    {
        $this->tt->deleteTimeSlot($ts_id);
        return back()->with('flash_success', __('msg.delete_ok'));
    }


    /*********** RECORDS *************/

    public function edit_record($ttr_id)
    {
        $d['ttr'] = $ttr = $this->tt->findRecord($ttr_id);
        $d['exams'] = $this->exam->getExam(['year' => $ttr->year]);
        $d['my_classes'] = $this->my_class->all();

        return Ui::render('Timetables/Index', function () use ($ttr) {
            return $this->pageProps($this->tt->getAllRecords(), [
                'name' => $ttr->name,
                'my_class_id' => (string) $ttr->my_class_id,
                'exam_id' => $ttr->exam_id ? (string) $ttr->exam_id : '',
                'year' => $ttr->year,
                'url' => route('ttr.update', $ttr->id),
                'manage' => route('ttr.manage', $ttr->id),
            ]);
        }, 'pages.support_team.timetables.edit', $d);
    }

    public function show_record($ttr_id)
    {
        $d_time = [];
        $d['ttr'] = $ttr = $this->tt->findRecord($ttr_id);
        $d['ttr_id'] = $ttr_id;
        $d['my_class'] = $this->my_class->find($ttr->my_class_id);

        $d['time_slots'] = $tms = $this->tt->getTimeSlotByTTR($ttr_id);
        $d['tts'] = $tts = $this->tt->getTimeTable(['ttr_id' => $ttr_id]);

        if($ttr->exam_id){
            $d['exam_id'] = $ttr->exam_id;
            $d['exam'] = $this->exam->find($ttr->exam_id);
            $d['days'] = $days = $tts->unique('exam_date')->pluck('exam_date');
            $d_date = 'exam_date';
        }

        else{
            $d['days'] = $days = $tts->unique('day')->pluck('day');
            $d_date = 'day';
        }

        foreach ($days as $day) {
            foreach ($tms as $tm) {
                $d_time[] = ['day' => $day, 'time' => $tm->full, 'subject' => $tts->where('ts_id', $tm->id)->where($d_date, $day)->first()->subject->name ?? NULL ];
            }
        }

        $d['d_time'] = collect($d_time);

        return Ui::render('Timetables/Show', function () use ($d, $ttr) {
            return [
                'record' => ['name' => $ttr->name, 'year' => $ttr->year, 'class' => optional($d['my_class'])->name, 'exam' => isset($d['exam']) ? $d['exam']->name : null],
                'days' => collect($d['days'])->values(),
                'slots' => $d['time_slots']->sortBy('timestamp_from')->pluck('full')->values(),
                'cells' => $d['d_time']->values(),
                'urls' => array_filter([
                    'print' => route('ttr.print', $ttr->id),
                    'manage' => Qs::userIsTeamSA() ? route('ttr.manage', $ttr->id) : null,
                    'index' => route('tt.index'),
                ]),
            ];
        }, 'pages.support_team.timetables.show', $d);
    }
    public function print_record($ttr_id)
    {
        $d_time = [];
        $d['ttr'] = $ttr = $this->tt->findRecord($ttr_id);
        $d['ttr_id'] = $ttr_id;
        $d['my_class'] = $this->my_class->find($ttr->my_class_id);

        $d['time_slots'] = $tms = $this->tt->getTimeSlotByTTR($ttr_id);
        $d['tts'] = $tts = $this->tt->getTimeTable(['ttr_id' => $ttr_id]);

        if($ttr->exam_id){
            $d['exam_id'] = $ttr->exam_id;
            $d['exam'] = $this->exam->find($ttr->exam_id);
            $d['days'] = $days = $tts->unique('exam_date')->pluck('exam_date');
            $d_date = 'exam_date';
        }

        else{
            $d['days'] = $days = $tts->unique('day')->pluck('day');
            $d_date = 'day';
        }

        foreach ($days as $day) {
            foreach ($tms as $tm) {
                $d_time[] = ['day' => $day, 'time' => $tm->full, 'subject' => $tts->where('ts_id', $tm->id)->where($d_date, $day)->first()->subject->name ?? NULL ];
            }
        }

        $d['d_time'] = collect($d_time);
        $d['s'] = Setting::all()->flatMap(function($s){
            return [$s->type => $s->description];
        });

        return view('pages.support_team.timetables.print', $d);
    }

    public function store_record(TTRecordRequest $req)
    {
        $data = $req->all();
        $data['year'] = $this->year;
        $this->tt->createRecord($data);

        return Qs::jsonStoreOk();
    }

    public function update_record(TTRecordRequest $req, $id)
    {
        $data = $req->all();
        $this->tt->updateRecord($id, $data);

        return Qs::jsonUpdateOk();
    }

    public function delete_record($ttr_id)
    {
        $this->tt->deleteRecord($ttr_id);
        return back()->with('flash_success', __('msg.delete_ok'));
    }
}
