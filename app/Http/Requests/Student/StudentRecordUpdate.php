<?php

namespace App\Http\Requests\Student;

use Illuminate\Foundation\Http\FormRequest;
use App\Helpers\Qs;

class StudentRecordUpdate extends FormRequest
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
            'gender' => 'required|string',
            'phone' => 'sometimes|nullable|string|min:6|max:20',
            'email' => 'sometimes|nullable|email|max:100|unique:users,id',
            'photo' => 'sometimes|nullable|image|mimes:jpeg,gif,png,jpg|max:2048',
            'address' => 'required|string|min:6|max:120',
            'bg_id' => 'sometimes|nullable',
            'my_class_id' => 'required',
            'section_id' => 'required',
            'state_id' => 'required',
            'lga_id' => 'required',
            'nal_id' => 'required',
            'my_parent_id' => 'sometimes|nullable',
            'dorm_id' => 'sometimes|nullable',
        ];

        // The classic (Blade) form does not send the new admission fields.
        return $this->input('form_version') === '2' ? array_merge($base, $this->detailRules(false)) : $base;
    }

    public function attributes()
    {
        return $this->admissionAttributes() + [
            'nal_id' => 'Nationality',
            'dorm_id' => 'Dormitory',
            'state_id' => 'State',
            'lga_id' => 'LGA',
            'bg_id' => 'Blood Group',
            'my_parent_id' => 'Parent',
            'my_class_id' => 'Class',
            'section_id' => 'Section',
        ];
    }

    protected function getValidatorInstance()
    {
        $input = $this->all();

        $input['my_parent_id'] = !empty($input['my_parent_id']) ? Qs::decodeHash($input['my_parent_id']) : NULL;
        $this->composeName($input);
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
