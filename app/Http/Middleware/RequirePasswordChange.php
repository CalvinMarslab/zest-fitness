<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequirePasswordChange
{
    /**
     * Routes accessible while must_change_password=true.
     * Everything else redirects to password.change.
     */
    private const ALLOWED_ROUTES = [
        'password.change',
        'password.change.update',
        'logout',
        'password.request',
        'password.email',
        'password.reset',
        'password.store',
        'verification.notice',
        'verification.verify',
        'verification.send',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->isMember() && $user->must_change_password) {
            $routeName = $request->route()?->getName();

            if (! in_array($routeName, self::ALLOWED_ROUTES, true)) {
                return redirect()->route('password.change');
            }
        }

        return $next($request);
    }
}
