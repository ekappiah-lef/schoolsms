<?php

namespace App\Http\Requests\Student;

use Illuminate\Foundation\Http\FormRequest;
use App\Helpers\Qs;

class StudentRecordCreate extends FormRequest
{
    use AdmissionRules;

    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $base = [
            'name' => 'required|string|min:6|max:150',
            'adm_no' => 'sometimes|nullable|alpha_num|min:3|max:150|unique:student_records',
            'gender' => 'required|string',
            'year_admitted' => 'required|string',
            'phone' => 'sometimes|nullable|string|min:6|max:20',
            'email' => 'sometimes|nullable|email|max:100|unique:users',
            'photo' => 'sometimes|nullable|image|mimes:jpeg,gif,png,jpg|max:2048',
            'address' => 'required|string|min:6|max:120',
            'bg_id' => 'sometimes|nullable',
            'state_id' => 'required',
            'lga_id' => 'required',
            'nal_id' => 'required',
            'my_class_id' => 'required',
            'section_id' => 'required',
            'my_parent_id' => 'sometimes|nullable',
            'dorm_id' => 'sometimes|nullable',
        ];

        // The classic (Blade) form does not send the new admission fields.
        return $this->input('form_version') === '2' ? array_merge($base, $this->detailRules(true), $this->parentRules()) : $base;
    }

    public function attributes()
    {
        return $this->admissionAttributes() + [
            'section_id' => 'Section',
            'nal_id' => 'Nationality',
            'my_class_id' => 'Class',
            'dorm_id' => 'Dormitory',
            'state_id' => 'State',
            'lga_id' => 'LGA',
            'bg_id' => 'Blood Group',
            'my_parent_id' => 'Parent',
        ];
    }

    protected function getValidatorInstance()
    {
        $input = $this->all();

        $mode = $input['parent_mode'] ?? (empty($input['my_parent_id']) ? 'none' : 'existing');
        $input['parent_mode'] = $mode;
        $input['my_parent_id'] = ($mode === 'existing' && !empty($input['my_parent_id'])) ? Qs::decodeHash($input['my_parent_id']) : NULL;
        $this->composeName($input);
        // The year in usernames/reports comes from the full admission date.
        if (!empty($input['admission_date']) && preg_match('/^(\d{4})-\d{2}-\d{2}$/', $input['admission_date'], $m)) {
            $input['year_admitted'] = $m[1];
        }

        $this->getInputSource()->replace($input);

        return parent::getValidatorInstance();
    }

    /** A nationality, state or LGA typed in (not in the list) is added before saving. */
    protected function passedValidation()
    {
        \App\Support\Locations::resolve($this);
    }
}
