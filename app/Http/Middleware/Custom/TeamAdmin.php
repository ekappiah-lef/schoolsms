<?php

namespace App\Http\Middleware\Custom;

use App\Helpers\Qs;
use Closure;
use Illuminate\Support\Facades\Auth;

/** Admins and super admins only (not the academic admin). */
class TeamAdmin
{
    public function handle($request, Closure $next)
    {
        return (Auth::check() && Qs::userIsTeamAdmin()) ? $next($request) : redirect()->route('login');
    }
}
