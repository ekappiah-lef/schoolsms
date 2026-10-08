<?php

namespace App\Http\Requests\Exam;

use App\Helpers\Qs;
use App\Models\Exam;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExamUpdate extends FormRequest
{

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
        return [
            'name' => 'required|string',
            // One exam per term in a year: its marks fill that term's column on the report sheets.
            'term' => ['required', 'integer', 'in:1,2,3', Rule::unique('exams')->where('year', Exam::find($this->route('exam'))->year ?? Qs::getCurrentSession())->ignore($this->route('exam'))],
        ];
    }

    public function messages()
    {
        return ['term.unique' => 'This year already has an exam for Term '.$this->input('term').'. Edit that exam instead, or choose another term.'];
    }

}
