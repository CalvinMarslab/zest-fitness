import { useState } from 'react';
import { router, useForm } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { parseLocalDT, toLocalInputDT } from '@/utils/date';

// ── Helpers ───────────────────────────────────────────────────────────────────

function formatDT(dt) {
    return parseLocalDT(dt).toLocaleString('en-US', {
        weekday: 'short', month: 'short', day: 'numeric',
        hour: '2-digit', minute: '2-digit',
    });
}

const DAYS = [
    { label: 'Sun', value: 0 },
    { label: 'Mon', value: 1 },
    { label: 'Tue', value: 2 },
    { label: 'Wed', value: 3 },
    { label: 'Thu', value: 4 },
    { label: 'Fri', value: 5 },
    { label: 'Sat', value: 6 },
];

// ── Shared base fields ────────────────────────────────────────────────────────

function BaseFields({ form }) {
    return (
        <div className="grid grid-cols-2 gap-3">
            <div>
                <label className="text-xs font-semibold text-gray-500 uppercase">Class Name</label>
                <input
                    className="mt-1 w-full border rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-orange-300"
                    placeholder="e.g. Morning CrossFit"
                    value={form.data.name}
                    onChange={(e) => form.setData('name', e.target.value)}
                    required
                />
            </div>
            <div>
                <label className="text-xs font-semibold text-gray-500 uppercase">Coach</label>
                <input
                    className="mt-1 w-full border rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-orange-300"
                    placeholder="Coach name"
                    value={form.data.coach}
                    onChange={(e) => form.setData('coach', e.target.value)}
                    required
                />
            </div>
            <div>
                <label className="text-xs font-semibold text-gray-500 uppercase">Capacity</label>
                <input
                    type="number" min="1" max="100"
                    className="mt-1 w-full border rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-orange-300"
                    value={form.data.capacity}
                    onChange={(e) => form.setData('capacity', parseInt(e.target.value))}
                    required
                />
            </div>
        </div>
    );
}

// ── Add Class Form (single + recurring) ───────────────────────────────────────

