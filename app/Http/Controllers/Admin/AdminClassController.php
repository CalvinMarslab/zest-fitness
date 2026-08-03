<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClassTemplate;
use App\Models\GymClass;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class AdminClassController extends Controller
{
    public function index(): Response
    {
        \Artisan::call('classes:generate', ['--weeks' => 8]);

        $templates = ClassTemplate::orderBy('day_of_week')->orderBy('start_time')->get();

        // Special one-off classes (no template)
        $specials = GymClass::withCount('bookings')
            ->whereNull('template_id')
            ->orderBy('start_time')
            ->get()
            ->map(fn ($c) => [...$c->toArray(), 'attendees' => []]);

        return Inertia::render('Admin/Classes', [
            'templates' => $templates,
            'specials'  => $specials,
        ]);
    }

    public function storeTemplate(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'        => 'required|string|max:255',
            'coach'       => 'required|string|max:255',
            'day_of_week' => 'required|integer|between:0,6',
            'start_time'  => 'required|string',
            'capacity'    => 'required|integer|min:1|max:200',
        ]);

        ClassTemplate::create($data);
        return back();
    }

    public function updateTemplate(Request $request, ClassTemplate $template): RedirectResponse
    {
        $data = $request->validate([
            'name'        => 'sometimes|string|max:255',
            'coach'       => 'sometimes|string|max:255',
            'day_of_week' => 'sometimes|integer|between:0,6',
            'start_time'  => 'sometimes|string',
            'capacity'    => 'sometimes|integer|min:1|max:200',
            'is_active'   => 'sometimes|boolean',
        ]);

        $template->update($data);
        return back();
    }

    public function destroyTemplate(ClassTemplate $template): RedirectResponse
    {
        $template->delete();
        return back();
    }

    public function generate(Request $request): RedirectResponse
    {
        $weeks = (int) $request->input('weeks', 8);
        \Artisan::call('classes:generate', ['--weeks' => $weeks]);
        return back()->with('success', \Artisan::output());
    }

    public function store(Request $request): RedirectResponse
    {
        $base = $request->validate([
            'name'      => 'required|string|max:255',
            'coach'     => 'required|string|max:255',
            'capacity'  => 'required|integer|min:1|max:100',
            'recurring' => 'required|boolean',
        ]);

        if (! $base['recurring']) {
            // ── Single class ─────────────────────────────────────────────────
            $data = $request->validate(['start_time' => 'required|date']);
            GymClass::create([...$base, 'start_time' => $data['start_time'], 'recurring' => false]);
        } else {
            // ── Recurring schedule ───────────────────────────────────────────
            $data = $request->validate([
                'days'       => 'required|array|min:1',   // [0=Sun…6=Sat]
                'days.*'     => 'integer|between:0,6',
                'start_hour' => 'required|string',        // "HH:MM"
                'end_hour'   => 'required|string',        // "HH:MM"
                'weeks'      => 'required|integer|min:1|max:52',
            ]);

            [$sh, $sm] = explode(':', $data['start_hour']);
            [$eh, $em] = explode(':', $data['end_hour']);

            $today   = Carbon::today();
            $endDate = $today->copy()->addWeeks((int) $data['weeks']);
            $classes = [];

            for ($d = $today->copy(); $d->lt($endDate); $d->addDay()) {
                if (in_array($d->dayOfWeek, $data['days'])) {
                    $startTime = $d->copy()->setTime((int) $sh, (int) $sm);
                    $label     = sprintf('%s–%s', $data['start_hour'], $data['end_hour']);
                    $classes[] = [
                        'name'       => $base['name'],
                        'coach'      => $base['coach'],
                        'capacity'   => $base['capacity'],
                        'start_time' => $startTime->toDateTimeString(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }

            if (! empty($classes)) {
                GymClass::insert($classes);
            }
        }

        return back();
    }

    public function slot(Request $request): Response
    {
        $templateId = (int) $request->query('template_id');
        $selectedId = (int) $request->query('id', 0);

        $template = ClassTemplate::findOrFail($templateId);

        $instances = GymClass::withCount('bookings')
            ->with(['bookings.user:id,name,email'])
            ->where('template_id', $templateId)
            ->orderBy('start_time')
            ->get()
            ->map(fn ($c) => [
                ...$c->toArray(),
                'attendees' => $c->bookings->map(fn ($b) => [
                    'id'    => $b->user->id,
                    'name'  => $b->user->name,
                    'email' => $b->user->email,
                ])->values(),
            ]);

        $selected = $selectedId
            ? $instances->firstWhere('id', $selectedId)
            : $instances->first();

        return Inertia::render('Admin/ClassSlot', [
            'template'  => $template,
            'instances' => $instances->values(),
            'selected'  => $selected,
        ]);
    }

    public function update(Request $request, GymClass $gymClass): RedirectResponse
    {
        $data = $request->validate([
            'name'         => 'sometimes|string|max:255',
            'coach'        => 'sometimes|string|max:255',
            'start_time'   => 'sometimes|date',
            'capacity'     => 'sometimes|integer|min:1|max:100',
            'exercises'    => 'sometimes|nullable|array',
            'exercises.*'  => 'array',
            'wod_type'     => 'sometimes|nullable|string|max:50',
            'wod_duration' => 'sometimes|nullable|string|max:20',
            'wod_config'   => 'sometimes|nullable|array',
            'is_cancelled' => 'sometimes|boolean',
        ]);

        $gymClass->update($data);

        return back();
    }

    public function destroy(GymClass $gymClass): RedirectResponse
    {
        $gymClass->delete();

        return back();
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        $data = $request->validate(['ids' => 'required|array', 'ids.*' => 'integer']);
        GymClass::whereIn('id', $data['ids'])->delete();

        return back();
    }

    public function generateWod(Request $request): \Illuminate\Http\JsonResponse
    {
        $data = $request->validate([
            'wod_type'   => 'required|string|max:50',
            'class_name' => 'required|string|max:255',
        ]);

        $exercises = app(\App\Services\ClaudeService::class)
            ->generateWod($data['wod_type'], $data['class_name']);

        return response()->json(['exercises' => $exercises]);
    }
}
