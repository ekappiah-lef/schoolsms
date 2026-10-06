<?php

namespace App\Models;

use Eloquent;

class Lga extends Eloquent
{
    protected $fillable = ['state_id', 'name'];

    public function ministry()
    {
       // return $this->hasMany(Ministry::class);
    }
}
