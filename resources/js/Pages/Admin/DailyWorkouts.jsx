import { Head, router, useForm } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';

const PROGRAMS = [
    { key: 'hyrox', label: 'HYROX', color: 'bg-lime-400', hint: 'Used by every HYROX class on this date' },
    { key: 'crossfit', label: 'CrossFit', color: 'bg-orange-500', hint: 'Used by every CrossFit class on this date' },
];

function WorkoutEditor({ program, date, saved }) {
    const form = useForm({
        workout_date: date,
        program: program.key,
        title: saved?.title ?? '',
        workout: saved?.workout ?? '',
        coach_notes: saved?.coach_notes ?? '',
        is_published: saved?.is_published ?? false,
    });

    const submit = e => {
        e.preventDefault();
        form.post('/admin/daily-workouts', { preserveScroll: true });
    };

    return <form onSubmit={submit} className="bg-white rounded-2xl border border-gray-200 overflow-hidden shadow-sm">
        <div className="p-5 border-b flex items-center gap-3">
            <span className={`w-3 h-10 rounded-full ${program.color}`} />
            <div className="flex-1"><h2 className="font-black text-xl">{program.label}</h2><p className="text-xs text-gray-500">{program.hint}</p></div>
            <label className="flex items-center gap-2 text-sm font-semibold"><input type="checkbox" checked={form.data.is_published} onChange={e => form.setData('is_published', e.target.checked)} /> Published</label>
        </div>
        <div className="p-5 space-y-4">
            <label className="block text-xs font-bold uppercase text-gray-500">Workout title<input className="mt-1 w-full rounded-xl border-gray-300" placeholder="e.g. Engine Builder" value={form.data.title} onChange={e => form.setData('title', e.target.value)} /></label>
            <label className="block text-xs font-bold uppercase text-gray-500">Workout<textarea rows="12" className="mt-1 w-full rounded-xl border-gray-300 font-mono text-sm" placeholder={'Warm-up\n...\n\nMain workout\n...'} value={form.data.workout} onChange={e => form.setData('workout', e.target.value)} required /></label>
            <label className="block text-xs font-bold uppercase text-gray-500">Coach notes (optional)<textarea rows="3" className="mt-1 w-full rounded-xl border-gray-300" value={form.data.coach_notes} onChange={e => form.setData('coach_notes', e.target.value)} /></label>
            {form.errors.workout && <p className="text-sm text-red-500">{form.errors.workout}</p>}
            <button disabled={form.processing} className="w-full rounded-xl bg-gray-900 text-white py-3 font-bold disabled:opacity-50">{form.processing ? 'Saving…' : `Save ${program.label} workout`}</button>
        </div>
    </form>;
}

export default function DailyWorkouts({ date, workouts }) {
    const changeDate = value => router.get('/admin/daily-workouts', { date: value }, { preserveState: false });
    const shiftDate = days => { const d = new Date(`${date}T12:00:00`); d.setDate(d.getDate() + days); changeDate(d.toISOString().slice(0, 10)); };

    return <AdminLayout title="Daily Workouts"><Head title="Daily Workouts" />
        <div className="flex flex-wrap items-center gap-3 mb-6 bg-white rounded-xl border p-4">
            <button onClick={() => shiftDate(-1)} className="px-3 py-2 rounded-lg bg-gray-100 font-bold">←</button>
            <input type="date" value={date} onChange={e => changeDate(e.target.value)} className="rounded-lg border-gray-300 font-bold" />
            <button onClick={() => shiftDate(1)} className="px-3 py-2 rounded-lg bg-gray-100 font-bold">→</button>
            <button onClick={() => changeDate(new Date().toISOString().slice(0, 10))} className="px-3 py-2 rounded-lg text-orange-600 bg-orange-50 font-bold text-sm">Today</button>
            <p className="text-sm text-gray-500 ml-auto">Create only once per program. Every matching class shares it.</p>
        </div>
        <div className="grid xl:grid-cols-2 gap-6">{PROGRAMS.map(program => <WorkoutEditor key={`${program.key}-${date}`} program={program} date={date} saved={workouts?.[program.key]} />)}</div>
    </AdminLayout>;
}
