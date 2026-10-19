<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** The HOD's approval of one subject's marks (exam, class, section, subject, year). */
class MarkApproval extends Model
{
    protected $fillable = ['exam_id', 'my_class_id', 'section_id', 'subject_id', 'year', 'status', 'submitted_by', 'submitted_at', 'reviewed_by', 'reviewed_at', 'note', 'history'];

    protected $casts = ['history' => 'array', 'submitted_at' => 'datetime', 'reviewed_at' => 'datetime'];

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }
}
