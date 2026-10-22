<?php

namespace App\Listeners;

use App\Support\ActivityLog;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;

/** Sign-ins, sign-outs and failed sign-ins go into the activity log. */
class LogSignIns
{
    public function handle($event)
    {
        if ($event instanceof Login) ActivityLog::add('Signed in', [], null, null, $event->user);
        elseif ($event instanceof Logout && $event->user) ActivityLog::add('Signed out', [], null, null, $event->user);
        elseif ($event instanceof Failed) ActivityLog::add('Failed sign-in', ['username' => mb_substr((string) ($event->credentials['username'] ?? $event->credentials['email'] ?? ''), 0, 100)], null, null, false);
    }
}
