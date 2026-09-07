import { useState } from 'react';
import { useForm, usePage } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';

function FlashMessage() {
    const { flash } = usePage().props;
    if (!flash?.success) return null;
    return (
        <div className="mb-4 rounded-2xl bg-[#FFF34D]/10 border border-[#FFF34D]/30 px-4 py-3 text-[#5A6A35] text-sm font-medium">
            {flash.success}
        </div>
    );
}

function formatDate(isoStr) {
    if (!isoStr) return '';
    const d = new Date(isoStr);
    return d.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric' });
}

function formatTime(isoStr) {
    if (!isoStr) return '';
    const d = new Date(isoStr);
    return d.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });
}

// ─── Cancel confirmation modal ────────────────────────────────────────────────

function CancelModal({ booking, onClose }) {
    const form = useForm({ gym_class_id: booking.gym_class_id });

    function confirm() {
        form.delete(route('bookings.destroy'), {
            preserveScroll: true,
            onSuccess: onClose,
        });
    }

    const isWaitlist   = booking.status === 'waitlisted';
    const willRefund   = booking.will_refund_credit;
    const creditCharged = booking.credit_charged;

    let refundNote;
    if (isWaitlist) {
        refundNote = 'You were on the waitlist and were not charged a credit.';
    } else if (!creditCharged) {
        refundNote = 'No credit was charged (unlimited package) — nothing will be refunded.';
    } else if (willRefund) {
        refundNote = '1 credit will be returned to your account.';
    } else {
        refundNote = 'This is a late cancellation — your credit will NOT be refunded.';
    }

    const cls = booking.gym_class;

    return (
        <div className="fixed inset-0 z-50 flex items-end justify-center" aria-modal="true">
            <div className="absolute inset-0 bg-black/60 backdrop-blur-sm" onClick={onClose} />
            <div className="relative w-full max-w-lg bg-white rounded-t-3xl p-6 pb-10 shadow-2xl">
                <div className="mx-auto mb-4 w-10 h-1 rounded-full bg-gray-200" />
                <h2 className="font-black text-lg text-[#333E48] mb-1">Cancel Booking?</h2>
                {cls && (
                    <p className="text-sm text-gray-500 mb-4">
                        {cls.name} · {formatDate(cls.start_time)} {formatTime(cls.start_time)}
                    </p>
                )}
                <div className={[
                    'rounded-2xl px-4 py-3 text-sm mb-5',
                    !willRefund && creditCharged && !isWaitlist
                        ? 'bg-red-50 border border-red-200 text-red-700'
                        : 'bg-amber-50 border border-amber-200 text-amber-800',
                ].join(' ')}>
                    {refundNote}
                </div>
                <div className="space-y-2">
                    <button
                        onClick={confirm}
                        disabled={form.processing}
                        className="w-full rounded-2xl bg-red-500/20 border border-red-500/30 py-3.5 text-sm font-bold text-red-500 hover:bg-red-500/30 transition-colors disabled:opacity-50"
                    >
                        {form.processing ? 'Cancelling…' : (isWaitlist ? 'Leave Waitlist' : 'Yes, cancel my booking')}
                    </button>
                    <button
                        onClick={onClose}
                        className="w-full rounded-2xl bg-[#CFE0EB] border border-[#DDD5C0] py-3 text-sm font-medium text-[#333E48] hover:bg-[#b8d4e2] transition-colors"
                    >
                        Keep my {isWaitlist ? 'spot' : 'booking'}
                    </button>
                </div>
            </div>
        </div>
    );
}

// ─── Booking card ─────────────────────────────────────────────────────────────

function BookingCard({ booking, showCancel = false }) {
    const [showModal, setShowModal] = useState(false);
    const cls = booking.gym_class;
    if (!cls) return null;

    const statusColors = {
        booked:      'bg-[#FFF34D] text-[#333E48]',
        checked_in:  'bg-green-400/20 text-green-700 border border-green-400/30',
        waitlisted:  'bg-amber-400/20 text-amber-700 border border-amber-400/30',
        late_cancel: 'bg-red-400/10 text-red-500 border border-red-400/20',
        cancelled:   'bg-gray-200 text-gray-500',
        no_show:     'bg-red-400/10 text-red-500 border border-red-400/20',
    };

    const statusLabels = {
        booked:      'Booked',
        checked_in:  'Checked In',
        waitlisted:  `Waitlist #${booking.queue_position}`,
        late_cancel: 'Late Cancel',
        cancelled:   'Cancelled',
        no_show:     'No Show',
    };

    const colorClass = statusColors[booking.status] ?? 'bg-gray-200 text-gray-500';
    const label      = statusLabels[booking.status] ?? booking.status;
    const canCancel  = showCancel && booking.can_cancel && (booking.status === 'booked' || booking.status === 'waitlisted');

    return (
        <>
            <div className="bg-white rounded-3xl border border-[#DDD5C0] p-4">
                <div className="flex items-start justify-between gap-2">
                    <div className="flex-1 min-w-0">
                        <p className="font-black text-[#333E48] truncate">{cls.name}</p>
                        <p className="text-xs text-[#666] mt-0.5">with {cls.coach}</p>
                        <p className="text-xs text-[#888] mt-1">
                            {formatDate(cls.start_time)} · {formatTime(cls.start_time)}
                            {cls.location && ` · ${cls.location}`}
                        </p>
                    </div>
                    <div className="flex flex-col items-end gap-2">
                        <span className={`text-[10px] font-bold px-2.5 py-1 rounded-full whitespace-nowrap ${colorClass}`}>
                            {label}
                        </span>
                        {canCancel && (
                            <button
                                onClick={() => setShowModal(true)}
                                className="text-xs font-semibold text-red-400 hover:text-red-500 transition-colors"
                            >
                                Cancel
                            </button>
                        )}
                    </div>
                </div>
            </div>
            {showModal && <CancelModal booking={booking} onClose={() => setShowModal(false)} />}
        </>
    );
}

function Section({ title, children, empty }) {
    return (
        <div className="mb-8">
            <h2 className="text-sm font-bold text-[#555] uppercase tracking-wider mb-3">{title}</h2>
            {children ?? (
                <p className="text-sm text-[#888] text-center py-6">{empty}</p>
            )}
        </div>
    );
}

export default function MyBookings({ upcoming, waitlisted, past }) {
    return (
        <AppLayout active="Bookings">
            <div className="mb-6">
                <h1 className="text-2xl font-black text-[#333E48]">My Bookings</h1>
                <p className="text-sm text-[#666] mt-0.5">Upcoming and past classes</p>
            </div>

            <FlashMessage />

            <Section title="Upcoming" empty="No upcoming bookings">
                {upcoming.length > 0 && (
                    <div className="flex flex-col gap-3">
                        {upcoming.map(b => (
                            <BookingCard key={b.id} booking={b} showCancel />
                        ))}
                    </div>
                )}
            </Section>

            {waitlisted.length > 0 && (
                <Section title="Waitlisted">
                    <div className="flex flex-col gap-3">
                        {waitlisted.map(b => (
                            <BookingCard key={b.id} booking={b} showCancel />
                        ))}
                    </div>
                </Section>
            )}

            <Section title="Past 30 Days" empty="No recent classes">
                {past.length > 0 && (
                    <div className="flex flex-col gap-3">
                        {past.map(b => (
                            <BookingCard key={b.id} booking={b} />
                        ))}
                    </div>
                )}
            </Section>
        </AppLayout>
    );
}
