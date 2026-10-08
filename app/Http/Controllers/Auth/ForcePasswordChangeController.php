<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class ForcePasswordChangeController extends Controller
{
    public function show(Request $request): Response|RedirectResponse
    {
        if (! $request->user()->must_change_password) {
            return redirect()->intended(route('schedule', absolute: false));
        }

        return Inertia::render('Auth/ChangePassword');
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => [
                'required',
                'confirmed',
                Password::min(8)->mixedCase()->numbers(),
            ],
        ]);

        $user = $request->user();

        $user->forceFill([
            'password' => Hash::make($request->password),
            'must_change_password' => false,
            'remember_token' => Str::random(60),
        ])->save();

        // Invalidate all other active sessions for this user
        DB::table('sessions')
            ->where('user_id', $user->id)
            ->where('id', '!=', $request->session()->getId())
            ->delete();

        $request->session()->regenerate();

        $home = $user->isAdmin()
            ? route('admin.dashboard', absolute: false)
            : route('schedule', absolute: false);

        return redirect()->intended($home);
    }
}
