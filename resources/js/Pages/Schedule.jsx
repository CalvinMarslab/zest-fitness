import { useState } from 'react';
import { useForm, usePage } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { getActivityType } from '@/config/activityTypes';
import { parseLocalDT } from '@/utils/date';

// ─── Flash Message ────────────────────────────────────────────────────────────

function FlashMessage() {
    const { flash } = usePage().props;
    if (!flash?.success) return null;
    return (
        <div className="mb-4 rounded-2xl bg-[#FFF34D]/10 border border-[#FFF34D]/30 px-4 py-3 text-[#5A6A35] text-sm font-medium">
            {flash.success}
        </div>
    );
}

// ─── Class Detail Modal ───────────────────────────────────────────────────────

function ClassDetailModal({ gymClass, onClose }) {
    const form = useForm({ gym_class_id: gymClass.id });
    const [confirmCancel, setConfirmCancel] = useState(false);

    const book   = () => form.post(route('bookings.store'),   { preserveScroll: true, onSuccess: onClose });
    const cancel = () => form.delete(route('bookings.destroy'), { preserveScroll: true, onSuccess: onClose });

    const start   = parseLocalDT(gymClass.start_time);
    const icon    = getActivityType(gymClass.name.toLowerCase().split(' ')[0]).icon;
    const pct     = Math.round(((gymClass.capacity - gymClass.spots_left) / gymClass.capacity) * 100);
    const isPast  = start <= new Date();

    // Use backend-provided cancellation metadata
    const cutoffAt   = gymClass.cancellation_cutoff_at ? new Date(gymClass.cancellation_cutoff_at) : null;
    const cancelMsg  = (() => {
        if (!gymClass.can_cancel) return null;
        if (gymClass.is_waitlisted) return 'Leaving the waitlist will remove your spot in the queue.';
        if (gymClass.will_refund_credit) {
            const timeStr = cutoffAt
                ? cutoffAt.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' })
                : null;
            return timeStr
                ? `Cancel by ${timeStr} to get your credit back.`
                : 'Your credit will be refunded.';
        }
        if (gymClass.credit_charged) return 'Late cancellation — your credit will NOT be refunded.';
        return 'No credit was charged for this booking.';
    })();

    return (
        <div className="fixed inset-0 z-50 flex items-end justify-center">
            <div className="absolute inset-0 bg-black/70 backdrop-blur-sm" onClick={onClose} />
            <div className="relative w-full max-w-lg bg-[#FFFFFF] rounded-t-3xl border-t border-[#DDD5C0] p-6 pb-10 shadow-2xl">
                {/* Handle */}
                <div className="mx-auto mb-5 w-10 h-1 rounded-full bg-[#EDE5D4]" />

                {/* Close */}
                <button onClick={onClose} aria-label="Close class details"
                    className="absolute top-5 right-5 w-8 h-8 flex items-center justify-center rounded-full bg-[#DDD5C0] text-[#888] hover:text-[#333E48] text-sm transition-colors">
                    ✕
                </button>

                {/* Icon + Title */}
                <div className="flex items-center gap-4 mb-6">
                    <span className="text-5xl">{icon}</span>
                    <div>
                        <h2 className="text-xl font-black text-[#333E48]">{gymClass.name}</h2>
                        <p className="text-sm text-[#888]">with {gymClass.coach}</p>
                    </div>
                    {gymClass.is_booked && (
                        <span className="ml-auto text-xs font-bold text-[#333E48] bg-[#FFF34D] px-3 py-1 rounded-full">
                            BOOKED
                        </span>
                    )}
                </div>

                {/* Info grid */}
                <div className="grid grid-cols-2 gap-3 mb-6">
                    {[
                        ['Time', start.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' })],
                        ['Coach', gymClass.coach],
                        ['Capacity', `${gymClass.capacity} spots`],
                        ['Available', `${gymClass.spots_left} left`],
                    ].map(([label, value]) => (
                        <div key={label} className="bg-[#CFE0EB] rounded-2xl p-3 border border-[#DDD5C0]">
                            <p className="text-[10px] text-[#555] uppercase tracking-wider font-bold mb-1">{label}</p>
                            <p className="font-bold text-[#333E48] text-sm">{value}</p>
                        </div>
                    ))}
                </div>

                {/* Availability bar */}
                <div className="mb-6">
                    <div className="flex justify-between text-xs mb-2">
                        <span className="text-[#888] font-medium">
                            {gymClass.spots_left === 0 ? 'Class full' : `${gymClass.spots_left} spots left`}
                        </span>
                        <span className="text-[#555]">{gymClass.capacity} total</span>
                    </div>
                    <div className="h-2 w-full rounded-full bg-[#DDD5C0] overflow-hidden">
                        <div
                            className="h-full rounded-full bg-[#FFF34D] transition-all"
                            style={{ width: `${pct}%` }}
                        />
                    </div>
                </div>

                {/* Cancellation policy */}
                {cancelMsg && !isPast && (
                    <p className={[
                        'text-xs text-center mb-4',
                        gymClass.credit_charged && !gymClass.will_refund_credit && gymClass.is_booked
                            ? 'text-red-400 font-semibold'
                            : 'text-[#888]',
                    ].join(' ')}>
                        {cancelMsg}
                    </p>
                )}

                {/* Error */}
                {form.errors.gym_class_id && (
                    <div className="mb-4 rounded-2xl bg-red-500/10 border border-red-500/30 px-4 py-3 text-red-400 text-sm font-medium">
                        ⚠ {form.errors.gym_class_id}
                    </div>
                )}

                {/* CTA */}
                {gymClass.is_booked ? (
                    confirmCancel ? (
                        <div className="space-y-2">
                            <p className="text-sm text-center text-[#555] mb-1">
                                {gymClass.will_refund_credit
                                    ? <>Cancel this booking? <strong className="text-[#333E48]">1 credit</strong> will be refunded.</>
                                    : gymClass.credit_charged
                                        ? <>Cancel this booking? <strong className="text-red-500">Your credit will NOT be refunded</strong> (late cancellation).</>
                                        : 'Cancel this booking? (No credit was charged — unlimited booking.)'}
                            </p>
                            <button onClick={cancel} disabled={form.processing}
                                className="w-full rounded-2xl bg-red-500/20 border border-red-500/30 py-3.5 text-sm font-bold text-red-400 hover:bg-red-500/30 transition-colors disabled:opacity-50">
                                {form.processing ? 'Cancelling…' : 'Yes, cancel my booking'}
                            </button>
                            <button onClick={() => setConfirmCancel(false)}
                                className="w-full rounded-2xl bg-[#CFE0EB] border border-[#DDD5C0] py-3 text-sm font-medium text-[#333E48] hover:bg-[#b8d4e2] transition-colors">
                                Keep my booking
                            </button>
                        </div>
                    ) : (
                        <button onClick={() => setConfirmCancel(true)}
                            className="w-full rounded-2xl bg-red-500/20 border border-red-500/30 py-3.5 text-sm font-bold text-red-400 hover:bg-red-500/30 transition-colors">
                            Cancel Booking
                        </button>
                    )
                ) : gymClass.is_waitlisted ? (
                    <div className="space-y-2">
                        <div className="w-full rounded-2xl bg-amber-400/10 border border-amber-400/30 py-3 text-center">
                            <p className="text-sm font-bold text-amber-600">On Waitlist — Position #{gymClass.queue_position}</p>
                        </div>
                        <button onClick={cancel} disabled={form.processing}
                            className="w-full rounded-2xl bg-red-500/10 border border-red-500/20 py-3 text-sm font-semibold text-red-400 hover:bg-red-500/20 transition-colors disabled:opacity-50">
                            {form.processing ? 'Removing…' : 'Leave Waitlist'}
                        </button>
                    </div>
                ) : gymClass.is_full ? (
                    <button onClick={book} disabled={form.processing}
                        className="w-full rounded-2xl bg-amber-400/20 border border-amber-400/40 py-3.5 text-sm font-black text-amber-700 hover:bg-amber-400/30 active:scale-[0.98] transition-all disabled:opacity-50">
                        {form.processing ? 'Joining…' : 'Join Waitlist'}
                    </button>
                ) : (
                    <button onClick={book} disabled={form.processing}
                        className="w-full rounded-2xl bg-[#FFF34D] py-3.5 text-sm font-black text-[#333E48] hover:bg-[#FFE633] active:scale-[0.98] transition-all disabled:opacity-50">
                        {form.processing ? 'Booking…' : 'Book Now'}
                    </button>
                )}
            </div>
        </div>
    );
}

// ─── Class Card ───────────────────────────────────────────────────────────────

const CATEGORY_COLORS = {
    cardio:   'text-orange-400 bg-orange-400/10 border-orange-400/20',
    hiit:     'text-red-400 bg-red-400/10 border-red-400/20',
    yoga:     'text-purple-400 bg-purple-400/10 border-purple-400/20',
    spin:     'text-blue-400 bg-blue-400/10 border-blue-400/20',
    strength: 'text-[#6A7A00] bg-[#FFF34D]/20 border-[#BFD857]/40',
    default:  'text-[#6A7A00] bg-[#FFF34D]/20 border-[#BFD857]/40',
};

function getCategoryColor(name) {
    const lower = name.toLowerCase();
    if (lower.includes('cardio'))   return CATEGORY_COLORS.cardio;
    if (lower.includes('hiit'))     return CATEGORY_COLORS.hiit;
    if (lower.includes('yoga'))     return CATEGORY_COLORS.yoga;
    if (lower.includes('spin'))     return CATEGORY_COLORS.spin;
    if (lower.includes('strength')) return CATEGORY_COLORS.strength;
    return CATEGORY_COLORS.default;
}

function ClassCard({ gymClass, onSelect }) {
    const start    = parseLocalDT(gymClass.start_time);
    const icon     = getActivityType(gymClass.name.toLowerCase().split(' ')[0]).icon;
    const pct      = Math.round(((gymClass.capacity - gymClass.spots_left) / gymClass.capacity) * 100);
    const catColor = getCategoryColor(gymClass.name);
    const timeStr  = start.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });

    return (
        <article
            role="button"
            tabIndex={0}
            onClick={() => onSelect(gymClass)}
            onKeyDown={(e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); onSelect(gymClass); } }}
            aria-label={`${gymClass.name} with ${gymClass.coach}, ${timeStr}${gymClass.is_booked ? ' — booked' : gymClass.is_full ? ' — full' : ''}`}
            className="bg-[#FFFFFF] rounded-3xl border border-[#DDD5C0] p-5 cursor-pointer hover:border-[#FFF34D]/30 active:scale-[0.99] transition-all"
        >
            {/* Top row: time + booked badge */}
            <div className="flex items-start justify-between mb-3">
                <span className="text-2xl font-black text-[#333E48]">{timeStr}</span>
                {gymClass.is_booked ? (
                    <span className="text-xs font-black text-[#333E48] bg-[#FFF34D] px-3 py-1 rounded-full">
                        BOOKED
                    </span>
                ) : gymClass.is_waitlisted ? (
                    <span className="text-xs font-bold text-amber-600 bg-amber-400/20 border border-amber-400/30 px-3 py-1 rounded-full">
                        WAITLIST #{gymClass.queue_position}
                    </span>
                ) : gymClass.is_full ? (
                    <span className="text-xs font-bold text-red-400 bg-red-400/10 border border-red-400/20 px-3 py-1 rounded-full">
                        FULL
                    </span>
                ) : null}
            </div>

            {/* Category badge */}
            <span className={`inline-flex items-center gap-1.5 text-xs font-bold px-3 py-1 rounded-full border mb-3 ${catColor}`}>
                <span>{icon}</span>
                {gymClass.name.split(' ')[0]}
            </span>

            {/* Class name */}
            <h2 className="text-lg font-black text-[#333E48] mb-1">{gymClass.name}</h2>

            {/* Coach */}
            <p className="text-sm text-[#666] mb-4">with {gymClass.coach}</p>

            {/* Spots bar */}
            <div>
                <div className="flex justify-between text-xs mb-1.5">
                    <span className="text-[#666]">Spots Available</span>
                    <span className={gymClass.spots_left <= 3 ? 'text-red-400 font-bold' : 'text-[#888]'}>
                        {gymClass.spots_left} / {gymClass.capacity}
                    </span>
                </div>
                <div className="h-1.5 w-full rounded-full bg-[#DDD5C0] overflow-hidden">
                    <div
                        className={`h-full rounded-full transition-all ${
                            gymClass.spots_left === 0 ? 'bg-red-500'
                            : gymClass.spots_left <= 3 ? 'bg-amber-400'
                            : 'bg-[#FFF34D]'
                        }`}
                        style={{ width: `${pct}%` }}
                    />
                </div>
            </div>
        </article>
    );
}

