<?php

namespace App\Http\Requests\MyClass;

use Illuminate\Foundation\Http\FormRequest;

class ClassUpdate extends FormRequest
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
            'class_type_id' => 'sometimes|nullable|exists:class_types,id',
            'name' => ['required', 'string', 'min:3', function ($attr, $value, $fail) {
                $id = $this->route('id');
                $id = is_numeric($id) ? (int) $id : \App\Helpers\Qs::decodeHash($id);
                $type = \DB::table('my_classes')->where('id', $id)->value('class_type_id');
                if (\DB::table('my_classes')->where('class_type_id', $type)->where('name', $value)->where('id', '!=', $id)->exists()) {
                    $fail('A class with this name already exists for this class type.');
                }
            }],
        ];
    }
}
