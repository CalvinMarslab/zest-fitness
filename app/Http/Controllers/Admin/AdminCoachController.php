<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class AdminCoachController extends Controller
{
    public function index(): Response
    {
        $coaches = User::where('role', 'coach')
            ->withCount([
                'assignedClasses as classes_this_week' => fn ($q) => $q
                    ->where('start_time', '>=', now()->startOfWeek())
                    ->where('start_time', '<', now()->endOfWeek()),
            ])
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'phone', 'status', 'created_at']);

        return Inertia::render('Admin/Coaches', [
            'coaches' => $coaches,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'phone' => 'nullable|string|max:30',
        ]);

        $tempPassword = Str::random(12);

        User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'password' => Hash::make($tempPassword),
            'role' => 'coach',
            'status' => 'active',
            'joined_at' => now(),
        ]);

        return back()->with('success', "Coach {$data['name']} created. Temp password: {$tempPassword}");
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        abort_if($user->role !== 'coach', 404);

        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,'.$user->id,
            'phone' => 'sometimes|nullable|string|max:30',
            'status' => 'sometimes|string|in:active,suspended',
        ]);

        $user->update($data);

        return back()->with('success', 'Coach updated.');
    }

    public function destroy(User $user): RedirectResponse
    {
        abort_if($user->role !== 'coach', 404);
        abort_if($user->id === auth()->id(), 422, 'Cannot delete yourself.');

        $user->update(['status' => 'suspended', 'role' => 'member']);

        return back()->with('success', 'Coach deactivated.');
    }
}
