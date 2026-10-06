<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    const STATUSES = ['present' => 'Present', 'absent' => 'Absent', 'late' => 'Late'];

    protected $fillable = ['student_id', 'my_class_id', 'section_id', 'date', 'status', 'note', 'recorded_by', 'alerted_at', 'year'];

    protected $casts = ['date' => 'date', 'alerted_at' => 'datetime'];
}
