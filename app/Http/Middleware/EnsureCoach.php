<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCoach
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || (! $user->isCoach() && ! $user->isAdmin())) {
            abort(403, 'Coach access required.');
        }

        return $next($request);
    }
}
