<?php

namespace App\Models;

use Eloquent;

/** One term of the school calendar (SchoolCalendar). */
class SchoolTerm extends Eloquent
{
    protected $fillable = ['year', 'term', 'starts_on', 'ends_on', 'midterm_starts_on', 'midterm_ends_on'];

    protected $casts = ['term' => 'integer', 'starts_on' => 'date', 'ends_on' => 'date', 'midterm_starts_on' => 'date', 'midterm_ends_on' => 'date'];
}
