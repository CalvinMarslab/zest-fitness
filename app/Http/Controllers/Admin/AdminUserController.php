<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminUserController extends Controller
{
    public function index(): Response
    {
        $select = ['id', 'name', 'email', 'credits', 'is_admin', 'created_at'];

        $team = User::withCount(['bookings', 'activities'])
            ->where('is_admin', true)
            ->orderBy('name')
            ->get($select);

        $customers = User::withCount(['bookings', 'activities'])
            ->where('is_admin', false)
            ->orderBy('name')
            ->get($select);

        // Stats scoped to customers (non-admin) only
        $now              = now();
        $threeMonthsLater = now()->addMonths(3);
        $cq               = fn () => User::where('is_admin', false);

        $stats = [
            'total'         => $cq()->count(),
            'active'        => $cq()->whereHas('subscriptions', fn ($q) => $q->where('expires_at', '>', $threeMonthsLater))->count(),
            'expiring_soon' => $cq()->whereHas('subscriptions', fn ($q) => $q->where('expires_at', '>', $now)->where('expires_at', '<=', $threeMonthsLater))
                ->whereDoesntHave('subscriptions', fn ($q) => $q->where('expires_at', '>', $threeMonthsLater))
                ->count(),
            'expired'       => $cq()->whereDoesntHave('subscriptions', fn ($q) => $q->where('expires_at', '>', $now))->count(),
        ];

        return Inertia::render('Admin/Users', compact('team', 'customers', 'stats'));
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'name'     => 'sometimes|string|max:255',
            'credits'  => 'sometimes|integer|min:0',
            'is_admin' => 'sometimes|boolean',
        ]);

        $user->forceFill($data)->save();

        return back();
    }

    public function destroy(User $user): RedirectResponse
    {
        abort_if($user->id === auth()->id(), 422, 'Cannot delete yourself.');
        $user->delete();

        return back();
    }
}
