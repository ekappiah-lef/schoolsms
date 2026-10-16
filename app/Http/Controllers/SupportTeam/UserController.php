<?php

namespace App\Http\Controllers\SupportTeam;

use App\Helpers\Qs;
use App\Helpers\Ui;
use App\Http\Requests\UserRequest;
use App\Models\ParentDetail;
use App\Models\StudentRecord;
use App\Support\Fees;
use App\Repositories\LocationRepo;
use App\Repositories\MyClassRepo;
use App\Repositories\UserRepo;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;


class UserController extends Controller
{
    protected $user, $loc, $my_class;

    public function __construct(UserRepo $user, LocationRepo $loc, MyClassRepo $my_class)
    {
        // Admins manage every user; the academic admin manages teachers only (checked in each action).
        $this->middleware('teamSA', ['only' => ['index', 'staff', 'store', 'edit', 'update', 'reset_pass'] ]);
        $this->middleware('super_admin', ['only' => ['destroy'] ]);

        $this->user = $user;
        $this->loc = $loc;
        $this->my_class = $my_class;
    }

    /** Who counts as staff of the school (super admins run the system and are not listed). */
    const STAFF_TYPES = ['teacher', 'non_teaching', 'accountant', 'academic_admin', 'admin', 'librarian'];

    /** The Staff page: teaching and non-teaching staff. The academic admin always works from it. */
    protected static function staffView(): bool
    {
        return Qs::userIsAcademicAdmin() || request()->routeIs('staff.index') || request()->query('view') === 'staff';
    }

    /** User types the signed-in user may add and edit: the academic admin, teachers and non-teaching staff only. */
    protected static function limitedTo(): ?array
    {
        return Qs::userIsAcademicAdmin() ? ['teacher', 'non_teaching'] : null;
    }

    /** User types offered when adding a user. */
    protected function typesFor($ut)
    {
        if ($allowed = self::limitedTo()) {
            return $ut->whereIn('title', $allowed);
        }
        if (self::staffView()) {
            return $ut->whereIn('title', self::STAFF_TYPES)->when(!Qs::userIsSuperAdmin(), function ($c) { return $c->where('level', '>', 2); });
        }

        return Qs::userIsAdmin() ? $ut->where('level', '>', 2) : $ut;
    }

    /**
     * Whose password the signed-in user may set:
     * super admin: anyone (except the head super admin); admin: everyone below admin;
     * academic admin: teachers and parents only.
     */
    public static function canResetPassword($target): bool
    {
        if (!$target || Qs::headSA($target->id)) {
            return false;
        }
        if (Qs::userIsSuperAdmin()) {
            return true;
        }
        if (Qs::userIsAdmin()) {
            return !in_array($target->user_type, ['super_admin', 'admin'], true);
        }

        return Qs::userIsAcademicAdmin() && in_array($target->user_type, ['teacher', 'parent'], true);
    }

    /** Teaching and non-teaching staff, with teachers' classes, job titles and full / part time. */
    public function staff()
    {
        return $this->index();
    }

    public function index()
    {
        $ut = $this->user->getAllTypes();

        $d['user_types'] = $this->typesFor($ut);
        $d['states'] = $this->loc->getStates();
        $d['users'] = self::staffView() ? $this->user->getPTAUsers()->whereIn('user_type', self::STAFF_TYPES)->values() : $this->user->getPTAUsers();
        $d['nationals'] = $this->loc->getAllNationals();
        $d['blood_groups'] = $this->user->getBloodGroups();

        return Ui::render('Users/Index', function () use ($d) { return $this->indexProps($d['user_types'], $d['users']); }, 'pages.support_team.users.index', $d);
    }

