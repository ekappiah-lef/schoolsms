<?php

namespace App\Http\Middleware;

use App\Helpers\Qs;
use App\Helpers\Vite;
use App\Support\Navigation;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    /** Session keys the existing controllers use for one-off messages. */
    const FLASH_KEYS = [
        'flash_success' => 'success', 'pop_success' => 'success',
        'flash_info' => 'info',
        'flash_warning' => 'warning', 'pop_warning' => 'warning',
        'flash_error' => 'error', 'flash_danger' => 'error', 'pop_error' => 'error',
    ];

    public function version(Request $request)
    {
        return Vite::version();
    }

    public function handle(Request $request, Closure $next)
    {
        $response = parent::handle($request, $next);

        // An Inertia visit that ends on a page still rendered by Blade (e.g. a
        // redirect to a module not migrated yet) becomes a normal page load.
        if ($request->header('X-Inertia')
            && $request->isMethod('GET')
            && ! $response->headers->has('X-Inertia')
            && $response->getStatusCode() === 200
            && Str::contains((string) $response->headers->get('Content-Type'), 'text/html')) {
            return Inertia::location($request->fullUrl());
        }

        return $response;
    }

    public function share(Request $request)
    {
        return array_merge(parent::share($request), [
            'app' => function () {
                return [
                    'name' => Qs::getSystemName(),
                    'code' => Qs::getSetting('system_title'),
                    'session' => Qs::getCurrentSession(),
                    'classic_url' => route('ui.mode', 'classic'),
                ];
            },
            'auth' => function () {
                $user = Auth::user();
                if (! $user) {
                    return ['user' => null];
                }

                return [
                    'user' => [
                        'name' => $user->name,
                        'email' => $user->email,
                        'username' => $user->username,
                        'photo' => $user->photo,
                        'type' => $user->user_type,
                        'role' => ucwords(str_replace('_', ' ', $user->user_type)),
                        'profile_url' => self::profileUrl($user),
                        'account_url' => route('my_account'),
                    ],
                    // UI hints only — the backend enforces access on every route.
                    'can' => [
                        'teamSA' => Qs::userIsTeamSA(),
                        'teamSAT' => Qs::userIsTeamSAT(),
                        'teamAccount' => Qs::userIsTeamAccount(),
                        'superAdmin' => Qs::userIsSuperAdmin(),
                    ],
                ];
            },
            'nav' => function () {
                return Navigation::build();
            },
            'route' => function () {
                return Route::currentRouteName();
            },
            'flash' => function () use ($request) {
                $messages = [];
                foreach (self::FLASH_KEYS as $key => $type) {
                    if ($msg = $request->session()->get($key)) {
                        $messages[] = ['id' => Str::random(8), 'type' => $type, 'message' => $msg];
                    }
                }

                return $messages;
            },
        ]);
    }

    protected static function profileUrl($user): ?string
    {
        if ($user->user_type === 'student') {
            $sr = Qs::findStudentRecord($user->id);

            return $sr ? route('students.show', Qs::hash($sr->id)) : null;
        }

        return route('users.show', Qs::hash($user->id));
    }
}
