<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     * Returns null in testing so Inertia skips version-mismatch 409s.
     */
    public function version(Request $request): ?string
    {
        if (app()->environment('testing')) {
            return null;
        }

        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $activeSub = null;
        if ($user) {
            $sub = $user->activeSubscription()?->load('package');
            if ($sub) {
                $activeSub = [
                    'package_name' => $sub->package?->name,
                    'credits_remaining' => $sub->credits_remaining,
                    'is_unlimited' => (bool) $sub->is_unlimited,
                    'expires_at' => $sub->expires_at?->toDateString(),
                    'expires_soon' => $sub->expires_at?->diffInDays(now()) <= 14,
                ];
            }
        }

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user?->only('id', 'name', 'email', 'credits', 'is_admin', 'role', 'phone', 'status'),
                'active_subscription' => $activeSub,
            ],
            'flash' => [
                'success' => $request->session()->get('success'),
            ],
        ];
    }
}