    /** Props for the users page (create / show users), also used when editing one. */
    protected function indexProps($types, $users, $editing = null): array
    {
        $isSA = Qs::userIsSuperAdmin();
        $typeNames = $this->user->getAllTypes()->pluck('name', 'title');
        $staffView = self::staffView();
        $allowed = self::limitedTo();
        $index = $staffView ? route('staff.index') : route('users.index');

        // What each teacher is assigned to: the sections they are class teacher of, and their subjects per class.
        $teacherIds = $users->where('user_type', 'teacher')->pluck('id');
        $classTeacherOf = \DB::table('sections as s')->join('my_classes as c', 'c.id', '=', 's.my_class_id')->whereIn('s.teacher_id', $teacherIds)
            ->orderBy('c.name')->get(['s.teacher_id', 's.name as section', 'c.name as class'])->groupBy('teacher_id');
        $subjectsOf = \DB::table('subjects as sb')->join('my_classes as c', 'c.id', '=', 'sb.my_class_id')->whereIn('sb.teacher_id', $teacherIds)
            ->orderBy('c.name')->orderBy('sb.name')->get(['sb.teacher_id', 'sb.name as subject', 'c.name as class'])->groupBy('teacher_id');
        $staff = \DB::table('staff_records')->whereIn('user_id', $users->pluck('id'))->get(['user_id', 'employment_type', 'job_title'])->keyBy('user_id');

        return [
            'userTypes' => $types->map(function ($t) { return ['id' => Qs::hash($t->id), 'title' => $t->title, 'name' => $t->name]; })->values(),
            'users' => $users->map(function ($u) use ($isSA, $typeNames, $staffView, $allowed, $classTeacherOf, $subjectsOf, $staff) {
                $h = Qs::hash($u->id);
                return [
                    'id' => $h, 'name' => $u->name, 'photo' => $u->photo, 'username' => $u->username, 'phone' => $u->phone, 'email' => $u->email,
                    'type' => $u->user_type, 'type_name' => $typeNames[$u->user_type] ?? ucwords(str_replace('_', ' ', $u->user_type)),
                    'employment' => optional($staff[$u->id] ?? null)->employment_type,
                    'job_title' => optional($staff[$u->id] ?? null)->job_title,
                    'category' => $u->user_type === 'teacher' ? 'teaching' : 'non_teaching',
                    'class_teacher' => collect($classTeacherOf[$u->id] ?? [])->map(function ($s) { return trim($s->class.' '.$s->section); })->values(),
                    'subjects' => collect($subjectsOf[$u->id] ?? [])->groupBy('class')->map(function ($g, $class) { return $class.': '.$g->pluck('subject')->implode(', '); })->values(),
                    'urls' => array_filter([
                        'show' => route('users.show', $h),
                        'edit' => !$allowed || in_array($u->user_type, $allowed, true) ? route('users.edit', $h).($staffView && !Qs::userIsAcademicAdmin() ? '?view=staff' : '') : null,
                        'reset_pass' => self::canResetPassword($u) ? route('users.reset_pass', $h) : null,
                        'destroy' => $isSA && !Qs::headSA($u->id) ? route('users.destroy', $h) : null,
                    ]),
                ];
            })->values(),
            'options' => [
                'states' => $this->loc->getAllStates()->map->only(['id', 'name'])->values(),
                'nationals' => $this->loc->getAllNationals()->map->only(['id', 'name'])->values(),
                'blood_groups' => $this->user->getBloodGroups()->map->only(['id', 'name'])->values(),
            ],
            'editing' => $editing,
            'staffView' => $staffView,
            'urls' => ['store' => route('users.store'), 'index' => $index, 'lgas' => route('get_lga', ':id')],
        ];
    }

