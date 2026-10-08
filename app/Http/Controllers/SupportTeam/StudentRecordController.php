<?php

namespace App\Http\Controllers\SupportTeam;

use App\Helpers\Qs;
use App\Helpers\Mk;
use App\Helpers\Ui;
use App\Http\Requests\Student\StudentRecordCreate;
use App\Http\Requests\Student\StudentRecordUpdate;
use App\Models\BloodGroup;
use App\Models\Mark;
use App\Models\NotificationLog;
use App\Models\ParentDetail;
use App\Models\Section;
use App\Models\StudentDetail;
use App\Repositories\LocationRepo;
use App\Repositories\MyClassRepo;
use App\Repositories\StudentRepo;
use App\Repositories\UserRepo;
use App\Support\ClassOrder;
use App\Support\Fees;
use App\Support\Notices;
use App\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

class StudentRecordController extends Controller
{
    protected $loc, $my_class, $user, $student;

   public function __construct(LocationRepo $loc, MyClassRepo $my_class, UserRepo $user, StudentRepo $student)
   {
       $this->middleware('teamSA', ['only' => ['edit','update', 'reset_pass', 'create', 'store', 'graduated'] ]);
       $this->middleware('super_admin', ['only' => ['destroy',] ]);
       $this->middleware('teamSAT', ['only' => ['index', 'search'] ]);

        $this->loc = $loc;
        $this->my_class = $my_class;
        $this->user = $user;
        $this->student = $student;
   }

    public function reset_pass($st_id)
    {
        $st_id = Qs::decodeHash($st_id);
        $data['password'] = Hash::make('student');
        $this->user->update($st_id, $data);
        return back()->with('flash_success', __('msg.p_reset'));
    }

    /** All students, filterable by class/section/gender, server-side paginated. */
    public function index(Request $req)
    {
        return Inertia::render('Students/Index', $this->listProps($req, 0));
    }

    /** Quick-find used by the command menu. */
    public function search(Request $req)
    {
        $term = trim((string) $req->q);
        if (mb_strlen($term) < 2) {
            return [];
        }

        return $this->baseQuery(0)
            ->where(function ($w) use ($term) {
                $w->where('u.name', 'like', "%{$term}%")->orWhere('sr.adm_no', 'like', "%{$term}%");
            })
            ->orderBy('u.name')->limit(8)->get()
            ->map(function ($r) {
                return [
                    'name' => $r->name,
                    'photo' => $r->photo,
                    'adm_no' => $r->adm_no,
                    'class' => trim($r->class_name.' '.$r->section_name),
                    'url' => route('students.show', Qs::hash($r->id)),
                ];
            });
    }

    public function create()
    {
        return Ui::render('Students/Create', function () {
            return [
                'options' => $this->formOptions(),
                'urls' => [
                    'store' => route('students.store'),
                    'index' => route('students.index'),
                ],
            ];
        }, 'pages.support_team.students.add', function () {
            $data['my_classes'] = $this->my_class->all();
            $data['parents'] = $this->user->getUserByType('parent');
            $data['dorms'] = $this->student->getAllDorms();
            $data['states'] = $this->loc->getStates();
            $data['nationals'] = $this->loc->getAllNationals();
            return $data;
        });
    }

