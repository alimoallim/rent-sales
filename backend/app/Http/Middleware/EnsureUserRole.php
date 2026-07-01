<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserRole
{
    /**
     * Module gate: rental, sales, or admin (user management).
     *
     * @param  string  ...$modules
     */
    public function handle(Request $request, Closure $next, string ...$modules): Response
    {
        $user = $request->user();

        if ($user === null) {
            abort(403, 'You do not have access to this area.');
        }

        foreach ($modules as $module) {
            $allowed = match ($module) {
                'rental' => $user->canAccessRental(),
                'sales' => $user->canAccessSales(),
                'admin' => $user->isAdmin(),
                default => false,
            };

            if ($allowed) {
                return $next($request);
            }
        }

        abort(403, 'You do not have access to this area.');
    }
}
