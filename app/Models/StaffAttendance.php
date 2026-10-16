<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StaffAttendance extends Model
{
    protected $fillable = ['user_id', 'date', 'clock_in', 'clock_out', 'source', 'device', 'note', 'recorded_by'];

    protected $casts = ['date' => 'date'];

    /**
     * A punch from a device: the first of the day is the clock in, any later one becomes the clock out.
     * Returns the day's record, or null when no staff member has this clock-in ID.
     */
    public static function punch(string $clockId, \Carbon\Carbon $at, ?string $device = null): ?self
    {
        $userId = StaffRecord::where('clock_id', trim($clockId))->value('user_id');
        if (!$userId) {
            return null;
        }
        $row = self::firstOrNew(['user_id' => $userId, 'date' => $at->toDateString()]);
        $time = $at->format('H:i:s');
        if (!$row->clock_in || $time < $row->clock_in) {
            // An earlier punch arriving late (devices resend) moves the clock in; the old one may be the clock out.
            if ($row->clock_in && (!$row->clock_out || $row->clock_in > $row->clock_out)) $row->clock_out = $row->clock_in;
            $row->clock_in = $time;
        } elseif ($time > $row->clock_in && (!$row->clock_out || $time > $row->clock_out)) {
            $row->clock_out = $time;
        }
        $row->device = $device ?: $row->device;
        $row->source = $row->source ?: 'device';
        $row->save();

        return $row;
    }
}
