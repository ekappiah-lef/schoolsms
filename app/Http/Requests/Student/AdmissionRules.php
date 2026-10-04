<?php

namespace App\Http\Requests\Student;

/**
 * Rules for the admission form fields added on top of the original student
 * request (names, health, previous school, admission date, services and,
 * when creating, a new parent/guardian).
 */
trait AdmissionRules
{
    protected function detailRules(bool $creating): array
    {
        return [
            'first_name' => 'required|string|max:60',
            'middle_name' => 'nullable|string|max:60',
            'last_name' => 'required|string|max:60',
            'admission_date' => ($creating ? 'required' : 'nullable').'|date_format:Y-m-d|before_or_equal:today',
            'height' => 'nullable|string|max:20',
            'weight' => 'nullable|string|max:20',
            'birth_place' => 'nullable|string|max:100',
            'religion' => 'nullable|string|max:60',
            'past_school' => 'nullable|string|max:150',
            'past_school_address' => 'nullable|string|max:200',
            'past_qualification' => 'nullable|string|max:150',
            'allergies' => 'nullable|string|max:1000',
            'medical_conditions' => 'nullable|string|max:1000',
            'insurance_card_no' => 'nullable|string|max:60',
            'insurance_provider' => 'nullable|string|max:100',
            'insurance_category' => 'nullable|string|max:60',
            'insurance_coverage_limit' => 'nullable|string|max:60',
            'insurance_valid_until' => 'nullable|date_format:Y-m-d',
            'insurance_start_date' => 'nullable|date_format:Y-m-d',
            'doctor_name' => 'nullable|string|max:100',
            'doctor_hospital' => 'nullable|string|max:150',
            'doctor_contact' => 'nullable|string|max:40',
            'services' => 'nullable|json',
            'fee_discount_id' => 'nullable|exists:fee_discounts,id',
        ];
    }

    protected function parentRules(): array
    {
        $r = [
            'parent_mode' => 'required|in:existing,new,none',
            'terms_accepted' => 'accepted',
        ];

        if ($this->input('parent_mode') === 'existing') {
            $r['my_parent_id'] = 'required';
        }

        if ($this->input('parent_mode') === 'new') {
            $r += [
                'parent_primary' => 'required|in:father,mother,guardian',
                'father_name' => 'nullable|required_without_all:mother_name,guardian_name|string|max:120',
                'mother_name' => 'nullable|string|max:120',
                'guardian_name' => 'nullable|string|max:120',
                'father_phone' => 'nullable|required_without_all:mother_phone,guardian_phone|string|min:9|max:30',
                'mother_phone' => 'nullable|string|min:9|max:30',
                'guardian_phone' => 'nullable|string|min:9|max:30',
                'father_email' => 'nullable|email|max:100',
                'mother_email' => 'nullable|email|max:100',
                'guardian_relationship' => 'nullable|string|max:60',
                'guardian_address' => 'nullable|string|max:200',
                'guardian_ghana_card' => 'nullable|string|max:30',
            ];
            foreach (['father', 'mother'] as $p) {
                $r += [
                    "{$p}_ghana_card" => 'nullable|string|max:30',
                    "{$p}_occupation" => 'nullable|string|max:100',
                    "{$p}_workplace" => 'nullable|string|max:200',
                    "{$p}_residential_address" => 'nullable|string|max:200',
                    "{$p}_postal_address" => 'nullable|string|max:200',
                ];
            }
            // The primary contact must actually be filled in.
            $r[$this->input('parent_primary', 'father').'_name'] = 'required|string|max:120';
        }

        return $r;
    }

    protected function admissionAttributes(): array
    {
        return [
            'first_name' => 'First name',
            'last_name' => 'Last name',
            'admission_date' => 'Date of admission',
            'father_name' => 'Father / mother / guardian name',
            'father_phone' => 'Parent contact number',
            'guardian_name' => 'Guardian name',
            'mother_name' => 'Mother name',
            'parent_primary' => 'Main contact',
            'terms_accepted' => 'Parent/guardian agreement',
            'insurance_valid_until' => 'Insurance validity date',
            'insurance_start_date' => 'Insurance start date',
            'fee_discount_id' => 'Fee discount',
        ];
    }

    /** Full name from its parts, so the existing users.name stays the source of truth. */
    protected function composeName(array &$input): void
    {
        if (!empty($input['first_name']) || !empty($input['last_name'])) {
            $parts = array_filter(array_map('trim', [$input['first_name'] ?? '', $input['middle_name'] ?? '', $input['last_name'] ?? '']));
            $input['name'] = preg_replace('/\s+/', ' ', implode(' ', $parts));
        }
    }
}