    public function edit($id)
    {
        $id = Qs::decodeHash($id);
        $d['user'] = $this->user->find($id);
        $d['states'] = $this->loc->getStates();
        $d['users'] = $this->user->getPTAUsers();
        $d['blood_groups'] = $this->user->getBloodGroups();
        $d['nationals'] = $this->loc->getAllNationals();
        $allowed = self::limitedTo();
        if (!$d['user'] || ($allowed && !in_array($d['user']->user_type, $allowed, true))) {
            return Qs::goWithDanger('users.index');
        }
        if (self::staffView()) {
            $d['users'] = $d['users']->whereIn('user_type', self::STAFF_TYPES)->values();
        }

        return Ui::render('Users/Index', function () use ($d) {
            $u = $d['user'];
            $ut = $this->user->getAllTypes();
            return $this->indexProps($this->typesFor($ut), $d['users'], [
                'url' => route('users.update', Qs::hash($u->id)),
                'name' => $u->name, 'type' => $u->user_type, 'type_name' => optional($ut->firstWhere('title', $u->user_type))->name,
                'address' => $u->address, 'email' => $u->email, 'phone' => $u->phone, 'phone2' => $u->phone2,
                'gender' => $u->gender, 'nal_id' => $u->nal_id, 'state_id' => $u->state_id, 'lga_id' => $u->lga_id, 'bg_id' => $u->bg_id,
                'emp_date' => optional($u->staff->first())->emp_date ? date('Y-m-d', strtotime($u->staff->first()->emp_date)) : '',
                'employment_type' => optional($u->staff->first())->employment_type ?: '',
                'job_title' => optional($u->staff->first())->job_title ?: '',
                'dob' => $u->dob ? date('Y-m-d', strtotime($u->dob)) : '',
                'clock_id' => optional($u->staff->first())->clock_id ?: '',
                'photo' => $u->photo, 'username' => $u->username,
                'is_staff' => in_array($u->user_type, Qs::getStaff()),
                'lgas' => $u->state_id ? $this->loc->getLGAs($u->state_id)->map->only(['id', 'name'])->values() : [],
            ]);
        }, 'pages.support_team.users.edit', $d);
    }

    /** Set a new password for a user (the person doing it types the password). */
    public function reset_pass(\Illuminate\Http\Request $req, $id)
    {
        // The route already turns the hashed id in the address into the user's id.
        $user = $this->user->find(is_numeric($id) ? (int) $id : Qs::decodeHash($id));
        if (!self::canResetPassword($user)) {
            return Qs::json(__('msg.denied'), false);
        }
        $d = $req->validate(['password' => 'required|string|min:6|max:50'], [], ['password' => 'Password']);

        $this->user->update($user->id, ['password' => Hash::make($d['password']), 'remember_token' => null]);

        return Qs::json('Password changed for '.$user->name.'.');
    }

    public function store(UserRequest $req)
    {
        $user_type = $this->user->findType($req->user_type)->title;
        if (($allowed = self::limitedTo()) && !in_array($user_type, $allowed, true)) {
            return Qs::json('You can only add teachers and non-teaching staff.', false);
        }

        if ($req->filled('clock_id') && \DB::table('staff_records')->where('clock_id', $req->clock_id)->exists()) {
            return Qs::json('Another staff member already has this clock-in ID.', false);
        }

        $data = $req->except(Qs::getStaffRecord());
        $data['name'] = ucwords($req->name);
        $data['user_type'] = $user_type;
        $data['photo'] = Qs::getDefaultUserImage();
        $data['code'] = strtoupper(Str::random(10));

        $user_is_staff = in_array($user_type, Qs::getStaff());
        $user_is_teamSA = in_array($user_type, Qs::getTeamSA());

        $staff_id = Qs::uniqueUsername(Qs::getAppCode().'/STAFF/'.date('Y/m', strtotime($req->emp_date)).'/');
        $data['username'] = $uname = ($user_is_teamSA) ? $req->username : $staff_id;

        $pass = $req->password ?: $user_type;
        $data['password'] = Hash::make($pass);

        if($req->hasFile('photo')) {
            $photo = $req->file('photo');
            $f = Qs::getFileMetaData($photo);
            $f['name'] = 'photo.' . $f['ext'];
            $f['path'] = $photo->storeAs(Qs::getUploadPath($user_type).$data['code'], $f['name']);
            $data['photo'] = asset('storage/' . $f['path']);
        }

        /* Ensure that both username and Email are not blank*/
        if(!$uname && !$req->email){
            return back()->with('pop_error', __('msg.user_invalid'));
        }

        $user = $this->user->create($data); // Create User

        /* CREATE STAFF RECORD */
        if($user_is_staff){
            $d2 = $req->only(Qs::getStaffRecord());
            $d2['user_id'] = $user->id;
            $d2['code'] = $staff_id;
            $this->user->createStaffRecord($d2);
        }

        return Qs::jsonStoreOk();
    }

