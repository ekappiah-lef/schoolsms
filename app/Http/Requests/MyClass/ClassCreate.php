<?php

namespace App\Http\Requests\MyClass;

use Illuminate\Foundation\Http\FormRequest;

class ClassCreate extends FormRequest
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
            'name' => ['required', 'string', 'min:3', \Illuminate\Validation\Rule::unique('my_classes')->where('class_type_id', $this->class_type_id)],
            'class_type_id' => 'required|exists:class_types,id',
        ];
    }

    public function attributes()
    {
        return  [
            'class_type_id' => 'Class Type',
        ];
    }

    public function messages()
    {
        return ['name.unique' => 'A class with this name already exists for this class type.'];
    }

}
