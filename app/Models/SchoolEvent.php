<?php

namespace App\Models;

use Eloquent;

/** A holiday, exam, mid-term exam, revision week or other event on the school calendar. */
class SchoolEvent extends Eloquent
{
    public const TYPES = ['holiday' => 'Holiday', 'exam' => 'Examination', 'midterm_exam' => 'Mid-term exams', 'revision' => 'Revision week', 'event' => 'Event'];

    protected $fillable = ['year', 'type', 'title', 'starts_on', 'ends_on'];

    protected $casts = ['starts_on' => 'date', 'ends_on' => 'date'];
}
