<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Fideloper\Proxy\TrustProxies as Middleware;

class TrustProxies extends Middleware
{
    /**
     * The trusted proxies for this application.
     *
     * @var array
     */
    protected $proxies;

    /**
     * The headers that should be used to detect proxies.
     *
     * @var int
     */
    protected $headers = Request::HEADER_X_FORWARDED_ALL;

    public function handle(Request $request, \Closure $next)
    {
        // Set from TRUSTED_PROXIES (config/app.php), e.g. "*" when served behind a reverse proxy.
        if ($proxies = config('app.trusted_proxies')) {
            $this->proxies = $proxies === '*' ? '*' : array_map('trim', explode(',', $proxies));
        }

        return parent::handle($request, $next);
    }
}