// ─── Date Label Helper ────────────────────────────────────────────────────────

function getDateLabel(dateStr) {
    const today = new Date();
    const tomorrow = new Date(today);
    tomorrow.setDate(today.getDate() + 1);

    const date = new Date(dateStr + 'T00:00:00');
    const todayStr  = today.toISOString().slice(0, 10);
    const tomorrowStr = tomorrow.toISOString().slice(0, 10);

    if (dateStr === todayStr)     return 'Today';
    if (dateStr === tomorrowStr)  return 'Tomorrow';
    return date.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric' });
}

// ─── Day nav helpers ──────────────────────────────────────────────────────────

function todayDateStr() {
    const t = new Date();
    return `${t.getFullYear()}-${String(t.getMonth() + 1).padStart(2, '0')}-${String(t.getDate()).padStart(2, '0')}`;
}

// ─── Page ─────────────────────────────────────────────────────────────────────

export default function Schedule({ classes }) {
    const [detailClass, setDetailClass] = useState(null);

    // All unique sorted dates in the schedule window
    const allDates = [...new Set(classes.map((c) => c.date_label))].sort();
    const todayStr = todayDateStr();

    // Default to today if present, else the first date
    const [selectedDate, setSelectedDate] = useState(() =>
        allDates.includes(todayStr) ? todayStr : (allDates[0] ?? todayStr)
    );

    // Ensure selected date is always in bounds when classes change
    const effectiveDate = allDates.includes(selectedDate) ? selectedDate : (allDates[0] ?? selectedDate);

    const selectedIdx = allDates.indexOf(effectiveDate);
    const canPrev = selectedIdx > 0;
    const canNext = selectedIdx < allDates.length - 1;

    const dayClasses = classes.filter((c) => c.date_label === effectiveDate);

    function goToDate(d) { setSelectedDate(d); }
    function goPrev() { if (canPrev) setSelectedDate(allDates[selectedIdx - 1]); }
    function goNext() { if (canNext) setSelectedDate(allDates[selectedIdx + 1]); }

    return (
        <AppLayout active="Schedule">
            {detailClass && (
                <ClassDetailModal gymClass={detailClass} onClose={() => setDetailClass(null)} />
            )}

            {/* Header */}
            <div className="mb-4">
                <h1 className="text-2xl font-black text-[#333E48]">Schedule</h1>
            </div>

            <FlashMessage />

            {/* Day navigation */}
            {allDates.length > 0 && (
                <div className="flex items-center gap-2 mb-5">
                    <button
                        onClick={goPrev}
                        disabled={!canPrev}
                        className="w-9 h-9 flex items-center justify-center rounded-full bg-[#DDD5C0] text-[#333E48] font-bold text-lg disabled:opacity-30 transition-opacity"
                        aria-label="Previous day"
                    >
                        ‹
                    </button>

                    <div className="flex-1 text-center">
                        <p className="text-lg font-black text-[#333E48]">{getDateLabel(effectiveDate)}</p>
                        <p className="text-xs text-[#888] mt-0.5">
                            {new Date(effectiveDate + 'T00:00:00').toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric' })}
                        </p>
                    </div>

                    <button
                        onClick={goNext}
                        disabled={!canNext}
                        className="w-9 h-9 flex items-center justify-center rounded-full bg-[#DDD5C0] text-[#333E48] font-bold text-lg disabled:opacity-30 transition-opacity"
                        aria-label="Next day"
                    >
                        ›
                    </button>
                </div>
            )}

            {/* Today button — show only when not already on today */}
            {effectiveDate !== todayStr && allDates.includes(todayStr) && (
                <div className="flex justify-center mb-4">
                    <button
                        onClick={() => goToDate(todayStr)}
                        className="text-xs font-bold text-[#333E48] bg-[#FFF34D] px-4 py-1.5 rounded-full hover:bg-[#FFE633] transition-colors"
                    >
                        Jump to Today
                    </button>
                </div>
            )}

            {/* Class list for selected day */}
            {classes.length === 0 ? (
                <div className="text-center py-20">
                    <p className="text-5xl mb-4">🗓️</p>
                    <p className="font-bold text-[#333E48]">No upcoming classes</p>
                    <p className="text-sm mt-1 text-[#666]">Check back soon</p>
                </div>
            ) : dayClasses.length === 0 ? (
                <div className="text-center py-16">
                    <p className="text-4xl mb-3">🏖️</p>
                    <p className="font-bold text-[#333E48]">No classes this day</p>
                    <p className="text-sm mt-1 text-[#666]">Try another day</p>
                </div>
            ) : (
                <div className="flex flex-col gap-4">
                    {dayClasses.map(cls => (
                        <ClassCard key={cls.id} gymClass={cls} onSelect={setDetailClass} />
                    ))}
                </div>
            )}

            {/* Day dots — quick nav */}
            {allDates.length > 1 && (
                <div className="flex justify-center gap-1.5 mt-8 pb-4">
                    {allDates.map((d) => (
                        <button
                            key={d}
                            onClick={() => goToDate(d)}
                            aria-label={getDateLabel(d)}
                            className={[
                                'w-2 h-2 rounded-full transition-all',
                                d === effectiveDate
                                    ? 'bg-[#FFF34D] scale-125'
                                    : d === todayStr
                                        ? 'bg-[#BFD857]'
                                        : 'bg-[#DDD5C0]',
                            ].join(' ')}
                        />
                    ))}
                </div>
            )}
        </AppLayout>
    );
}
