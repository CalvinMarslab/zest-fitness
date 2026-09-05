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
            'credits' => 'required|integer|min:1',
            'period_days' => 'required|integer|min:1',
            'price' => 'required|numeric|min:0',
            'badge' => 'nullable|string|max:50',
            'is_active' => 'boolean',
            'is_unlimited' => 'boolean',
            'sort_order' => 'integer|min:0',
        ]);

        Package::create($data);

        return back();
    }

    public function update(Request $request, Package $package): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'sometimes|string|max:100',
            'description' => 'nullable|string|max:500',
            'credits' => 'sometimes|integer|min:1',
            'period_days' => 'sometimes|integer|min:1',
            'price' => 'sometimes|numeric|min:0',
            'badge' => 'nullable|string|max:50',
            'is_active' => 'boolean',
            'is_unlimited' => 'boolean',
            'sort_order' => 'integer|min:0',
        ]);

        $package->update($data);

        return back();
    }

    public function destroy(Package $package): RedirectResponse
    {
        $package->delete();

        return back();
    }
}
