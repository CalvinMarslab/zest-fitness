<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClassTemplate;
use App\Models\GymClass;
use App\Services\BookingService;
use App\Services\ClaudeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class AdminClassController extends Controller
{
    public function __construct(private readonly BookingService $bookingService) {}

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
            'specials' => $specials,
        ]);
    }

    public function storeTemplate(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'coach' => 'required|string|max:255',
            'coach_id' => 'nullable|exists:users,id',
            'day_of_week' => 'required|integer|between:0,6',
            'start_time' => 'required|string',
            'capacity' => 'required|integer|min:1|max:200',
        ]);

        ClassTemplate::create($data);

        return back();
    }

    public function updateTemplate(Request $request, ClassTemplate $template): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'coach' => 'sometimes|string|max:255',
            'coach_id' => 'sometimes|nullable|exists:users,id',
            'day_of_week' => 'sometimes|integer|between:0,6',
            'start_time' => 'sometimes|string',
            'capacity' => 'sometimes|integer|min:1|max:200',
            'is_active' => 'sometimes|boolean',
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
            'name' => 'required|string|max:255',
            'coach' => 'required|string|max:255',
            'capacity' => 'required|integer|min:1|max:100',
            'recurring' => 'required|boolean',
        ]);

        if (! $base['recurring']) {
            // ── Single class ─────────────────────────────────────────────────
            $data = $request->validate(['start_time' => 'required|date']);
            GymClass::create([...$base, 'start_time' => $data['start_time'], 'recurring' => false]);
        } else {
            // ── Recurring schedule ───────────────────────────────────────────
            $data = $request->validate([
                'days' => 'required|array|min:1',   // [0=Sun…6=Sat]
                'days.*' => 'integer|between:0,6',
                'start_hour' => 'required|string',        // "HH:MM"
                'end_hour' => 'required|string',        // "HH:MM"
                'weeks' => 'required|integer|min:1|max:52',
            ]);

            [$sh, $sm] = explode(':', $data['start_hour']);
            [$eh, $em] = explode(':', $data['end_hour']);

            $today = Carbon::today();
            $endDate = $today->copy()->addWeeks((int) $data['weeks']);
            $classes = [];

            for ($d = $today->copy(); $d->lt($endDate); $d->addDay()) {
                if (in_array($d->dayOfWeek, $data['days'])) {
                    $startTime = $d->copy()->setTime((int) $sh, (int) $sm);
                    $classes[] = [
                        'name' => $base['name'],
                        'coach' => $base['coach'],
                        'capacity' => $base['capacity'],
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

        $instances = GymClass::withCount([
            'bookings',
            'bookings as confirmed_count' => fn ($q) => $q->whereIn('status', ['booked', 'checked_in']),
            'bookings as waitlist_count' => fn ($q) => $q->where('status', 'waitlisted'),
        ])
            ->with(['bookings' => fn ($q) => $q->with('user:id,name,email')->orderBy('status')->orderBy('queue_position')])
            ->where('template_id', $templateId)
            ->where('start_time', '>=', Carbon::now()->startOfWeek())
            ->where('start_time', '<=', Carbon::now()->startOfWeek()->addWeeks(8))
            ->orderBy('start_time')
            ->get()
            ->map(fn ($c) => [
                ...$c->toArray(),
                'attendees' => $c->bookings
                    ->whereIn('status', ['booked', 'checked_in'])
                    ->map(fn ($b) => [
                        'id' => $b->user->id,
                        'name' => $b->user->name,
                        'email' => $b->user->email,
                        'booking_id' => $b->id,
                        'booking_status' => $b->status,
                        'checked_in_at' => $b->checked_in_at?->toIso8601String(),
                    ])->values(),
                'waitlist' => $c->bookings
                    ->where('status', 'waitlisted')
                    ->sortBy('queue_position')
                    ->map(fn ($b) => [
                        'id' => $b->user->id,
                        'name' => $b->user->name,
                        'email' => $b->user->email,
                        'booking_id' => $b->id,
                        'queue_position' => $b->queue_position,
                    ])->values(),
            ]);

        $selected = $selectedId
            ? $instances->firstWhere('id', $selectedId)
            : $instances->first();

        return Inertia::render('Admin/ClassSlot', [
            'template' => $template,
            'instances' => $instances->values(),
            'selected' => $selected,
        ]);
    }

    public function update(Request $request, GymClass $gymClass): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'coach' => 'sometimes|string|max:255',
            'coach_id' => 'sometimes|nullable|integer|exists:users,id',
            'start_time' => 'sometimes|date',
            'end_time' => 'sometimes|nullable|date',
            'location' => 'sometimes|nullable|string|max:100',
            'capacity' => 'sometimes|integer|min:1|max:100',
            'exercises' => 'sometimes|nullable|array',
            'exercises.*' => 'array',
            'wod_type' => 'sometimes|nullable|string|max:50',
            'wod_duration' => 'sometimes|nullable|string|max:20',
            'wod_config' => 'sometimes|nullable|array',
            'is_cancelled' => 'sometimes|boolean',
            'status' => 'sometimes|string|in:scheduled,cancelled,completed',
        ]);

        $becomingCancelled = (isset($data['status']) && $data['status'] === 'cancelled')
            || (isset($data['is_cancelled']) && $data['is_cancelled']);

        $wasAlreadyCancelled = $gymClass->is_cancelled || $gymClass->status === 'cancelled';

        if ($becomingCancelled && ! $wasAlreadyCancelled) {
            $this->bookingService->cancelClass($gymClass);

            return back()->with('success', 'Class cancelled. All bookings have been processed.');
        }

        $gymClass->update($data);

        return back();
    }

    public function destroy(GymClass $gymClass): RedirectResponse
    {
        $hasBookings = $gymClass->bookings()->exists();

        if ($hasBookings) {
            $this->bookingService->cancelClass($gymClass);
        } else {
            $gymClass->delete();
        }

        return back()->with('success', $hasBookings
            ? 'Class cancelled and all bookings refunded.'
            : 'Class deleted.');
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        $data = $request->validate(['ids' => 'required|array', 'ids.*' => 'integer']);

        $classes = GymClass::whereIn('id', $data['ids'])->get();

        foreach ($classes as $gymClass) {
            $hasBookings = $gymClass->bookings()->exists();
            if ($hasBookings) {
                $this->bookingService->cancelClass($gymClass);
            } else {
                $gymClass->delete();
            }
        }

        return back()->with('success', 'Classes processed successfully.');
    }

    public function generateWod(Request $request): JsonResponse
    {
        $data = $request->validate([
            'wod_type' => 'required|string|max:50',
            'class_name' => 'required|string|max:255',
        ]);

        $exercises = app(ClaudeService::class)
            ->generateWod($data['wod_type'], $data['class_name']);

        return response()->json(['exercises' => $exercises]);
    }
}