    public function update(UserRequest $req, $id)
    {
        $id = Qs::decodeHash($id);

        // Redirect if Making Changes to Head of Super Admins
        if(Qs::headSA($id)){
            return Qs::json(__('msg.denied'), FALSE);
        }

        $user = $this->user->find($id);
        if (($allowed = self::limitedTo()) && !in_array(optional($user)->user_type, $allowed, true)) {
            return Qs::json('You can only edit teachers and non-teaching staff.', false);
        }

        if ($req->filled('clock_id') && \DB::table('staff_records')->where('clock_id', $req->clock_id)->where('user_id', '!=', $id)->exists()) {
            return Qs::json('Another staff member already has this clock-in ID.', false);
        }

        $user_type = $user->user_type;
        $user_is_staff = in_array($user_type, Qs::getStaff());
        $user_is_teamSA = in_array($user_type, Qs::getTeamSA());

        $data = $req->except(Qs::getStaffRecord());
        $data['name'] = ucwords($req->name);
        $data['user_type'] = $user_type;

        // Keep the login ID. (It used to be regenerated with a random number on every save,
        // which locked staff out of their accounts.)
        $data['username'] = $user->username;

        if($req->hasFile('photo')) {
            $photo = $req->file('photo');
            $f = Qs::getFileMetaData($photo);
            $f['name'] = 'photo.' . $f['ext'];
            $f['path'] = $photo->storeAs(Qs::getUploadPath($user_type).$user->code, $f['name']);
            $data['photo'] = asset('storage/' . $f['path']);
        }

        $this->user->update($id, $data);   /* UPDATE USER RECORD */

        /* UPDATE STAFF RECORD */
        if($user_is_staff){
            $d2 = $req->only(Qs::getStaffRecord());
            $this->user->updateStaffRecord(['user_id' => $id], $d2);
        }

        return Qs::jsonUpdateOk();
    }

    public function show($user_id)
    {
        $user_id = Qs::decodeHash($user_id);
        if(!$user_id){return back();}

        $data['user'] = $this->user->find($user_id);

        /* Prevent Other Students from viewing Profile of others*/
        // Teachers have no access to parents' details.
        $isParent = optional($this->user->find($user_id))->user_type === 'parent';
        if((Auth::user()->id != $user_id && !Qs::userIsTeamSAT() && !Qs::userIsMyChild(Auth::user()->id, $user_id)) || ($isParent && Qs::userIsTeacher())){
            return redirect(route('dashboard'))->with('pop_error', __('msg.denied'));
        }

        if ($data['user'] && $data['user']->user_type === 'parent') {
            return Ui::render('Users/Parent', function () use ($data) { return $this->parentProps($data['user']); }, 'pages.support_team.users.show', $data);
        }

        return Ui::render('Users/Show', function () use ($data) { return $this->showProps($data['user']); }, 'pages.support_team.users.show', $data);
    }

