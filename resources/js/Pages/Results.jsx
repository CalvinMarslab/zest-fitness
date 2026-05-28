import { useForm, usePage, router } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';

// ─── Helpers ─────────────────────────────────────────────────────────────────

function formatDateLabel(dateStr) {
    return new Date(dateStr + 'T00:00:00').toLocaleDateString('en-US', {
        weekday: 'long', month: 'long', day: 'numeric',
    });
}

function dayLabel(dateStr) {
    return new Date(dateStr + 'T00:00:00').toLocaleDateString('en-US', { weekday: 'short' });
}

function dayNum(dateStr) {
    return new Date(dateStr + 'T00:00:00').getDate();
}

// ─── Date Strip ───────────────────────────────────────────────────────────────

function DateStrip({ dates, selectedDate, today }) {
    return (
        <div className="flex gap-2 overflow-x-auto pb-1 mb-6 -mx-5 px-5">
            {dates.map((d) => {
                const isSelected = d === selectedDate;
                const isToday    = d === today;
                return (
                    <button
                        key={d}
                        onClick={() => router.get(route('results'), { date: d }, { preserveScroll: true })}
                        className={[
                            'flex flex-col items-center shrink-0 w-14 py-2.5 rounded-2xl transition-all',
                            isSelected
                                ? 'bg-[#C8FF00] text-[#0D0D0D]'
                                : 'bg-[#1A1A1A] border border-[#2A2A2A] text-white hover:border-[#C8FF00]/40',
                        ].join(' ')}
                    >
                        <span className={[
                            'text-[10px] font-bold uppercase tracking-wider',
                            isSelected ? 'text-[#0D0D0D]/70' : isToday ? 'text-[#C8FF00]' : 'text-[#666]',
                        ].join(' ')}>
                            {dayLabel(d)}
                        </span>
                        <span className={[
                            'text-lg font-black leading-tight',
                            isSelected ? 'text-[#0D0D0D]' : isToday ? 'text-[#C8FF00]' : 'text-white',
                        ].join(' ')}>
                            {dayNum(d)}
                        </span>
                        {isToday && !isSelected && (
                            <span className="w-1 h-1 rounded-full bg-[#C8FF00] mt-0.5" />
                        )}
                    </button>
                );
            })}
        </div>
    );
}

// ─── Log Result Modal ─────────────────────────────────────────────────────────

function LogModal({ date, onClose }) {
    const form = useForm({ exercise: '', value: '', notes: '', date });

    function submit(e) {
        e.preventDefault();
        form.post(route('results.store'), { onSuccess: onClose });
    }

    return (
        <div className="fixed inset-0 z-50 flex items-end justify-center">
            <div className="absolute inset-0 bg-black/70 backdrop-blur-sm" onClick={onClose} />
            <div
                className="relative w-full max-w-lg bg-[#1A1A1A] rounded-t-3xl border-t border-[#2A2A2A] p-6 pb-10 shadow-2xl"
                onClick={(e) => e.stopPropagation()}
            >
                <div className="mx-auto mb-5 w-10 h-1 rounded-full bg-[#333]" />
                <button onClick={onClose}
                    className="absolute top-5 right-5 w-8 h-8 flex items-center justify-center rounded-full bg-[#2A2A2A] text-[#888] hover:text-white text-sm transition-colors">
                    ✕
                </button>
                <h2 className="text-lg font-black text-white mb-5">Log Your Result</h2>

                <form onSubmit={submit} className="flex flex-col gap-4">
                    <div>
                        <label className="block text-xs font-bold text-[#888] uppercase tracking-widest mb-1.5">Exercise</label>
                        <input
                            className="w-full rounded-xl bg-[#111] border border-[#2A2A2A] text-white px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-[#C8FF00]/50 focus:border-[#C8FF00]/50 transition-all placeholder:text-[#444]"
                            placeholder="e.g. Deadlift, 5K Run, Snatch"
                            value={form.data.exercise}
                            onChange={(e) => form.setData('exercise', e.target.value)}
                        />
                        {form.errors.exercise && <p className="text-xs text-red-400 mt-1">{form.errors.exercise}</p>}
                    </div>

                    <div>
                        <label className="block text-xs font-bold text-[#888] uppercase tracking-widest mb-1.5">Result</label>
                        <input
                            className="w-full rounded-xl bg-[#111] border border-[#2A2A2A] text-white px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-[#C8FF00]/50 focus:border-[#C8FF00]/50 transition-all placeholder:text-[#444]"
                            placeholder="e.g. 120 kg, 22:30, 150 reps"
                            value={form.data.value}
                            onChange={(e) => form.setData('value', e.target.value)}
                        />
                        {form.errors.value && <p className="text-xs text-red-400 mt-1">{form.errors.value}</p>}
                    </div>

                    <div>
                        <label className="block text-xs font-bold text-[#888] uppercase tracking-widest mb-1.5">
                            Notes <span className="normal-case font-normal text-[#555]">(optional)</span>
                        </label>
                        <textarea
                            className="w-full rounded-xl bg-[#111] border border-[#2A2A2A] text-white px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-[#C8FF00]/50 focus:border-[#C8FF00]/50 transition-all placeholder:text-[#444] resize-none"
                            rows={2}
                            placeholder="PR? Technique notes?"
                            value={form.data.notes}
                            onChange={(e) => form.setData('notes', e.target.value)}
                        />
                    </div>

                    <button
                        type="submit"
                        disabled={form.processing}
                        className="w-full py-3.5 rounded-2xl bg-[#C8FF00] text-[#0D0D0D] font-black text-sm hover:bg-[#d4ff33] active:scale-[0.98] transition-all disabled:opacity-50"
                    >
                        {form.processing ? 'Saving…' : 'Save Result'}
                    </button>
                </form>
            </div>
        </div>
    );
}

