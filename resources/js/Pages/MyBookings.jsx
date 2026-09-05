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

function CancelButton({ booking }) {
    const form = useForm({ gym_class_id: booking.gym_class_id });
    const cancel = () => form.delete(route('bookings.destroy'), { preserveScroll: true });

    return (
        <button
            onClick={cancel}
            disabled={form.processing}
            className="text-xs font-semibold text-red-400 hover:text-red-500 transition-colors disabled:opacity-50"
        >
            {form.processing ? 'Cancelling…' : 'Cancel'}
        </button>
    );
}

function BookingCard({ booking, showCancel = false }) {
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

    return (
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
                    {showCancel && (booking.status === 'booked' || booking.status === 'waitlisted') && (
                        <CancelButton booking={booking} />
                    )}
                </div>
            </div>
        </div>
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
