<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Package;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminPackageController extends Controller
{
    public function index(): Response
    {
        $packages = Package::withCount('subscriptions')
            ->orderBy('sort_order')
            ->get();

        return Inertia::render('Admin/Packages', ['packages' => $packages]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
            'credits' => 'required|integer|min:0',
            'period_days' => 'required|integer|min:1',
            'price' => 'required|numeric|min:0',
            'badge' => 'nullable|string|max:50',
            'is_active' => 'boolean',
            'is_unlimited' => 'boolean',
            'weekly_booking_limit' => 'nullable|integer|min:1|max:255',
            'sort_order' => 'integer|min:0',
        ]);

        Package::create($this->normalizePackageCredits($data));

        return back();
    }

    public function update(Request $request, Package $package): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'sometimes|string|max:100',
            'description' => 'nullable|string|max:500',
            'credits' => 'sometimes|integer|min:0',
            'period_days' => 'sometimes|integer|min:1',
            'price' => 'sometimes|numeric|min:0',
            'badge' => 'nullable|string|max:50',
            'is_active' => 'boolean',
            'is_unlimited' => 'boolean',
            'weekly_booking_limit' => 'nullable|integer|min:1|max:255',
            'sort_order' => 'integer|min:0',
        ]);

        $package->update($this->normalizePackageCredits($data, $package));

        return back();
    }

    public function destroy(Package $package): RedirectResponse
    {
        $package->delete();

        return back();
    }

    /**
     * Enforce canonical unlimited representation server-side:
     * is_unlimited=true → credits forced to 0 (never 999 or any other value).
     * is_unlimited=false → credits must be >= 1 (finite packages need at least one credit).
     *
     * The $existing package is used to resolve the is_unlimited state when a
     * partial update does not include the is_unlimited field.
     */
    private function normalizePackageCredits(array $data, ?Package $existing = null): array
    {
        $isUnlimited = array_key_exists('is_unlimited', $data)
            ? (bool) $data['is_unlimited']
            : (bool) ($existing?->is_unlimited ?? false);

        if ($isUnlimited) {
            $data['credits'] = 0;
        } elseif (array_key_exists('credits', $data) && (int) $data['credits'] < 1) {
            $data['credits'] = 1;
        }

        return $data;
    }
}