// ─── Result Card ──────────────────────────────────────────────────────────────

function ResultCard({ result, isCurrentUser, selectedDate }) {
    function handleDelete() {
        if (!confirm('Delete this result?')) return;
        router.delete(route('results.destroy', result.id), { data: { date: selectedDate } });
    }

    return (
        <div className={[
            'rounded-2xl border p-4',
            isCurrentUser
                ? 'bg-[#C8FF00]/5 border-[#C8FF00]/20'
                : 'bg-[#1A1A1A] border-[#2A2A2A]',
        ].join(' ')}>
            <div className="flex items-start gap-3">
                {/* Avatar */}
                <div className={[
                    'shrink-0 w-10 h-10 rounded-full flex items-center justify-center text-sm font-black',
                    isCurrentUser ? 'bg-[#C8FF00] text-[#0D0D0D]' : 'bg-[#2A2A2A] text-[#888]',
                ].join(' ')}>
                    {result.name[0]}
                </div>

                {/* Content */}
                <div className="flex-1 min-w-0">
                    <div className="flex items-center justify-between gap-2">
                        <p className={`font-bold text-sm ${isCurrentUser ? 'text-[#C8FF00]' : 'text-white'}`}>
                            {result.name}
                            {isCurrentUser && <span className="ml-1 text-xs text-[#C8FF00]/60 font-normal">(you)</span>}
                        </p>
                        {isCurrentUser && (
                            <button
                                onClick={handleDelete}
                                className="text-xs text-[#444] hover:text-red-400 transition-colors shrink-0"
                            >
                                ✕
                            </button>
                        )}
                    </div>

                    <p className="text-xs text-[#666] mt-0.5">{result.exercise}</p>

                    <div className="mt-2 inline-block bg-[#C8FF00]/10 text-[#C8FF00] text-sm font-bold px-3 py-1 rounded-full border border-[#C8FF00]/20">
                        {result.value}
                    </div>

                    {result.notes && (
                        <p className="text-xs text-[#555] mt-1.5 italic">{result.notes}</p>
                    )}
                </div>
            </div>
        </div>
    );
}

// ─── Page ─────────────────────────────────────────────────────────────────────

export default function Results({ results, selectedDate, dates }) {
    const { auth } = usePage().props;
    const today    = new Date().toISOString().slice(0, 10);
    const [showLog, setShowLog] = useState(false);

    return (
        <AppLayout active="Results">
            <div className="mb-5">
                <h1 className="text-2xl font-black text-white">Results Board</h1>
                <p className="text-sm text-[#666] mt-0.5">Log and compare your workout results</p>
            </div>

            <DateStrip dates={dates} selectedDate={selectedDate} today={today} />

            <div className="flex items-center justify-between mb-4">
                <p className="text-sm font-bold text-[#888]">{formatDateLabel(selectedDate)}</p>
                <button
                    onClick={() => setShowLog(true)}
                    className="text-xs font-black text-[#0D0D0D] bg-[#C8FF00] px-4 py-2 rounded-full hover:bg-[#d4ff33] active:scale-95 transition-all"
                >
                    + Log Result
                </button>
            </div>

            {results.length === 0 ? (
                <div className="text-center py-20">
                    <p className="text-5xl mb-4">🏋️</p>
                    <p className="font-bold text-white">No results logged for this day</p>
                    <p className="text-sm mt-1 text-[#666]">Be the first to log your result!</p>
                </div>
            ) : (
                <div className="flex flex-col gap-3">
                    {results.map((r) => (
                        <ResultCard
                            key={r.id}
                            result={r}
                            isCurrentUser={r.user_id === auth?.user?.id}
                            selectedDate={selectedDate}
                        />
                    ))}
                </div>
            )}

            {showLog && (
                <LogModal date={selectedDate} onClose={() => setShowLog(false)} />
            )}
        </AppLayout>
    );
}
