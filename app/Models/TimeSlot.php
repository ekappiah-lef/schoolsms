<?php

namespace App\Models;

use Eloquent;

class TimeSlot extends Eloquent
{
    protected $fillable = ['ttr_id', 'timestamp_from', 'timestamp_to', 'full', 'time_from', 'time_to', 'hour_from', 'min_from', 'meridian_from', 'hour_to', 'min_to', 'meridian_to', 'label', 'days', 'day_names'];

    protected $casts = ['days' => 'array', 'day_names' => 'array'];

    public function tt_record()
    {
        return $this->belongsTo(TimeTableRecord::class, 'ttr_id');
    }

    /** What the slot holds on a day: that day's own activity, or the slot's name. */
    public function nameOn(?string $day): ?string
    {
        return ($day && !empty($this->day_names[$day])) ? $this->day_names[$day] : $this->label;
    }
}