    public function store(StudentRecordCreate $req)
    {
       $data =  $req->only(Qs::getUserRecord());
       $sr =  $req->only(Qs::getStudentData());

        $ct = $this->my_class->findTypeByClass($req->my_class_id)->code;
       /* $ct = ($ct == 'J') ? 'JSS' : $ct;
        $ct = ($ct == 'S') ? 'SS' : $ct;*/

        $data['user_type'] = 'student';
        $data['name'] = ucwords($req->name);
        $data['code'] = strtoupper(Str::random(10));
        $data['password'] = Hash::make('student');
        $data['photo'] = Qs::getDefaultUserImage();
        $adm_no = $req->adm_no;
        $data['username'] = strtoupper(Qs::getAppCode().'/'.$ct.'/'.$sr['year_admitted'].'/'.($adm_no ?: mt_rand(1000, 99999)));

        if($req->hasFile('photo')) {
            $photo = $req->file('photo');
            $f = Qs::getFileMetaData($photo);
            $f['name'] = 'photo.' . $f['ext'];
            $f['path'] = $photo->storeAs(Qs::getUploadPath('student').$data['code'], $f['name']);
            $data['photo'] = asset('storage/' . $f['path']);
        }

        // Classic (Blade) admission form: original behaviour.
        if ($req->input('form_version') !== '2') {
            $user = $this->user->create($data); // Create User

            $sr['adm_no'] = $data['username'];
            $sr['user_id'] = $user->id;
            $sr['session'] = Qs::getSetting('current_session');

            $this->student->createRecord($sr); // Create Student
            return Qs::jsonStoreOk();
        }

        $year = Qs::getSetting('current_session');
        $newParent = $req->parent_mode === 'new';
        $parentEmail = $newParent ? $this->parentAccountEmail($req) : null;
        if ($parentEmail && User::where('email', $parentEmail)->exists()) {
            $field = $req->input($req->parent_primary.'_email') === $parentEmail ? $req->parent_primary.'_email' : 'father_email';
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => [$field => ['An account already uses this email. Choose "Existing parent" and select that account instead.']],
            ], 422);
        }

        [$record, $parentLogin] = DB::transaction(function () use ($req, $data, $sr, $year, $newParent, $parentEmail) {
            $parentLogin = null;
            if ($newParent) {
                $parent = $this->createParentAccount($req, $parentEmail);
                $sr['my_parent_id'] = $parent->id;
                $parentLogin = $parent->username;
            }

            $user = $this->user->create($data); // Create User

            $sr['adm_no'] = $data['username'];
            $sr['user_id'] = $user->id;
            $sr['session'] = $year;
            $sr['admission_date'] = $req->admission_date;
            $sr['admitted_session'] = $year;
            $sr['fee_discount_id'] = $req->fee_discount_id ?: null;
            // ClearEnroll check made before admitting (school-to-school fee clearance).
            if (in_array($req->clearenroll_status, array_keys(\App\Http\Controllers\SupportTeam\ClearEnrollController::STATUSES), true)) {
                $sr['clearenroll_status'] = $req->clearenroll_status;
                $sr['clearenroll_checked_at'] = now();
            }
            $record = $this->student->createRecord($sr); // Create Student

            StudentDetail::create(['user_id' => $user->id, 'terms_accepted_at' => now()] + $req->only(StudentDetail::FIELDS));
            Fees::syncOptional($user->id, $year, (array) json_decode((string) $req->services, true));
            Fees::billSchoolFees($record, $year);

            return [$record, $parentLogin];
        });

        $notices = $req->boolean('send_notices') ? Notices::sendAdmission($record) : [];

        return response()->json([
            'ok' => true,
            'msg' => __('msg.store_ok'),
            'student_url' => route('students.show', Qs::hash($record->id)),
            'parent_login' => $parentLogin,
            'notices' => $notices,
        ]);
    }

    /** Re-send the admission email/SMS (e.g. after fixing a parent's contact details). */
    public function notify($sr_id)
    {
        $sr = $this->findAnyRecord($sr_id);
        if (!$sr) {
            return Qs::json(__('msg.rnf'), false);
        }

        $results = Notices::sendAdmission($sr);
        $sent = collect($results)->where('status', 'sent')->count();

        return response()->json([
            'ok' => true,
            'msg' => $sent ? "{$sent} of ".count($results).' notices sent.' : 'No notices could be sent. See the delivery log for details.',
            'notices' => $results,
        ]);
    }

    /** Email used for a new parent's login: the main contact's, else any parent email. */
    protected function parentAccountEmail(Request $req): ?string
    {
        foreach ([$req->parent_primary, 'father', 'mother'] as $p) {
            if ($p && $p !== 'guardian' && $req->filled("{$p}_email")) {
                return strtolower(trim($req->input("{$p}_email")));
            }
        }

        return null;
    }

    /** Parent account + father/mother/guardian details, as entered on the admission form. */
    protected function createParentAccount(Request $req, ?string $email)
    {
        $p = $req->parent_primary;
        $phone = $req->input("{$p}_phone") ?: ($req->father_phone ?: ($req->mother_phone ?: $req->guardian_phone));
        $address = $p === 'guardian' ? $req->guardian_address : $req->input("{$p}_residential_address");

        $parent = $this->user->create([
            'name' => ucwords(trim($req->input("{$p}_name"))),
            'email' => $email,
            'phone' => $phone,
            'address' => $address ?: $req->address,
            'user_type' => 'parent',
            'code' => strtoupper(Str::random(10)),
            'username' => strtoupper(Qs::getAppCode().'/PARENT/'.date('Y/m').'/'.mt_rand(1000, 9999)),
            'password' => Hash::make('parent'),
            'photo' => Qs::getDefaultUserImage(),
        ]);

        ParentDetail::create(['user_id' => $parent->id] + $req->only(ParentDetail::fields()));

        return $parent;
    }

    public function listByClass($class_id)
    {
        if (!Ui::isClassic()) {
            return redirect()->route('students.index', ['class' => $class_id]);
        }

        $data['my_class'] = $mc = $this->my_class->getMC(['id' => $class_id])->first();
        $data['students'] = $this->student->findStudentsByClass($class_id);
        $data['sections'] = $this->my_class->getClassSections($class_id);

        return is_null($mc) ? Qs::goWithDanger() : view('pages.support_team.students.list', $data);
    }

    public function graduated(Request $req)
    {
        return Ui::render('Students/Index', function () use ($req) {
            return $this->listProps($req, 1);
        }, 'pages.support_team.students.graduated', function () {
            $data['my_classes'] = $this->my_class->all();
            $data['students'] = $this->student->allGradStudents();
            return $data;
        });
    }

    public function not_graduated($sr_id)
    {
        $d['grad'] = 0;
        $d['grad_date'] = NULL;
        $d['session'] = Qs::getSetting('current_session');
        $this->student->updateRecord($sr_id, $d);

        return back()->with('flash_success', __('msg.update_ok'));
    }

    public function show(Request $req, $sr_id)
    {
        $sr_id = Qs::decodeHash($sr_id);
        if(!$sr_id){return Qs::goWithDanger();}

        // Graduated students are linked from the graduated list, so look them up too.
        $data['sr'] = $sr = $this->findAnyRecord($sr_id);
        if(!$sr){return Qs::goWithDanger();}

        /* Prevent Other Students/Parents from viewing Profile of others */
        if(Auth::user()->id != $data['sr']->user_id && !Qs::userIsTeamSAT() && !Qs::userIsMyChild($data['sr']->user_id, Auth::user()->id)){
            return redirect(route('dashboard'))->with('pop_error', __('msg.denied'));
        }
        if (!\App\Support\TeacherScope::canSeeStudent($sr)) {
            return redirect(route('students.index'))->with('pop_error', 'You can only view students in your own class.');
        }

        return Ui::render('Students/Show', function () use ($sr, $req) {
            return $this->profileProps($sr, $req->query('tab'));
        }, 'pages.support_team.students.show', $data);
    }

    public function edit($sr_id)
    {
        $sr_id = Qs::decodeHash($sr_id);
        if(!$sr_id){return Qs::goWithDanger();}

        $data['sr'] = $sr = $this->findAnyRecord($sr_id);
        if(!$sr){return Qs::goWithDanger();}

        return Ui::render('Students/Edit', function () use ($sr) {
            $u = $sr->user;
            return [
                'student' => [
                    'name' => $u->name,
                    'photo' => $u->photo,
                    'adm_no' => $sr->adm_no,
                    'values' => $this->detailValues($sr) + [
                        'admission_date' => $sr->admission_date,
                        'fee_discount_id' => $sr->fee_discount_id ? (string) $sr->fee_discount_id : '',
                        'services' => Fees::selectionFor($sr->user_id, Qs::getCurrentSession()),
                        'name' => $u->name,
                        'address' => $u->address,
                        'email' => $u->email,
                        'gender' => $u->gender,
                        'phone' => $u->phone,
                        'phone2' => $u->phone2,
                        'dob' => $u->dob,
                        'nal_id' => $u->nal_id,
                        'state_id' => $u->state_id,
                        'lga_id' => $u->lga_id,
                        'bg_id' => $u->bg_id,
                        'my_class_id' => $sr->my_class_id,
                        'section_id' => $sr->section_id,
                        'my_parent_id' => $sr->my_parent_id ? Qs::hash($sr->my_parent_id) : '',
                        'year_admitted' => $sr->year_admitted,
                        'dorm_id' => $sr->dorm_id,
                        'dorm_room_no' => $sr->dorm_room_no,
                        'house' => $sr->house,
                    ],
                    'sections' => $this->idNames($this->my_class->getClassSections($sr->my_class_id)),
                    'lgas' => $u->state_id ? $this->idNames($this->loc->getLGAs($u->state_id)) : [],
                ],
                'options' => $this->formOptions(),
                'urls' => [
                    'update' => route('students.update', Qs::hash($sr->id)),
                    'show' => route('students.show', Qs::hash($sr->id)),
                ],
            ];
        }, 'pages.support_team.students.edit', function () use ($data) {
            $data['my_classes'] = $this->my_class->all();
            $data['parents'] = $this->user->getUserByType('parent');
            $data['dorms'] = $this->student->getAllDorms();
            $data['states'] = $this->loc->getStates();
            $data['nationals'] = $this->loc->getAllNationals();
            return $data;
        });
    }

    public function update(StudentRecordUpdate $req, $sr_id)
    {
        $sr_id = Qs::decodeHash($sr_id);
        if(!$sr_id){return Qs::goWithDanger();}

        $sr = $this->findAnyRecord($sr_id);
        if(!$sr){return Qs::json(__('msg.rnf'), FALSE);}
        $d =  $req->only(Qs::getUserRecord());
        $d['name'] = ucwords($req->name);

        if($req->hasFile('photo')) {
            $photo = $req->file('photo');
            $f = Qs::getFileMetaData($photo);
            $f['name'] = 'photo.' . $f['ext'];
            $f['path'] = $photo->storeAs(Qs::getUploadPath('student').$sr->user->code, $f['name']);
            $d['photo'] = asset('storage/' . $f['path']);
        }

        $this->user->update($sr->user->id, $d); // Update User Details

        $srec = $req->only(Qs::getStudentData());

        $discountChanged = false;
        if ($req->input('form_version') === '2') {
            $srec['admission_date'] = $req->admission_date ?: null;
            $srec['fee_discount_id'] = $req->fee_discount_id ?: null;
            $discountChanged = (int) $sr->fee_discount_id !== (int) $srec['fee_discount_id'];
        }

        $this->student->updateRecord($sr_id, $srec); // Update St Rec

        /*** If Class/Section is Changed in Same Year, Delete Marks/ExamRecord of Previous Class/Section ****/
        Mk::deleteOldRecord($sr->user->id, $srec['my_class_id']);

        if ($req->input('form_version') === '2') {
            StudentDetail::updateOrCreate(['user_id' => $sr->user_id], $req->only(StudentDetail::FIELDS));
            if ($discountChanged) {
                Fees::applyDiscount($sr->fresh());
            }

            $kept = [];
            if ($req->has('services')) {
                $kept = Fees::syncOptional($sr->user_id, Qs::getCurrentSession(), (array) json_decode((string) $req->services, true));
            }
            if ($discountChanged || $req->has('services')) {
                \App\Support\ClearEnroll::studentChanged((int) $sr->user_id);
            }
            if ($kept) {
                return Qs::json(rtrim(__('msg.update_ok'), '.').'. Kept because payments were recorded: '.implode(', ', $kept).'. Reset those payments first to remove them.');
            }
        }

        return Qs::jsonUpdateOk();
    }

    public function destroy($st_id)
    {
        $st_id = Qs::decodeHash($st_id);
        if(!$st_id){return Qs::goWithDanger();}

        $sr = $this->student->getRecord(['user_id' => $st_id])->first();
        $path = Qs::getUploadPath('student').$sr->user->code;
        Storage::exists($path) ? Storage::deleteDirectory($path) : false;
        $this->user->delete($sr->user->id);

        return back()->with('flash_success', __('msg.del_ok'));
    }

    /* ---------------------------------------------------------------------
     | Presentation helpers for the React pages
     * --------------------------------------------------------------------*/

    protected function findAnyRecord($sr_id)
    {
        return $this->student->getRecord(['id' => $sr_id])->first()
            ?: $this->student->getGradRecord(['id' => $sr_id])->first();
    }

    protected function baseQuery(int $grad)
    {
        return DB::table('student_records as sr')
            ->join('users as u', 'u.id', '=', 'sr.user_id')
            ->leftJoin('my_classes as c', 'c.id', '=', 'sr.my_class_id')
            ->leftJoin('sections as s', 's.id', '=', 'sr.section_id')
            ->leftJoin('users as p', 'p.id', '=', 'sr.my_parent_id')
            ->where('sr.grad', $grad)
            // Teachers only see the students of the section(s) they are class teacher of.
            ->when(\App\Support\TeacherScope::applies(), function ($q) {
                $q->whereIn('sr.section_id', \App\Support\TeacherScope::ownSectionIds());
            })
            ->select(
                'sr.id', 'sr.user_id', 'sr.adm_no', 'sr.my_class_id', 'sr.section_id', 'sr.year_admitted', 'sr.grad_date',
                'u.name', 'u.email', 'u.phone', 'u.gender', 'u.photo',
                'c.name as class_name', 's.name as section_name', 'p.name as parent_name'
            );
    }

    protected function listProps(Request $req, int $grad): array
    {
        $filters = [
            'q' => trim((string) $req->query('q', '')),
            'class' => $req->query('class') ?: null,
            'section' => $req->query('section') ?: null,
            'gender' => $req->query('gender') ?: null,
            'sort' => in_array($req->query('sort'), ['name', 'adm_no', 'class', 'year', 'grad_date']) ? $req->query('sort') : ($grad ? 'grad_date' : 'name'),
            'dir' => $req->query('dir') === 'desc' ? 'desc' : ($req->query('dir') === 'asc' ? 'asc' : ($grad ? 'desc' : 'asc')),
            'per_page' => in_array((int) $req->query('per_page'), [10, 25, 50, 100]) ? (int) $req->query('per_page') : 10,
        ];

        $scope = $this->baseQuery($grad);
        if ($filters['class']) $scope->where('sr.my_class_id', $filters['class']);
        if ($filters['section']) $scope->where('sr.section_id', $filters['section']);
        if ($filters['q'] !== '') {
            $term = $filters['q'];
            $scope->where(function ($w) use ($term) {
                $w->where('u.name', 'like', "%{$term}%")
                    ->orWhere('sr.adm_no', 'like', "%{$term}%")
                    ->orWhere('u.email', 'like', "%{$term}%")
                    ->orWhere('p.name', 'like', "%{$term}%");
            });
        }

        // Gender breakdown for the current scope (before the gender filter itself).
        $genders = (clone $scope)->reorder()->select('u.gender', DB::raw('count(*) as total'))->groupBy('u.gender')->pluck('total', 'u.gender');

        if ($filters['gender'] === 'none') {
            $scope->whereNull('u.gender');
        } elseif ($filters['gender']) {
            $scope->where('u.gender', $filters['gender']);
        }

        $sortColumn = ['name' => 'u.name', 'adm_no' => 'sr.adm_no', 'class' => 'c.name', 'year' => 'sr.year_admitted', 'grad_date' => 'sr.grad_date'][$filters['sort']];
        $page = $scope->orderBy($sortColumn, $filters['dir'])->orderBy('u.name')
            ->paginate($filters['per_page'])->appends($req->query());

        $isSA = Qs::userIsTeamSA();
        $isSuper = Qs::userIsSuperAdmin();

        $rows = collect($page->items())->map(function ($r) use ($isSA, $isSuper, $grad) {
            $srHash = Qs::hash($r->id);
            $userHash = Qs::hash($r->user_id);

            return [
                'id' => $srHash,
                'name' => $r->name,
                'photo' => $r->photo,
                'email' => $r->email,
                'phone' => $r->phone,
                'gender' => $r->gender,
                'adm_no' => $r->adm_no,
                'class' => $r->class_name,
                'section' => $r->section_name,
                'parent' => $r->parent_name,
                'year_admitted' => $r->year_admitted,
                'grad_date' => $r->grad_date,
                'urls' => array_filter([
                    'show' => route('students.show', $srHash),
                    'marksheet' => route('marks.year_selector', $userHash),
                    'edit' => $isSA ? route('students.edit', $srHash) : null,
                    'reset_pass' => $isSA ? route('st.reset_pass', $userHash) : null,
                    'not_graduated' => ($isSA && $grad) ? route('st.not_graduated', $srHash) : null,
                    'destroy' => $isSuper ? route('students.destroy', $userHash) : null,
                ]),
            ];
        });

        return [
            'variant' => $grad ? 'graduated' : 'active',
            'students' => [
                'data' => $rows,
                'meta' => [
                    'total' => $page->total(),
                    'from' => $page->firstItem(),
                    'to' => $page->lastItem(),
                    'current_page' => $page->currentPage(),
                    'last_page' => $page->lastPage(),
                    'per_page' => $page->perPage(),
                ],
            ],
            'summary' => [
                // For the Enrolled / Graduated tabs.
                'enrolled' => (int) DB::table('student_records')->where('grad', 0)->count(),
                'graduated' => (int) DB::table('student_records')->where('grad', 1)->count(),
                'total' => (int) $genders->sum(),
                'male' => (int) ($genders['Male'] ?? 0),
                'female' => (int) ($genders['Female'] ?? 0),
                'unspecified' => (int) ($genders[''] ?? 0),
            ],
            'filters' => $filters,
            'classes' => $this->idNames(ClassOrder::sort($this->my_class->all())),
            'sections' => $this->my_class->getAllSections()->map(function ($s) {
                return ['id' => $s->id, 'name' => $s->name, 'class_id' => $s->my_class_id];
            })->values(),
            'urls' => [
                'index' => $grad ? route('students.graduated') : route('students.index'),
                'create' => $isSA ? route('students.create') : null,
                'graduated' => $isSA ? route('students.graduated') : null,
                'active' => route('students.index'),
                'promotion' => $isSA ? route('students.promotion') : null,
            ],
        ];
    }

    protected function profileProps($sr, ?string $tab): array
    {
        $sr->loadMissing(['my_class', 'section', 'dorm', 'my_parent', 'user.blood_group', 'user.nationality', 'user.state', 'user.lga']);
        $u = $sr->user;
        $srHash = Qs::hash($sr->id);
        $userHash = Qs::hash($u->id);
        $parent = $sr->my_parent;

        $canFees = Qs::userIsTeamAccount();
        // The academic admin sees what a student owes (read-only) but cannot open finance pages.
        $seesFees = $canFees || Qs::userIsAcademicAdmin();
        $resultsLocked = Mk::examIsLocked() && !Qs::userIsTeamSA();

        $props = [
            'student' => [
                'id' => $srHash,
                'name' => $u->name,
                'photo' => $u->photo,
                'adm_no' => $sr->adm_no,
                'username' => $u->username,
                'class' => optional($sr->my_class)->name,
                'section' => optional($sr->section)->name,
                'status' => $sr->grad ? 'graduated' : 'active',
                'grad_date' => $sr->grad_date,
                'gender' => $u->gender,
                'dob' => $u->dob,
                'email' => $u->email,
                'phone' => $u->phone,
                'phone2' => $u->phone2,
                'address' => $u->address,
                'blood_group' => optional($u->blood_group)->name,
                'nationality' => optional($u->nationality)->name,
                'state' => optional($u->state)->name,
                'lga' => optional($u->lga)->name,
                'year_admitted' => $sr->year_admitted,
                'session' => $sr->session,
                'house' => $sr->house,
                'dorm' => optional($sr->dorm)->name,
                'dorm_room_no' => $sr->dorm_room_no,
                'admission_date' => $sr->admission_date,
                'discount' => $sr->fee_discount_id ? optional(\App\Models\FeeDiscount::find($sr->fee_discount_id))->only(['name', 'percent']) : null,
                'details' => Qs::userIsTeamSAT() || Auth::id() === $u->id || Qs::userIsMyChild($u->id, Auth::id())
                    ? optional(StudentDetail::where('user_id', $u->id)->first())->only(StudentDetail::FIELDS)
                    : null,
            ],
            'guardian' => $parent ? [
                'name' => $parent->name,
                'photo' => $parent->photo,
                'email' => $parent->email,
                'phone' => $parent->phone,
                'phone2' => $parent->phone2,
                'address' => $parent->address,
                'url' => route('users.show', Qs::hash($parent->id)),
                // Ghana card numbers etc. are only shown to admins.
                'details' => Qs::userIsTeamSA() ? optional(ParentDetail::where('user_id', $parent->id)->first())->only(ParentDetail::fields()) : null,
            ] : null,
            // Brothers and sisters: other children of the same parent.
            'siblings' => $sr->my_parent_id ? DB::table('student_records as s')->join('users as u', 'u.id', '=', 's.user_id')
                ->leftJoin('my_classes as c', 'c.id', '=', 's.my_class_id')->leftJoin('sections as x', 'x.id', '=', 's.section_id')
                ->where('s.my_parent_id', $sr->my_parent_id)->where('s.id', '!=', $sr->id)->orderBy('s.grad')->orderBy('u.name')
                ->select('s.id', 'u.name', 'u.photo', 'u.gender', 's.grad', 'c.name as class_name', 'x.name as section_name')->get()
                ->map(function ($s) {
                    return [
                        'name' => $s->name, 'photo' => $s->photo, 'gender' => $s->gender,
                        'class' => $s->grad ? 'Graduated' : trim($s->class_name.' '.$s->section_name),
                        'url' => route('students.show', Qs::hash($s->id)),
                    ];
                })->values() : [],
            'urls' => array_filter([
                'back' => Qs::userIsTeamSAT() ? route($sr->grad ? 'students.graduated' : 'students.index') : null,
                'marksheet' => route('marks.year_selector', $userHash),
                'edit' => Qs::userIsTeamSA() ? route('students.edit', $srHash) : null,
                'reset_pass' => Qs::userIsTeamSA() ? route('st.reset_pass', $userHash) : null,
                'invoice' => $canFees ? route('payments.invoice', $userHash) : null,
                'send_invoice' => $canFees ? route('payments.send_invoice', $userHash) : null,
                'notify' => Qs::userIsTeamSA() ? route('students.notify', $srHash) : null,
                'destroy' => Qs::userIsSuperAdmin() ? route('students.destroy', $userHash) : null,
            ]),
            'tabs' => [
                'fees' => $seesFees,
                'results' => true,
            ],
            'resultsLocked' => $resultsLocked,
        ];

        if ($seesFees) {
            $props['fees'] = Fees::statement($u->id, null, $canFees);
            $props['invoice'] = Fees::termInvoice($u->id);
        }

        if (Qs::userIsTeamSA()) {
            $props['notices'] = NotificationLog::where('student_id', $u->id)->latest('id')->limit(20)->get()
                ->map(function ($n) {
                    return $n->only(['channel', 'kind', 'recipient', 'status', 'error']) + ['date' => optional($n->created_at)->toIso8601String()];
                })->values();
        }

        if (!$resultsLocked) {
            $latest = DB::table('exam_records as er')->join('exams as e', 'e.id', '=', 'er.exam_id')
                ->where('er.student_id', $u->id)->whereNotNull('er.ave')
                ->orderByDesc('er.year')->orderByDesc('e.term')
                ->select('e.name as exam', 'er.year', 'er.ave', 'er.class_ave', 'er.pos')->first();
            $props['latestResult'] = $latest ? [
                'exam' => $latest->exam,
                'year' => $latest->year,
                'average' => round((float) $latest->ave, 1),
                'class_average' => $latest->class_ave !== null ? round((float) $latest->class_ave, 1) : null,
                'position' => $latest->pos,
            ] : null;
        }

        // Full results are loaded on demand (Inertia partial reload) unless the tab is opened directly.
        $results = function () use ($u, $resultsLocked) { return $resultsLocked ? null : $this->resultProps($u->id); };
        $props['results'] = $tab === 'results' ? $results() : Inertia::lazy($results);

        return $props;
    }

    protected function resultProps(int $student_id): array
    {
        $records = DB::table('exam_records as er')
            ->join('exams as e', 'e.id', '=', 'er.exam_id')
            ->leftJoin('my_classes as c', 'c.id', '=', 'er.my_class_id')
            ->where('er.student_id', $student_id)
            ->orderByDesc('er.year')->orderByDesc('e.term')
            ->select('er.exam_id', 'er.year', 'er.total', 'er.ave', 'er.class_ave', 'er.pos', 'e.name as exam', 'e.term', 'c.name as class_name')
            ->get();

        $marks = Mark::where('student_id', $student_id)->with(['subject', 'grade'])->get()->groupBy('exam_id');

        return [
            'exams' => $records->map(function ($r) use ($marks) {
                $tex = 'tex'.$r->term;
                $subjects = ($marks[$r->exam_id] ?? collect())->filter(function ($m) { return $m->subject; })
                    ->map(function ($m) use ($tex) {
                        return [
                            'subject' => $m->subject->name,
                            'ca1' => $m->t1,
                            'ca2' => $m->t2,
                            'exam' => $m->exm,
                            'total' => $m->$tex,
                            'grade' => optional($m->grade)->name,
                            'remark' => optional($m->grade)->remark,
                            'position' => $m->sub_pos,
                        ];
                    })->sortBy('subject')->values();

                return [
                    'exam_id' => $r->exam_id,
                    'exam' => $r->exam,
                    'year' => $r->year,
                    'class' => $r->class_name,
                    'total' => $r->total,
                    'average' => $r->ave !== null ? round((float) $r->ave, 1) : null,
                    'class_average' => $r->class_ave !== null ? round((float) $r->class_ave, 1) : null,
                    'position' => $r->pos,
                    'subjects' => $subjects,
                ];
            })->values(),
        ];
    }

    protected function formOptions(): array
    {
        $year = (int) date('Y');

        return [
            'classes' => $this->idNames(ClassOrder::sort($this->my_class->all())),
            'parents' => $this->user->getUserByType('parent')->map(function ($p) {
                return ['id' => Qs::hash($p->id), 'name' => $p->name, 'hint' => $p->email ?: $p->phone];
            })->values(),
            'dorms' => $this->idNames($this->student->getAllDorms()),
            'states' => $this->idNames($this->loc->getAllStates()),
            'nationals' => $this->idNames($this->loc->getAllNationals()),
            'blood_groups' => $this->idNames(BloodGroup::all()),
            'years' => range($year, $year - 10),
            'sections' => $this->sectionOptions(),
            'services' => Fees::catalogue(),
            'discounts' => Fees::discountOptions(),
            'clearEnroll' => \App\Support\ClearEnroll::enabled() ? route('clearenroll.search') : null,
            // School fees for this session, so the form can preview what will be billed.
            'fees' => \App\Models\Payment::where('year', Qs::getCurrentSession())->with('items')->get()->map(function ($p) {
                return [
                    'class_id' => $p->my_class_id,
                    'category' => $p->student_category ?: 'all',
                    'title' => $p->title,
                    'amount' => (int) $p->amount,
                    'items' => $p->items->map(function ($i) { return ['name' => $i->name, 'amount' => (int) $i->amount]; })->values(),
                ];
            })->values(),
            'session' => Qs::getCurrentSession(),
            'urls' => [
                'sections' => route('get_class_sections', ':id'),
                'lgas' => route('get_lga', ':id'),
            ],
        ];
    }

    /** Admission-form details; names fall back to splitting the stored full name. */
    protected function detailValues($sr): array
    {
        $d = StudentDetail::where('user_id', $sr->user_id)->first();
        $values = collect(StudentDetail::FIELDS)->mapWithKeys(function ($f) use ($d) {
            $v = $d ? $d->$f : null;
            return [$f => $v instanceof \DateTimeInterface ? $v->format('Y-m-d') : ($v ?? '')];
        })->all();

        if (!$values['first_name'] && !$values['last_name']) {
            $parts = preg_split('/\s+/', trim($sr->user->name));
            $values['first_name'] = array_shift($parts) ?: '';
            $values['last_name'] = $parts ? array_pop($parts) : '';
            $values['middle_name'] = implode(' ', $parts);
        }

        return $values;
    }

    /** Sections with how many active students they hold, for "Gold (18/24)". */
    protected function sectionOptions(): array
    {
        $counts = DB::table('student_records')->where('grad', 0)
            ->groupBy('section_id')->select('section_id', DB::raw('count(*) as n'))->pluck('n', 'section_id');

        return Section::orderBy('name')->get()->map(function ($s) use ($counts) {
            return [
                'id' => $s->id,
                'name' => $s->name,
                'class_id' => $s->my_class_id,
                'enrolled' => (int) ($counts[$s->id] ?? 0),
                'capacity' => $s->capacity ? (int) $s->capacity : null,
            ];
        })->values()->all();
    }

    protected function idNames($collection): array
    {
        return collect($collection)->map(function ($m) {
            return ['id' => $m->id, 'name' => $m->name];
        })->values()->all();
    }
}
