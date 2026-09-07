<?php

namespace App\Http\Controllers;

use App\Models\CreditTransaction;
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
                'id' => $p->id,
                'name' => $p->name,
                'description' => $p->description,
                'credits' => $p->credits,
                'period_days' => $p->period_days,
                'period_label' => $p->period_label,
                'price' => (float) $p->price,
                'badge' => $p->badge,
                'is_trial' => $p->is_trial,
                'trial_used' => $p->is_trial && in_array($p->id, $usedTrialIds),
            ]);

        $active = $request->user()
            ->activeSubscription()?->load('package');

        return Inertia::render('Packages', [
            'packages' => $packages,
            'activeSubscription' => $active ? [
                'id' => $active->id,
                'package_name' => $active->package->name,
                'credits_remaining' => $active->credits_remaining,
                'is_unlimited' => (bool) $active->is_unlimited,
                'expires_at' => $active->expires_at->toDateString(),
            ] : null,
        ]);
    }

    public function subscribe(Request $request, Package $package): RedirectResponse
    {
        abort_if(! $package->is_active, 404);

        // Only trial packages are self-service; paid packages require admin assignment
        abort_if(! $package->is_trial, 403, 'Paid packages must be assigned by a staff member. Please contact the gym.');

        $user = $request->user();

        $alreadyUsed = $user->subscriptions()->where('package_id', $package->id)->exists();
        abort_if($alreadyUsed, 403, 'You have already used the trial package.');

        $now = Carbon::now();

        $sub = UserSubscription::create([
            'user_id' => $user->id,
            'package_id' => $package->id,
            'credits_granted' => $package->credits,
            'credits_remaining' => $package->credits,
            'started_at' => $now,
            'expires_at' => $now->copy()->addDays($package->period_days),
            'status' => 'active',
            'is_unlimited' => $package->is_unlimited,
        ]);

        $user->syncCreditSummary();
        $user->refresh();

        if (! $package->is_unlimited) {
            CreditTransaction::create([
                'user_id' => $user->id,
                'user_subscription_id' => $sub->id,
                'type' => 'package_assigned',
                'amount' => $package->credits,
                'balance_after' => $user->credits,
                'reason' => "Trial package activated: {$package->name}",
            ]);
        }

        return redirect()->route('packages')
            ->with('success', "✅ {$package->name} activated! You have {$package->credits} credits.");
    }
}