    /** Profile of a staff member (admin, teacher, accountant, librarian…). */
    protected function showProps($u): array
    {
        $u->loadMissing(['blood_group', 'nationality', 'state', 'lga']);
        $h = Qs::hash($u->id);
        $type = optional($this->user->getAllTypes()->firstWhere('title', $u->user_type))->name ?? ucwords(str_replace('_', ' ', $u->user_type));

        return [
            'user' => [
                'name' => $u->name, 'photo' => $u->photo, 'type' => $type, 'gender' => $u->gender, 'address' => $u->address,
                'email' => $u->email, 'username' => $u->username, 'phone' => $u->phone, 'phone2' => $u->phone2, 'dob' => $u->dob,
                'blood_group' => optional($u->blood_group)->name, 'nationality' => optional($u->nationality)->name,
                'state' => optional($u->state)->name, 'lga' => optional($u->lga)->name,
                'emp_date' => optional($u->staff->first())->emp_date,
                'employment_type' => optional($u->staff->first())->employment_type,
                'job_title' => optional($u->staff->first())->job_title,
                'staff_code' => optional($u->staff->first())->code,
            ],
            'subjects' => $u->user_type === 'teacher' ? Qs::findTeacherSubjects($u->id)->map(function ($s) {
                return ['name' => $s->name, 'class' => optional($s->my_class)->name];
            })->values() : [],
            'urls' => array_filter([
                'back' => Qs::userIsTeamAdmin() ? route('users.index') : null,
                'edit' => Qs::userIsTeamAdmin() ? route('users.edit', $h) : null,
                'reset_pass' => self::canResetPassword($u) ? route('users.reset_pass', $h) : null,
            ]),
        ];
    }

    /** A parent with all their children: class, status and what each still owes. */
    protected function parentProps($p): array
    {
        $children = StudentRecord::where('my_parent_id', $p->id)->with(['user', 'my_class', 'section'])->get()
            ->filter(function ($sr) { return $sr->user; })
            ->sortBy(function ($sr) { return [$sr->grad, $sr->user->name]; })
            ->map(function ($sr) {
                $t = Qs::userIsTeamAccount() ? Fees::statement($sr->user_id)['totals'] : null;
                return [
                    'name' => $sr->user->name, 'photo' => $sr->user->photo, 'gender' => $sr->user->gender,
                    'adm_no' => $sr->adm_no, 'class' => trim(optional($sr->my_class)->name.' '.optional($sr->section)->name),
                    'status' => $sr->grad ? 'graduated' : 'active',
                    'fees' => $t, 'url' => route('students.show', Qs::hash($sr->id)),
                    'invoice_url' => $t ? route('payments.invoice', Qs::hash($sr->user_id)) : null,
                ];
            })->values();

        return [
            'parent' => [
                'name' => $p->name, 'photo' => $p->photo, 'email' => $p->email, 'phone' => $p->phone, 'phone2' => $p->phone2,
                'address' => $p->address, 'username' => $p->username,
                'details' => Qs::userIsTeamSA() ? optional(ParentDetail::where('user_id', $p->id)->first())->only(ParentDetail::fields()) : null,
            ],
            'children' => $children,
            'totals' => $children->pluck('fees')->filter()->reduce(function ($a, $t) {
                return ['amount' => $a['amount'] + $t['amount'], 'paid' => $a['paid'] + $t['paid'], 'balance' => $a['balance'] + $t['balance']];
            }, ['amount' => 0, 'paid' => 0, 'balance' => 0]),
            'urls' => array_filter([
                'edit' => Qs::userIsTeamAdmin() ? route('users.edit', Qs::hash($p->id)) : null,
                'reset_pass' => self::canResetPassword($p) ? route('users.reset_pass', Qs::hash($p->id)) : null,
                'back' => Qs::userIsTeamSAT() ? route('users.index') : null,
            ]),
        ];
    }

    public function destroy($id)
    {
        $id = Qs::decodeHash($id);

        // Redirect if Making Changes to Head of Super Admins
        if(Qs::headSA($id)){
            return back()->with('pop_error', __('msg.denied'));
        }

        $user = $this->user->find($id);

        if($user->user_type == 'teacher' && $this->userTeachesSubject($user)) {
            return back()->with('pop_error', __('msg.del_teacher'));
        }

        $path = Qs::getUploadPath($user->user_type).$user->code;
        Storage::exists($path) ? Storage::deleteDirectory($path) : true;
        $this->user->delete($user->id);

        return back()->with('flash_success', __('msg.del_ok'));
    }

    protected function userTeachesSubject($user)
    {
        $subjects = $this->my_class->findSubjectByTeacher($user->id);
        return ($subjects->count() > 0) ? true : false;
    }

}