function AddClassForm({ onSuccess }) {
    const [mode, setMode] = useState('single'); // 'single' | 'recurring'

    const form = useForm({
        name:       '',
        coach:      '',
        capacity:   20,
        recurring:  false,
        // single
        start_time: '',
        // recurring
        days:       [],
        start_hour: '11:00',
        end_hour:   '12:00',
        weeks:      4,
    });

    function toggleDay(val) {
        const days = form.data.days.includes(val)
            ? form.data.days.filter((d) => d !== val)
            : [...form.data.days, val].sort((a, b) => a - b);
        form.setData('days', days);
    }

    // Quick presets
    const presets = [
        { label: 'Mon–Fri', days: [1, 2, 3, 4, 5] },
        { label: 'Mon / Wed / Fri', days: [1, 3, 5] },
        { label: 'Tue / Thu', days: [2, 4] },
        { label: 'Weekends', days: [0, 6] },
    ];

    function submit(e) {
        e.preventDefault();
        form.setData('recurring', mode === 'recurring');
        form.post(route('admin.classes.store'), {
            data: { ...form.data, recurring: mode === 'recurring' },
            onSuccess,
        });
    }

    return (
        <form onSubmit={submit} className="space-y-4">
            {/* Mode toggle */}
            <div className="flex gap-2">
                {['single', 'recurring'].map((m) => (
                    <button
                        key={m}
                        type="button"
                        onClick={() => setMode(m)}
                        className={[
                            'px-4 py-1.5 rounded-full text-sm font-semibold transition-all',
                            mode === m
                                ? 'bg-orange-500 text-white shadow'
                                : 'bg-gray-100 text-gray-500 hover:bg-gray-200',
                        ].join(' ')}
                    >
                        {m === 'single' ? 'One-time' : 'Recurring Schedule'}
                    </button>
                ))}
            </div>

            {/* Base fields */}
            <BaseFields form={form} />

            {/* ── Single mode ── */}
            {mode === 'single' && (
                <div>
                    <label className="text-xs font-semibold text-gray-500 uppercase">Start Time</label>
                    <input
                        type="datetime-local"
                        className="mt-1 w-full border rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-orange-300"
                        value={form.data.start_time}
                        onChange={(e) => form.setData('start_time', e.target.value)}
                        required={mode === 'single'}
                    />
                </div>
            )}

            {/* ── Recurring mode ── */}
            {mode === 'recurring' && (
                <div className="space-y-4 bg-orange-50 border border-orange-100 rounded-2xl p-4">
                    {/* Day presets */}
                    <div>
                        <label className="text-xs font-semibold text-gray-500 uppercase mb-2 block">Quick Presets</label>
                        <div className="flex flex-wrap gap-2">
                            {presets.map((p) => (
                                <button
                                    key={p.label}
                                    type="button"
                                    onClick={() => form.setData('days', p.days)}
                                    className="text-xs px-3 py-1.5 rounded-full border border-orange-200 bg-white text-orange-600 hover:bg-orange-100 font-medium transition-colors"
                                >
                                    {p.label}
                                </button>
                            ))}
                        </div>
                    </div>

                    {/* Day pills */}
                    <div>
                        <label className="text-xs font-semibold text-gray-500 uppercase mb-2 block">Select Days</label>
                        <div className="flex gap-2 flex-wrap">
                            {DAYS.map((day) => {
                                const active = form.data.days.includes(day.value);
                                return (
                                    <button
                                        key={day.value}
                                        type="button"
                                        onClick={() => toggleDay(day.value)}
                                        className={[
                                            'w-12 h-12 rounded-xl text-sm font-bold transition-all border',
                                            active
                                                ? 'bg-orange-500 text-white border-orange-500 shadow'
                                                : 'bg-white text-gray-500 border-gray-200 hover:border-orange-300',
                                        ].join(' ')}
                                    >
                                        {day.label}
                                    </button>
                                );
                            })}
                        </div>
                        {form.data.days.length === 0 && (
                            <p className="text-xs text-red-400 mt-1">Select at least one day</p>
                        )}
                    </div>

                    {/* Time range */}
                    <div className="grid grid-cols-3 gap-3">
                        <div>
                            <label className="text-xs font-semibold text-gray-500 uppercase">Start Time</label>
                            <input
                                type="time"
                                className="mt-1 w-full border rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-orange-300 bg-white"
                                value={form.data.start_hour}
                                onChange={(e) => form.setData('start_hour', e.target.value)}
                                required={mode === 'recurring'}
                            />
                        </div>
                        <div>
                            <label className="text-xs font-semibold text-gray-500 uppercase">End Time</label>
                            <input
                                type="time"
                                className="mt-1 w-full border rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-orange-300 bg-white"
                                value={form.data.end_hour}
                                onChange={(e) => form.setData('end_hour', e.target.value)}
                                required={mode === 'recurring'}
                            />
                        </div>
                        <div>
                            <label className="text-xs font-semibold text-gray-500 uppercase">Generate (weeks)</label>
                            <input
                                type="number" min="1" max="52"
                                className="mt-1 w-full border rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-orange-300 bg-white"
                                value={form.data.weeks}
                                onChange={(e) => form.setData('weeks', parseInt(e.target.value))}
                            />
                        </div>
                    </div>

                    {/* Preview */}
                    {form.data.days.length > 0 && (
                        <p className="text-xs text-orange-700 bg-orange-100 px-3 py-2 rounded-xl">
                            Will create <strong>{form.data.days.length * form.data.weeks}</strong> classes —{' '}
                            {DAYS.filter((d) => form.data.days.includes(d.value)).map((d) => d.label).join(', ')},{' '}
                            {form.data.start_hour}–{form.data.end_hour}, for {form.data.weeks} week{form.data.weeks !== 1 ? 's' : ''}
                        </p>
                    )}
                </div>
            )}

            <button
                type="submit"
                disabled={form.processing || (mode === 'recurring' && form.data.days.length === 0)}
                className="w-full py-2.5 rounded-xl bg-orange-500 text-white text-sm font-bold disabled:opacity-50"
            >
                {form.processing
                    ? 'Creating…'
                    : mode === 'recurring'
                        ? `Create ${form.data.days.length * form.data.weeks || ''} Classes`
                        : 'Create Class'}
            </button>
        </form>
    );
}

// ── Edit Modal (single class only) ────────────────────────────────────────────

