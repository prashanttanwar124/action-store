<?php

namespace App\Support;

use Illuminate\Http\Request;

class PosRequest
{
    /**
     * Determine whether an incoming HTTP request targets the In-Store POS module.
     */
    public static function matches(Request $request): bool
    {
        if ($request->routeIs('pos.*')) {
            return true;
        }

        $posDomain = trim((string) config('pos.domain', ''));
        if ($posDomain !== '') {
            return strcasecmp($request->getHost(), $posDomain) === 0;
        }

        return $request->is('pos') || $request->is('pos/*');
    }
}
