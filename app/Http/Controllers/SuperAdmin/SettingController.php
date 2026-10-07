<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Helpers\Qs;
use App\Helpers\Ui;
use App\Http\Controllers\Controller;
use App\Http\Requests\SettingUpdate;
use App\Repositories\MyClassRepo;
use App\Repositories\SettingRepo;

class SettingController extends Controller
{
    protected $setting, $my_class;

    public function __construct(SettingRepo $setting, MyClassRepo $my_class)
    {
        $this->setting = $setting;
        $this->my_class = $my_class;
    }

    public function index()
    {
         $s = $this->setting->all();
         $d['class_types'] = $this->my_class->getTypes();
         $d['s'] = $s->flatMap(function($s){
            return [$s->type => $s->description];
        });
        return Ui::render('Settings/Index', function () use ($d) {
            $s = $d['s'];
            $years = [];
            for ($y = (int) date('Y', strtotime('-3 years')); $y <= (int) date('Y', strtotime('+1 years')); $y++) {
                $years[] = ($y - 1).'-'.$y;
            }
            return [
                'settings' => collect(['system_name', 'system_title', 'current_session', 'phone', 'system_email', 'address', 'term_ends', 'term_begins', 'lock_exam', 'clearenroll_url'])
                    ->mapWithKeys(function ($k) use ($s) { return [$k => (string) ($s[$k] ?? '')]; }),
                'logo' => $s['logo'] ?? null,
                'years' => $years,
                'urls' => ['update' => route('settings.update')],
            ];
        }, 'pages.super_admin.settings', $d);
    }

    public function update(SettingUpdate $req)
    {
        $sets = $req->except('_token', '_method', 'logo');
        $sets['lock_exam'] = $sets['lock_exam'] == 1 ? 1 : 0;
        $keys = array_keys($sets);
        $values = array_values($sets);
        for($i=0; $i<count($sets); $i++){
            $this->setting->update($keys[$i], $values[$i]);
        }

        if($req->hasFile('logo')) {
            $logo = $req->file('logo');
            $f = Qs::getFileMetaData($logo);
            $f['name'] = 'logo.' . $f['ext'];
            $f['path'] = $logo->storeAs(Qs::getPublicUploadPath(), $f['name']);
            $logo_path = asset('storage/' . $f['path']);
            $this->setting->update('logo', $logo_path);
        }

        return back()->with('flash_success', __('msg.update_ok'));

    }
}
