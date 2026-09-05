<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminSettingsController extends Controller
{
    private const ALLOWED_KEYS = [
        'cancellation_cutoff_hours',
        'late_cancel_loses_credit',
        'no_show_loses_credit',
    ];

    public function index(): Response
    {
        $settings = SystemSetting::whereIn('key', self::ALLOWED_KEYS)
            ->pluck('value', 'key');

        return Inertia::render('Admin/Settings', [
            'settings' => $settings,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'cancellation_cutoff_hours' => 'sometimes|integer|min:0|max:72',
            'late_cancel_loses_credit' => 'sometimes|boolean',
            'no_show_loses_credit' => 'sometimes|boolean',
        ]);

        foreach ($data as $key => $value) {
            if (in_array($key, self::ALLOWED_KEYS)) {
                SystemSetting::set($key, is_bool($value) ? ($value ? 'true' : 'false') : (string) $value);
            }
        }

        return back()->with('success', 'Settings saved.');
    }
}