function ExerciseEditor({ exercises, onChange }) {
    const [draft, setDraft] = useState('');

    function add() {
        const trimmed = draft.trim();
        if (!trimmed || exercises.includes(trimmed)) { setDraft(''); return; }
        onChange([...exercises, trimmed]);
        setDraft('');
    }

    function remove(ex) {
        onChange(exercises.filter((e) => e !== ex));
    }

    return (
        <div>
            <label className="text-xs font-semibold text-gray-500 uppercase">Today's Exercises (WOD)</label>
            <p className="text-xs text-gray-400 mt-0.5 mb-2">Members will see these as suggestions when logging results.</p>

            {/* Existing exercises */}
            {exercises.length > 0 && (
                <div className="flex flex-wrap gap-1.5 mb-2">
                    {exercises.map((ex) => (
                        <span key={ex} className="flex items-center gap-1 text-xs bg-orange-50 border border-orange-200 text-orange-700 px-2.5 py-1 rounded-full font-medium">
                            {ex}
                            <button type="button" onClick={() => remove(ex)} className="text-orange-400 hover:text-orange-600 font-bold leading-none">×</button>
                        </span>
                    ))}
                </div>
            )}

            {/* Add input */}
            <div className="flex gap-2">
                <input
                    className="flex-1 border rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-orange-300"
                    placeholder="e.g. Back Squat, Double Unders, Fran…"
                    value={draft}
                    onChange={(e) => setDraft(e.target.value)}
                    onKeyDown={(e) => { if (e.key === 'Enter') { e.preventDefault(); add(); } }}
                />
                <button
                    type="button"
                    onClick={add}
                    className="px-3 py-2 rounded-xl bg-orange-100 text-orange-600 text-sm font-semibold hover:bg-orange-200 transition-colors"
                >
                    + Add
                </button>
            </div>
        </div>
    );
}

