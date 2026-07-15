<?php

namespace App\Http\Controllers;

use App\Models\Package;
use App\Models\UserSubscription;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class PackageController extends Controller
{
    public function index(Request $request): Response
    {
        // IDs of trial packages this user has already purchased
        $usedTrialIds = $request->user()
            ->subscriptions()
            ->whereHas('package', fn ($q) => $q->where('is_trial', true))
            ->pluck('package_id')
            ->all();

        $packages = Package::where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->map(fn ($p) => [
                'id'           => $p->id,
                'name'         => $p->name,
                'description'  => $p->description,
                'credits'      => $p->credits,
                'period_days'  => $p->period_days,
                'period_label' => $p->period_label,
                'price'        => (float) $p->price,
                'badge'        => $p->badge,
                'is_trial'     => $p->is_trial,
                'trial_used'   => $p->is_trial && in_array($p->id, $usedTrialIds),
            ]);

        $active = $request->user()
            ->activeSubscription()?->load('package');

        return Inertia::render('Packages', [
            'packages'           => $packages,
            'activeSubscription' => $active ? [
                'id'           => $active->id,
                'package_name' => $active->package->name,
                'credits'      => $active->credits_granted,
                'expires_at'   => $active->expires_at->toDateString(),
            ] : null,
        ]);
    }

    public function subscribe(Request $request, Package $package): RedirectResponse
    {
        abort_if(! $package->is_active, 404);

        $user = $request->user();

        // Trial packages can only be purchased once per user
        if ($package->is_trial) {
            $alreadyUsed = $user->subscriptions()->where('package_id', $package->id)->exists();
            abort_if($alreadyUsed, 403, 'You have already used the trial package.');
        }

        $now   = Carbon::now();

        // Create subscription record
        UserSubscription::create([
            'user_id'         => $user->id,
            'package_id'      => $package->id,
            'credits_granted' => $package->credits,
            'started_at'      => $now,
            'expires_at'      => $now->copy()->addDays($package->period_days),
        ]);

        // Top up user credits
        $user->increment('credits', $package->credits);

        return redirect()->route('packages')
            ->with('success', "✅ {$package->name} activated! {$package->credits} credits added.");
    }
}
