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
        $this->middleware('teamSA', ['only' => ['index', 'store', 'edit', 'update'] ]);
        $this->middleware('super_admin', ['only' => ['reset_pass','destroy'] ]);

        $this->user = $user;
        $this->loc = $loc;
        $this->my_class = $my_class;
    }

    /** The academic admin may only add, see and edit teachers. */
    protected static function teachersOnly(): bool
    {
        return Qs::userIsAcademicAdmin();
    }

    public function index()
    {
        $ut = $this->user->getAllTypes();
        $ut2 = $ut->where('level', '>', 2);

        $d['user_types'] = self::teachersOnly() ? $ut->where('title', 'teacher') : (Qs::userIsAdmin() ? $ut2 : $ut);
        $d['states'] = $this->loc->getStates();
        $d['users'] = self::teachersOnly() ? $this->user->getPTAUsers()->where('user_type', 'teacher')->values() : $this->user->getPTAUsers();
        $d['nationals'] = $this->loc->getAllNationals();
        $d['blood_groups'] = $this->user->getBloodGroups();

        return Ui::render('Users/Index', function () use ($d) { return $this->indexProps($d['user_types'], $d['users']); }, 'pages.support_team.users.index', $d);
    }

    /** Props for the users page (create / show users), also used when editing one. */
    protected function indexProps($types, $users, $editing = null): array
    {
        $isSA = Qs::userIsSuperAdmin();
        $typeNames = $this->user->getAllTypes()->pluck('name', 'title');

        return [
            'userTypes' => $types->map(function ($t) { return ['id' => Qs::hash($t->id), 'title' => $t->title, 'name' => $t->name]; })->values(),
            'users' => $users->map(function ($u) use ($isSA, $typeNames) {
                $h = Qs::hash($u->id);
                return [
                    'id' => $h, 'name' => $u->name, 'photo' => $u->photo, 'username' => $u->username, 'phone' => $u->phone, 'email' => $u->email,
                    'type' => $u->user_type, 'type_name' => $typeNames[$u->user_type] ?? ucwords(str_replace('_', ' ', $u->user_type)),
                    'urls' => array_filter([
                        'show' => route('users.show', $h),
                        'edit' => route('users.edit', $h),
                        'reset_pass' => $isSA && !Qs::headSA($u->id) ? route('users.reset_pass', $h) : null,
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
            'urls' => ['store' => route('users.store'), 'index' => route('users.index'), 'lgas' => route('get_lga', ':id')],
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
        if (!$d['user'] || (self::teachersOnly() && $d['user']->user_type !== 'teacher')) {
            return Qs::goWithDanger('users.index');
        }
        if (self::teachersOnly()) {
            $d['users'] = $d['users']->where('user_type', 'teacher')->values();
        }

        return Ui::render('Users/Index', function () use ($d) {
            $u = $d['user'];
            $ut = $this->user->getAllTypes();
            return $this->indexProps(self::teachersOnly() ? $ut->where('title', 'teacher') : (Qs::userIsAdmin() ? $ut->where('level', '>', 2) : $ut), $d['users'], [
                'url' => route('users.update', Qs::hash($u->id)),
                'name' => $u->name, 'type' => $u->user_type, 'type_name' => optional($ut->firstWhere('title', $u->user_type))->name,
                'address' => $u->address, 'email' => $u->email, 'phone' => $u->phone, 'phone2' => $u->phone2,
                'gender' => $u->gender, 'nal_id' => $u->nal_id, 'state_id' => $u->state_id, 'lga_id' => $u->lga_id, 'bg_id' => $u->bg_id,
                'emp_date' => optional($u->staff->first())->emp_date ? date('Y-m-d', strtotime($u->staff->first()->emp_date)) : '',
                'photo' => $u->photo, 'username' => $u->username,
                'is_staff' => in_array($u->user_type, Qs::getStaff()),
                'lgas' => $u->state_id ? $this->loc->getLGAs($u->state_id)->map->only(['id', 'name'])->values() : [],
            ]);
        }, 'pages.support_team.users.edit', $d);
    }

    public function reset_pass($id)
    {
        // Redirect if Making Changes to Head of Super Admins
        if(Qs::headSA($id)){
            return back()->with('flash_danger', __('msg.denied'));
        }

        $data['password'] = Hash::make('user');
        $this->user->update($id, $data);
        return back()->with('flash_success', __('msg.pu_reset'));
    }

    public function store(UserRequest $req)
    {
        $user_type = $this->user->findType($req->user_type)->title;
        if (self::teachersOnly() && $user_type !== 'teacher') {
            return Qs::json('You can only add teachers.', false);
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
        if (self::teachersOnly() && optional($user)->user_type !== 'teacher') {
            return Qs::json('You can only edit teachers.', false);
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
        if(Auth::user()->id != $user_id && !Qs::userIsTeamSAT() && !Qs::userIsMyChild(Auth::user()->id, $user_id)){
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
                'staff_code' => optional($u->staff->first())->code,
            ],
            'subjects' => $u->user_type === 'teacher' ? Qs::findTeacherSubjects($u->id)->map(function ($s) {
                return ['name' => $s->name, 'class' => optional($s->my_class)->name];
            })->values() : [],
            'urls' => array_filter([
                'back' => Qs::userIsTeamAdmin() ? route('users.index') : null,
                'edit' => Qs::userIsTeamAdmin() ? route('users.edit', $h) : null,
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
