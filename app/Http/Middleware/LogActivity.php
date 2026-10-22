<?php

namespace App\Http\Middleware;

use App\Support\ActivityLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** Records every change (anything but viewing) made by a signed-in user in the activity log. */
class LogActivity
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();
        $response = $next($request);
        if (!in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true) && ($user = $user ?: Auth::user())
            && !in_array(optional($request->route())->getName(), ['login', 'logout'], true)
            && !preg_match('/(^|\.|_)(select|selector|preview|search|verify)(_|$)/', (string) optional($request->route())->getName())) {
            // Refused or failed (an error status, or {"ok": false}): logged as not done, with the reason.
            $details = ActivityLog::inputOf($request);
            $action = ActivityLog::describe($request);
            $status = $response->getStatusCode();
            $json = stripos((string) $response->headers->get('content-type'), 'json') !== false ? json_decode((string) $response->getContent(), true) : null;
            if ($status >= 400 || (is_array($json) && ($json['ok'] ?? true) === false)) {
                $action .= ' (not done)';
                $why = is_array($json) ? ($json['msg'] ?? $json['message'] ?? null) : null;
                if ($why) $details['result'] = mb_substr((string) $why, 0, 300);
            }
            ActivityLog::add($action, $details, $request, $status, $user);
        }

        return $response;
    }
}
