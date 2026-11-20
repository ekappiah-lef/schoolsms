<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Pages seen while signed in are not kept by the browser, so Back after signing out cannot show them. */
class NoStoreWhenSignedIn
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);
        if (Auth::check() && !$response instanceof BinaryFileResponse && !$response instanceof StreamedResponse) {
            $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, private');
            $response->headers->set('Pragma', 'no-cache');
        }

        return $response;
    }
}
