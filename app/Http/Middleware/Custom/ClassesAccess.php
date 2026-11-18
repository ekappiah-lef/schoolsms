<?php

namespace App\Http\Middleware\Custom;

use App\Helpers\Qs;
use Closure;
use Illuminate\Support\Facades\Auth;

/** Classes, sections and subjects: the administration team, or a staff member given these pages. */
class ClassesAccess
{
    public function handle($request, Closure $next)
    {
        return (Auth::check() && Qs::userManagesClasses()) ? $next($request) : redirect()->route('login');
    }
}
