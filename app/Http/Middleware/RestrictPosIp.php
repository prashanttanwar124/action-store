<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RestrictPosIp
{
    /**
     * Restrict POS access to configured store IP addresses when POS_ALLOWED_IPS is set.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var array<int, string> $allowedIps */
        $allowedIps = config('pos.allowed_ips', []);

        if ($allowedIps !== [] && ! in_array((string) $request->ip(), $allowedIps, true)) {
            abort(403, 'POS terminal access is restricted to authorized store network IP addresses.');
        }

        return $next($request);
    }
}
