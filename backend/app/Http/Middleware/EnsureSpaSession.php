<?php

namespace App\Http\Middleware;

use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

class EnsureSpaSession extends EnsureFrontendRequestsAreStateful
{
    /**
     * @param  \Illuminate\Http\Request  $request
     */
    public static function fromFrontend($request): bool
    {
        if (parent::fromFrontend($request)) {
            return true;
        }

        $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);

        if ($appHost === null || $request->getHost() !== $appHost) {
            return false;
        }

        return $request->headers->get('X-Requested-With') === 'XMLHttpRequest'
            || $request->expectsJson();
    }
}
