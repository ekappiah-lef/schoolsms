<?php

namespace App\Models;

use Eloquent;

class StudentDetail extends Eloquent
{
    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $dates = ['terms_accepted_at'];

    public const FIELDS = [
        'first_name', 'middle_name', 'last_name', 'height', 'weight', 'birth_place', 'religion',
        'past_school', 'past_school_address', 'past_qualification', 'allergies', 'medical_conditions',
        'insurance_card_no', 'insurance_provider', 'insurance_category', 'insurance_coverage_limit',
        'insurance_valid_until', 'insurance_start_date', 'doctor_name', 'doctor_hospital', 'doctor_contact',
    ];
}
