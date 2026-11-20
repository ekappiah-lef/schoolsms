<?php

namespace App\Http\Middleware\Custom;

use App\Helpers\Qs;
use App\Support\TeacherScope;
use Closure;
use Illuminate\Support\Facades\Auth;

/** Timetables: the academic leads (every class), and department heads (their department's classes). */
class TimetableEditor
{
    public function handle($request, Closure $next)
    {
        return (Auth::check() && (Qs::userIsAcademicLead() || TeacherScope::isDepartmentHead())) ? $next($request) : redirect()->route('login');
    }
}