function EditModal({ gymClass, onClose }) {
    const form = useForm({
        name:       gymClass.name,
        coach:      gymClass.coach,
        start_time: toLocalInputDT(gymClass.start_time),
        capacity:   gymClass.capacity,
        exercises:  gymClass.exercises ?? [],
    });

    function submit(e) {
        e.preventDefault();
        form.patch(route('admin.classes.update', gymClass.id), { onSuccess: onClose });
    }

    return (
        <div className="fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4" onClick={onClose}>
            <div className="bg-white rounded-2xl p-6 w-full max-w-lg shadow-2xl" onClick={(e) => e.stopPropagation()}>
                <h2 className="font-bold text-lg mb-4">Edit Class</h2>
                <form onSubmit={submit} className="space-y-3">
                    <BaseFields form={form} />
                    <div>
                        <label className="text-xs font-semibold text-gray-500 uppercase">Start Time</label>
                        <input
                            type="datetime-local"
                            className="mt-1 w-full border rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-orange-300"
                            value={form.data.start_time}
                            onChange={(e) => form.setData('start_time', e.target.value)}
                            required
                        />
                    </div>
                    <ExerciseEditor
                        exercises={form.data.exercises}
                        onChange={(ex) => form.setData('exercises', ex)}
                    />
                    <div className="flex gap-2 mt-2">
                        <button type="button" onClick={onClose}
                            className="flex-1 py-2 rounded-xl border text-sm text-gray-600">Cancel</button>
                        <button type="submit" disabled={form.processing}
                            className="flex-1 py-2 rounded-xl bg-orange-500 text-white text-sm font-semibold disabled:opacity-60">
                            {form.processing ? 'Saving…' : 'Update Class'}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}

// ── Attendees Modal ────────────────────────────────────────────────────────────

function AttendeesModal({ gymClass, onClose }) {
    const { attendees, name, start_time, bookings_count, capacity } = gymClass;

    return (
        <div className="fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4" onClick={onClose}>
            <div className="bg-white rounded-2xl p-6 w-full max-w-md shadow-2xl" onClick={(e) => e.stopPropagation()}>
                <div className="flex items-start justify-between mb-4">
                    <div>
                        <h2 className="font-bold text-lg text-gray-900">{name}</h2>
                        <p className="text-sm text-gray-400">{formatDT(start_time)}</p>
                    </div>
                    <span className={`text-xs font-semibold px-2.5 py-1 rounded-full ${bookings_count >= capacity ? 'bg-red-100 text-red-600' : 'bg-green-100 text-green-600'}`}>
                        {bookings_count}/{capacity} booked
                    </span>
                </div>

                {attendees.length === 0 ? (
                    <div className="text-center py-8 text-gray-400">
                        <p className="text-3xl mb-2">📭</p>
                        <p className="text-sm">No bookings yet</p>
                    </div>
                ) : (
                    <ul className="divide-y divide-gray-50">
                        {attendees.map((a, i) => (
                            <li key={a.id} className="flex items-center gap-3 py-2.5">
                                <span className="w-7 h-7 rounded-full bg-orange-100 text-orange-600 text-xs font-bold flex items-center justify-center shrink-0">
                                    {a.name[0].toUpperCase()}
                                </span>
                                <div className="min-w-0">
                                    <p className="text-sm font-medium text-gray-900 truncate">{a.name}</p>
                                    <p className="text-xs text-gray-400 truncate">{a.email}</p>
                                </div>
                                <span className="ml-auto text-xs text-gray-300 shrink-0">#{i + 1}</span>
                            </li>
                        ))}
                    </ul>
                )}

                <button onClick={onClose}
                    className="mt-4 w-full py-2 rounded-xl border text-sm text-gray-600 hover:bg-gray-50 transition-colors">
                    Close
                </button>
            </div>
        </div>
    );
}

// ── Page ──────────────────────────────────────────────────────────────────────

export default function Classes({ classes }) {
    const [editing, setEditing]       = useState(null);
    const [attendees, setAttendees]   = useState(null);
    const [showForm, setShowForm]     = useState(false);

    function deleteClass(c) {
        if (!confirm(`Delete "${c.name}"? All bookings will be removed.`)) return;
        router.delete(route('admin.classes.destroy', c.id));
    }

    return (
        <AdminLayout title="Classes">
            {/* Add class panel */}
            <div className="bg-white rounded-2xl border border-gray-100 p-5 mb-6">
                <div className="flex items-center justify-between mb-4">
                    <h2 className="font-semibold text-gray-900">Add New Class</h2>
                    <button
                        onClick={() => setShowForm(!showForm)}
                        className="text-sm text-orange-500 hover:text-orange-700 font-medium"
                    >
                        {showForm ? 'Hide' : '+ New Class'}
                    </button>
                </div>
                {showForm && <AddClassForm onSuccess={() => setShowForm(false)} />}
            </div>

            {/* Classes table */}
            <div className="bg-white rounded-2xl border border-gray-100 overflow-hidden">
                <div className="overflow-x-auto">
                <table className="w-full text-sm min-w-[560px]">
                    <thead className="bg-gray-50 border-b border-gray-100">
                        <tr>
                            {['Class', 'Coach', 'Start Time', 'Capacity', 'Booked', ''].map((h) => (
                                <th key={h} className="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">{h}</th>
                            ))}
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-50">
                        {classes.map((c) => {
                            const full = c.bookings_count >= c.capacity;
                            return (
                                <tr key={c.id} className="hover:bg-gray-50 transition-colors">
                                    <td className="px-4 py-3 font-medium text-gray-900">{c.name}</td>
                                    <td className="px-4 py-3 text-gray-500">{c.coach}</td>
                                    <td className="px-4 py-3 text-gray-500">{formatDT(c.start_time)}</td>
                                    <td className="px-4 py-3 text-gray-500">{c.capacity}</td>
                                    <td className="px-4 py-3">
                                        <button
                                            onClick={() => setAttendees(c)}
                                            className={`text-xs font-semibold px-2 py-0.5 rounded-full transition-opacity hover:opacity-70 ${full ? 'bg-red-100 text-red-600' : 'bg-green-100 text-green-600'}`}
                                        >
                                            {c.bookings_count}/{c.capacity}
                                        </button>
                                    </td>
                                    <td className="px-4 py-3">
                                        <div className="flex items-center gap-2">
                                            <button onClick={() => setEditing(c)}
                                                className="text-xs text-orange-500 hover:text-orange-700 font-medium">Edit</button>
                                            <button onClick={() => deleteClass(c)}
                                                className="text-xs text-red-400 hover:text-red-600 font-medium">Delete</button>
                                        </div>
                                    </td>
                                </tr>
                            );
                        })}
                    </tbody>
                </table>
                </div>
            </div>

            {editing   && <EditModal gymClass={editing} onClose={() => setEditing(null)} />}
            {attendees && <AttendeesModal gymClass={attendees} onClose={() => setAttendees(null)} />}
        </AdminLayout>
    );
}
