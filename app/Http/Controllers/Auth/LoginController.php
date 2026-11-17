<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\AuthenticatesUsers;

class LoginController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles authenticating users for the application and
    | redirecting them to your home screen. The controller uses a trait
    | to conveniently provide its functionality to your applications.
    |
    */

    use AuthenticatesUsers;

    /**
     * Where to redirect users after login.
     *
     * @var string
     */
    protected $redirectTo = '/home';

    /**
     * Create a new controller instance.
     *
     * @return void$field
     */
    public function __construct()
    {
        $this->middleware('guest')->except('logout');
    }

    public function showLoginForm()
    {
        return \App\Helpers\Ui::render('Auth/Login', function () {
            return [
                'urls' => [
                    'login' => route('login'),
                    'forgot' => route('password.request'),
                ],
            ];
        }, 'auth.login');
    }

    /**
     * Sign in for this browser session only: there is no "keep me signed in", so a user is asked to log in
     * again after the browser closes or after the idle time in config/session.php.
     */
    protected function attemptLogin(\Illuminate\Http\Request $request)
    {
        return $this->guard()->attempt($this->credentials($request), false);
    }

    /** Non-teaching staff are on record for staff lists and messages; they do not sign in. */
    protected function authenticated(\Illuminate\Http\Request $request, $user)
    {
        if ($user->user_type === 'non_teaching') {
            $this->guard()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw \Illuminate\Validation\ValidationException::withMessages([$this->username() => 'This staff account does not have access to the portal.']);
        }
    }

    /**
     * What phones add to a login: spaces around the dot ("doris. sackitey"), a space for the dot ("John Kpemeora"),
     * spaces in an email ("name @gmail.com"). Usernames are dotted first.last, so a space between words is a dot.
     */
    public static function cleanIdentity(string $identity): string
    {
        $identity = trim($identity);
        if (strpos($identity, '@') !== false) return preg_replace('/\s+/', '', $identity);
        $identity = preg_replace('/\s*\.\s*/', '.', $identity);

        return preg_replace('/\s+/', '.', $identity);
    }

    /** A space copied before or after the password is not part of it. */
    protected function credentials(\Illuminate\Http\Request $request)
    {
        $c = $request->only($this->username(), 'password');
        $c['password'] = trim((string) ($c['password'] ?? ''));

        return $c;
    }

    /*
     *  Login with Username or Email
     * */
    public function username()
    {
        $identity = self::cleanIdentity((string) request()->identity);
        request()->merge(['identity' => $identity]);
        $field = filter_var($identity, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';
        request()->merge([$field => $identity]);
        return $field;
    }
}
