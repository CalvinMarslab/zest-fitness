import { useState, useMemo } from 'react';
import { useForm, router, Link } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';

// ─── Helpers ─────────────────────────────────────────────────────────────────

function fmt(isoStr) {
    if (!isoStr) return '—';
    return new Date(isoStr).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
}

function fmtShort(isoStr) {
    if (!isoStr) return '—';
    return new Date(isoStr).toLocaleDateString('en-GB', { day: 'numeric', month: 'short' });
}

function fmtTime(isoStr) {
    if (!isoStr) return '—';
    return new Date(isoStr).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });
}

function fmtDT(isoStr) {
    if (!isoStr) return '—';
    const d = new Date(isoStr);
    return d.toLocaleDateString('en-GB', { day: 'numeric', month: 'short' }) + ' ' +
           d.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });
}

// ─── Assign Package modal ─────────────────────────────────────────────────────

function AssignPackageModal({ member, packages, onClose }) {
    const form = useForm({ package_id: '', started_at: '' });

    function submit(e) {
        e.preventDefault();
        form.post(route('admin.users.subscriptions.store', member.id), { onSuccess: onClose });
    }

    return (
        <div className="fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4" onClick={onClose}>
            <div className="bg-white rounded-2xl p-6 w-full max-w-md shadow-2xl" onClick={(e) => e.stopPropagation()}>
                <h2 className="font-bold text-lg mb-4">Assign Package — {member.name}</h2>
                <form onSubmit={submit} className="flex flex-col gap-4">
                    <div>
                        <label className="text-xs font-semibold text-gray-500 uppercase">Package</label>
                        <select
                            required
                            className="mt-1 w-full border rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-orange-300"
                            value={form.data.package_id}
                            onChange={(e) => form.setData('package_id', e.target.value)}
                        >
                            <option value="">Select a package…</option>
                            {packages.map((p) => (
                                <option key={p.id} value={p.id}>
                                    {p.name} — RM {parseFloat(p.price).toFixed(0)} / {p.period_label}
                                    {p.is_unlimited ? ' (Unlimited)' : ` (${p.credits} cr)`}
                                </option>
                            ))}
                        </select>
                    </div>
                    <div>
                        <label className="text-xs font-semibold text-gray-500 uppercase">Start Date (optional)</label>
                        <input
                            type="date"
                            className="mt-1 w-full border rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-orange-300"
                            value={form.data.started_at}
                            onChange={(e) => form.setData('started_at', e.target.value)}
                        />
                    </div>
                    {form.errors.package_id && <p className="text-xs text-red-500">{form.errors.package_id}</p>}
                    <div className="flex gap-2 mt-2">
                        <button type="button" onClick={onClose}
                            className="flex-1 py-2 rounded-xl border text-sm text-gray-600">Cancel</button>
                        <button type="submit" disabled={form.processing}
                            className="flex-1 py-2 rounded-xl bg-orange-500 text-white text-sm font-semibold disabled:opacity-60">
                            {form.processing ? 'Assigning…' : 'Assign Package'}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}

// ─── Adjust Credits modal ─────────────────────────────────────────────────────

function AdjustCreditsModal({ member, onClose }) {
    const activeSubs = (member.subscriptions ?? []).filter(
        (s) => s.status === 'active' && !s.is_unlimited && new Date(s.expires_at) > new Date()
    );
    const form = useForm({ subscription_id: activeSubs[0]?.id ?? '', adjustment: '', notes: '' });

    function submit(e) {
        e.preventDefault();
        form.patch(route('admin.users.credits.update', member.id), { onSuccess: onClose });
    }

    if (activeSubs.length === 0) {
        return (
            <div className="fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4" onClick={onClose}>
                <div className="bg-white rounded-2xl p-6 w-full max-w-sm shadow-2xl text-center" onClick={(e) => e.stopPropagation()}>
                    <p className="font-bold text-gray-700 mb-2">No Active Credit Subscription</p>
                    <p className="text-sm text-gray-500 mb-4">Assign a credit-based package first.</p>
                    <button onClick={onClose} className="px-4 py-2 rounded-xl bg-gray-100 text-sm text-gray-700">Close</button>
                </div>
            </div>
        );
    }

    return (
        <div className="fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4" onClick={onClose}>
            <div className="bg-white rounded-2xl p-6 w-full max-w-md shadow-2xl" onClick={(e) => e.stopPropagation()}>
                <h2 className="font-bold text-lg mb-4">Adjust Credits — {member.name}</h2>
                <form onSubmit={submit} className="flex flex-col gap-4">
                    {activeSubs.length > 1 && (
                        <div>
                            <label className="text-xs font-semibold text-gray-500 uppercase">Subscription</label>
                            <select
                                className="mt-1 w-full border rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-orange-300"
                                value={form.data.subscription_id}
                                onChange={(e) => form.setData('subscription_id', e.target.value)}
                            >
                                {activeSubs.map((s) => (
                                    <option key={s.id} value={s.id}>
                                        {s.package?.name} — {s.credits_remaining} cr (exp {fmt(s.expires_at)})
                                    </option>
                                ))}
                            </select>
                        </div>
                    )}
                    <div>
                        <label className="text-xs font-semibold text-gray-500 uppercase">Adjustment (+/−)</label>
                        <input
                            type="number"
                            required
                            placeholder="e.g. +2 or -1"
                            className="mt-1 w-full border rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-orange-300"
                            value={form.data.adjustment}
                            onChange={(e) => form.setData('adjustment', e.target.value)}
                        />
                    </div>
                    <div>
                        <label className="text-xs font-semibold text-gray-500 uppercase">Reason / Notes</label>
                        <input
                            className="mt-1 w-full border rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-orange-300"
                            placeholder="e.g. Makeup class, bonus, correction…"
                            value={form.data.notes}
                            onChange={(e) => form.setData('notes', e.target.value)}
                        />
                    </div>
                    {form.errors.adjustment && <p className="text-xs text-red-500">{form.errors.adjustment}</p>}
                    <div className="flex gap-2 mt-2">
                        <button type="button" onClick={onClose}
                            className="flex-1 py-2 rounded-xl border text-sm text-gray-600">Cancel</button>
                        <button type="submit" disabled={form.processing}
                            className="flex-1 py-2 rounded-xl bg-orange-500 text-white text-sm font-semibold disabled:opacity-60">
                            {form.processing ? 'Saving…' : 'Apply Adjustment'}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}

// ─── Book for Member modal ────────────────────────────────────────────────────

function capacityLabel(spotsLeft, waitlistCount) {
    if (spotsLeft > 0) return { text: `${spotsLeft} spot${spotsLeft === 1 ? '' : 's'}`, cls: 'text-green-700 bg-green-50' };
    if (waitlistCount > 0) return { text: 'Waitlist', cls: 'text-amber-600 bg-amber-50' };
    return { text: 'Full', cls: 'text-red-500 bg-red-50' };
}

function dateGroupHeader(isoStr) {
    const d = new Date(isoStr);
    const today = new Date();
    const tomorrow = new Date(today);
    tomorrow.setDate(today.getDate() + 1);
    const dayStr = d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
    if (d.toDateString() === today.toDateString()) return `TODAY · ${dayStr.toUpperCase()}`;
    if (d.toDateString() === tomorrow.toDateString()) return `TOMORROW · ${dayStr.toUpperCase()}`;
    return d.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric' }).toUpperCase();
}

function dateKey(isoStr) {
    return new Date(isoStr).toDateString();
}

function BookForMemberModal({ member, upcomingClasses, onClose }) {
    const [search, setSearch]             = useState('');
    const [selectedClass, setSelectedClass] = useState(null);
    const [submitting, setSubmitting]     = useState(false);
    const [bookingError, setBookingError] = useState(null);

    // Group classes by calendar date
    const groups = useMemo(() => {
        const q = search.trim().toLowerCase();
        const filtered = (upcomingClasses ?? []).filter((c) => {
            if (!q) return true;
            return c.name.toLowerCase().includes(q) || (c.coach ?? '').toLowerCase().includes(q);
        });

        const map = new Map();
        filtered.forEach((c) => {
            const key = dateKey(c.start_time);
            if (!map.has(key)) map.set(key, { header: dateGroupHeader(c.start_time), classes: [] });
            map.get(key).classes.push(c);
        });
        return [...map.values()];
    }, [upcomingClasses, search]);

    function book() {
        if (!selectedClass || submitting) return;
        setSubmitting(true);
        setBookingError(null);
        // FIX: pass payload directly to router.post — never use form.setData + form.post
        // because setData triggers async React state; the stale gym_class_id: '' is submitted.
        router.post(
            route('admin.users.bookings.store', member.id),
            { gym_class_id: selectedClass.id },
            {
                preserveScroll: true,
                onSuccess: onClose,
                onError: (errors) => {
                    setBookingError(
                        errors.booking ?? errors.gym_class_id ?? 'Booking failed. Please try again.'
                    );
                    setSubmitting(false);
                },
                onFinish: () => setSubmitting(false),
            }
        );
    }

    const hasClasses = groups.some((g) => g.classes.length > 0);

    return (
        <div className="fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4" onClick={onClose}>
            <div
                className="bg-white rounded-2xl w-full max-w-md shadow-2xl flex flex-col"
                style={{ maxHeight: '85vh' }}
                onClick={(e) => e.stopPropagation()}
            >
                {/* ── Fixed header ── */}
                <div className="flex-none px-5 pt-5 pb-3 border-b border-gray-50">
                    <h2 className="font-bold text-base mb-3">Book Class for {member.name}</h2>
                    <input
                        type="text"
                        placeholder="Search class name or coach…"
                        className="w-full border rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-orange-300"
                        value={search}
                        onChange={(e) => { setSearch(e.target.value); setSelectedClass(null); }}
                        autoFocus
                    />
                </div>

                {/* ── Scrollable class list ── */}
                <div className="flex-1 overflow-y-auto min-h-0">
                    {!hasClasses ? (
                        <p className="text-center text-sm text-gray-400 py-8">
                            {search ? `No classes matching "${search}"` : 'No upcoming classes.'}
                        </p>
                    ) : (
                        groups.map((group) => (
                            <div key={group.header}>
                                <div className="sticky top-0 px-4 py-2 bg-gray-50 border-b border-gray-100">
                                    <p className="text-[10px] font-bold uppercase tracking-widest text-gray-500">
                                        {group.header}
                                    </p>
                                </div>
                                {group.classes.map((c) => {
                                    const isSelected = selectedClass?.id === c.id;
                                    const cap = capacityLabel(c.spots_left, c.waitlist_count ?? 0);
                                    const coachDisplay = c.coach || 'Unassigned';
                                    return (
                                        <button
                                            key={c.id}
                                            type="button"
                                            onClick={() => { setSelectedClass(isSelected ? null : c); setBookingError(null); }}
                                            className={[
                                                'w-full text-left px-4 py-3 border-b border-gray-50 transition-colors',
                                                isSelected ? 'bg-orange-50' : 'hover:bg-gray-50',
                                            ].join(' ')}
                                        >
                                            <div className="flex items-center justify-between gap-2">
                                                <div className="flex items-center gap-3 min-w-0">
                                                    {isSelected && (
                                                        <div className="w-4 h-4 rounded-full bg-orange-500 flex items-center justify-center shrink-0">
                                                            <div className="w-1.5 h-1.5 rounded-full bg-white" />
                                                        </div>
                                                    )}
                                                    <div className="min-w-0">
                                                        <div className="flex items-center gap-2">
                                                            <p className={`text-sm font-semibold truncate ${isSelected ? 'text-orange-700' : 'text-gray-900'}`}>
                                                                {c.name}
                                                            </p>
                                                        </div>
                                                        <p className="text-xs text-gray-400">
                                                            {fmtTime(c.start_time)} · {coachDisplay}
                                                        </p>
                                                    </div>
                                                </div>
                                                <span className={`text-[10px] font-bold px-1.5 py-0.5 rounded-full shrink-0 ${cap.cls}`}>
                                                    {cap.text}
                                                </span>
                                            </div>
                                        </button>
                                    );
                                })}
                            </div>
                        ))
                    )}
                </div>

                {/* ── Selected confirmation ── */}
                {selectedClass && (
                    <div className="flex-none border-t border-orange-100 bg-orange-50 px-5 py-3">
                        <p className="text-[10px] font-bold uppercase tracking-widest text-orange-500 mb-0.5">Selected</p>
                        <p className="text-sm font-bold text-orange-700">{selectedClass.name}</p>
                        <p className="text-xs text-orange-500">
                            {fmtShort(selectedClass.start_time)} · {fmtTime(selectedClass.start_time)}
                            {selectedClass.coach ? ` · ${selectedClass.coach}` : ''}
                        </p>
                    </div>
                )}

                {/* ── Error ── */}
                {bookingError && (
                    <div className="flex-none border-t border-red-100 bg-red-50 px-5 py-2">
                        <p className="text-xs text-red-600 font-medium">{bookingError}</p>
                    </div>
                )}

                {/* ── Fixed footer ── */}
                <div className="flex-none border-t border-gray-100 px-5 py-4 flex gap-2">
                    <button
                        type="button"
                        onClick={onClose}
                        className="flex-1 py-2.5 rounded-xl border text-sm text-gray-600 hover:bg-gray-50"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        disabled={!selectedClass || submitting}
                        onClick={book}
                        className="flex-1 py-2.5 rounded-xl bg-orange-500 text-white text-sm font-semibold disabled:opacity-60 transition-colors hover:bg-orange-600"
                    >
                        {submitting ? 'Booking…' : selectedClass ? `Book into ${selectedClass.name}` : 'Select a class'}
                    </button>
                </div>
            </div>
        </div>
    );
}

// ─── Booking status badge ─────────────────────────────────────────────────────

function BookingStatusBadge({ status }) {
    const cfg = {
        booked:      ['bg-yellow-100 text-yellow-700', 'Booked'],
        checked_in:  ['bg-green-100 text-green-700', 'Checked In'],
        waitlisted:  ['bg-amber-100 text-amber-700', 'Waitlisted'],
        late_cancel: ['bg-red-100 text-red-600', 'Late Cancel'],
        cancelled:   ['bg-gray-100 text-gray-500', 'Cancelled'],
        no_show:     ['bg-red-100 text-red-600', 'No Show'],
    };
    const [cls, label] = cfg[status] ?? ['bg-gray-100 text-gray-500', status];
    return (
        <span className={`text-[10px] font-bold px-1.5 py-0.5 rounded-full ${cls}`}>{label}</span>
    );
}

// ─── Entitlement card ─────────────────────────────────────────────────────────

function EntitlementCard({ sub }) {
    const isUnlimited = sub.is_unlimited;
    const hasWeeklyLimit = sub.package?.weekly_booking_limit;
    const expDays = sub.expires_at
        ? Math.ceil((new Date(sub.expires_at) - new Date()) / 86400000)
        : null;
    const expSoon = expDays !== null && expDays <= 14;

    return (
        <div className={`rounded-2xl border p-4 ${
            isUnlimited
                ? 'border-purple-100 bg-purple-50'
                : expSoon
                    ? 'border-orange-100 bg-orange-50'
                    : 'border-gray-100 bg-gray-50'
        }`}>
            <div className="flex items-start justify-between gap-2 mb-2">
                <p className="text-sm font-bold text-gray-900">{sub.package?.name ?? '—'}</p>
                {isUnlimited ? (
                    <span className="text-[10px] font-bold text-purple-600 bg-purple-100 px-1.5 py-0.5 rounded-full">Unlimited</span>
                ) : (
                    <span className="text-[10px] font-bold text-green-600 bg-green-100 px-1.5 py-0.5 rounded-full">Active</span>
                )}
            </div>
            <div className="flex items-baseline gap-2">
                {isUnlimited ? (
                    <span className="text-2xl font-black text-purple-600">∞</span>
                ) : (
                    <span className="text-2xl font-black text-gray-900">{sub.credits_remaining}</span>
                )}
                {!isUnlimited && (
                    <span className="text-xs text-gray-400">/ {sub.credits_granted} credits</span>
                )}
                {hasWeeklyLimit && (
                    <span className="text-[10px] font-semibold text-blue-600 bg-blue-50 border border-blue-100 px-1.5 py-0.5 rounded-full">
                        {sub.package.weekly_booking_limit}/wk
                    </span>
                )}
            </div>
            <p className={`text-xs mt-1.5 font-medium ${expSoon ? 'text-orange-600' : 'text-gray-400'}`}>
                Exp {fmt(sub.expires_at)}
                {expSoon && expDays > 0 && ` · ${expDays}d left`}
                {expDays <= 0 && ' · Expires today'}
            </p>
        </div>
    );
}

// ─── Credit tx row ────────────────────────────────────────────────────────────

function TxRow({ tx }) {
    const positive = tx.amount > 0;
    const labels = {
        package_assigned:    'Package assigned',
        booking_deduction:   'Class booked',
        booking_refund:      'Refund',
        class_cancel_refund: 'Class cancelled',
        admin_adjustment:    'Admin adjustment',
        migration:           'Migration import',
        other:               'Other',
    };
    return (
        <div className="flex items-center gap-3 py-3 border-b border-gray-50 last:border-0">
            <div className={`w-8 h-8 rounded-full flex items-center justify-center text-sm font-black shrink-0 ${
                positive ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-600'
            }`}>
                {positive ? '+' : '−'}
            </div>
            <div className="flex-1 min-w-0">
                <p className="text-sm font-semibold text-gray-900">
                    {labels[tx.type] ?? tx.type}
                    {tx.booking?.gym_class?.name && (
                        <span className="font-normal text-gray-500"> · {tx.booking.gym_class.name}</span>
                    )}
                </p>
                <p className="text-xs text-gray-400">
                    {fmtDT(tx.created_at)}
                    {tx.actor?.name && ` · by ${tx.actor.name}`}
                    {tx.reason && ` · ${tx.reason}`}
                </p>
            </div>
            <div className="text-right shrink-0">
                <p className={`text-base font-black ${positive ? 'text-green-600' : 'text-red-500'}`}>
                    {positive ? `+${tx.amount}` : tx.amount}
                </p>
                <p className="text-[10px] text-gray-400">bal {tx.balance_after}</p>
            </div>
        </div>
    );
}

// ─── Page ─────────────────────────────────────────────────────────────────────

const BOOKING_TABS = [
    { key: 'all',        label: 'All' },
    { key: 'booked',     label: 'Booked' },
    { key: 'checked_in', label: 'Checked In' },
    { key: 'cancelled',  label: 'Cancelled' },
    { key: 'late_cancel', label: 'Late Cancel' },
    { key: 'no_show',    label: 'No Show' },
];

export default function UserProfile({ member, packages, upcomingClasses, upcomingBookings, recentBookings, creditHistory }) {
    const [modal, setModal]  = useState(null);
    const [histTab, setHistTab] = useState('all');
    const [showInactiveSubs, setShowInactiveSubs] = useState(false);

    const now = new Date();
    const activeSubs = (member.subscriptions ?? []).filter(
        (s) => s.status === 'active' && new Date(s.expires_at) > now
    );
    const inactiveSubs = (member.subscriptions ?? []).filter(
        (s) => !(s.status === 'active' && new Date(s.expires_at) > now)
    );

    const isSuspended = member.status === 'suspended';

    const filteredHistory = histTab === 'all'
        ? recentBookings
        : recentBookings.filter((b) => b.status === histTab);

    function cancelBookingForMember(bookingId) {
        if (!confirm('Cancel this booking and force-refund the credit?')) return;
        router.delete(route('admin.users.bookings.destroy', { user: member.id, booking: bookingId }));
    }

    function toggleSuspend() {
        const newStatus = isSuspended ? 'active' : 'suspended';
        const msg = isSuspended
            ? `Reactivate ${member.name}'s account?`
            : `Suspend ${member.name}'s account? They will not be able to book classes.`;
        if (!confirm(msg)) return;
        router.patch(route('admin.users.status.update', member.id), { status: newStatus });
    }

    return (
        <AdminLayout title={member.name}>

            {/* ── Member header ── */}
            <div className="bg-white rounded-2xl border border-gray-100 p-5 mb-5">
                <div className="flex items-start justify-between gap-3">
                    <div className="flex items-start gap-4">
                        <div className="w-12 h-12 rounded-full bg-orange-100 text-orange-600 text-lg font-black flex items-center justify-center shrink-0">
                            {member.name?.[0]?.toUpperCase() ?? '?'}
                        </div>
                        <div>
                            <div className="flex items-center gap-2 flex-wrap">
                                <h1 className="text-xl font-black text-gray-900">{member.name}</h1>
                                <span className={`text-[10px] font-bold px-2 py-0.5 rounded-full ${
                                    isSuspended
                                        ? 'bg-red-100 text-red-600'
                                        : 'bg-green-100 text-green-700'
                                }`}>
                                    {isSuspended ? 'Suspended' : 'Active'}
                                </span>
                            </div>
                            <p className="text-sm text-gray-500 mt-0.5">{member.email}</p>
                            {member.phone && <p className="text-xs text-gray-400 mt-0.5">{member.phone}</p>}
                        </div>
                    </div>
                    <div className="flex flex-col items-end gap-2 shrink-0">
                        <Link href={route('admin.users.index')} className="text-xs text-gray-400 hover:text-gray-600">
                            ← Members
                        </Link>
                        <button
                            onClick={toggleSuspend}
                            className={`text-xs font-semibold px-3 py-1.5 rounded-xl border transition-colors ${
                                isSuspended
                                    ? 'bg-green-50 text-green-700 border-green-200 hover:bg-green-100'
                                    : 'bg-red-50 text-red-600 border-red-200 hover:bg-red-100'
                            }`}
                        >
                            {isSuspended ? 'Reactivate' : 'Suspend'}
                        </button>
                    </div>
                </div>

                {/* Quick actions */}
                <div className="flex flex-wrap gap-2 mt-4 pt-4 border-t border-gray-50">
                    <button
                        onClick={() => setModal('book')}
                        className="px-4 py-2 rounded-xl bg-orange-500 text-white text-sm font-semibold hover:bg-orange-600 transition-colors"
                    >
                        + Book Class
                    </button>
                    <button
                        onClick={() => setModal('package')}
                        className="px-4 py-2 rounded-xl bg-gray-100 text-gray-700 text-sm font-semibold hover:bg-gray-200 transition-colors"
                    >
                        Assign Package
                    </button>
                    <button
                        onClick={() => setModal('credits')}
                        className="px-4 py-2 rounded-xl bg-gray-100 text-gray-700 text-sm font-semibold hover:bg-gray-200 transition-colors"
                    >
                        Adjust Credits
                    </button>
                </div>
            </div>

            {/* ── Active Entitlements ── */}
            <div className="mb-5">
                <div className="flex items-center justify-between mb-3">
                    <h2 className="text-sm font-bold text-gray-700 uppercase tracking-wide">Active Entitlements</h2>
                    <span className="text-xs text-gray-400">{activeSubs.length} active</span>
                </div>
                {activeSubs.length === 0 ? (
                    <div className="bg-white rounded-2xl border border-gray-100 px-5 py-6 text-center">
                        <p className="text-sm text-gray-400">No active packages.</p>
                        <button
                            onClick={() => setModal('package')}
                            className="mt-2 text-xs text-orange-500 font-semibold hover:text-orange-700"
                        >
                            + Assign Package
                        </button>
                    </div>
                ) : (
                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        {activeSubs.map((s) => <EntitlementCard key={s.id} sub={s} />)}
                    </div>
                )}

                {inactiveSubs.length > 0 && (
                    <button
                        type="button"
                        onClick={() => setShowInactiveSubs((v) => !v)}
                        className="mt-2 text-xs text-gray-400 hover:text-gray-600 font-medium"
                    >
                        {showInactiveSubs ? '▲ Hide' : '▼ Show'} {inactiveSubs.length} inactive package{inactiveSubs.length !== 1 ? 's' : ''}
                    </button>
                )}

                {showInactiveSubs && inactiveSubs.length > 0 && (
                    <div className="mt-2 bg-white rounded-2xl border border-gray-100 overflow-hidden">
                        {inactiveSubs.map((s, i) => (
                            <div key={s.id} className={`flex items-center justify-between px-4 py-3 ${i > 0 ? 'border-t border-gray-50' : ''}`}>
                                <div>
                                    <p className="text-sm text-gray-600">{s.package?.name ?? '—'}</p>
                                    <p className="text-xs text-gray-400">
                                        {s.is_unlimited ? '∞' : `${s.credits_remaining} cr`} · exp {fmt(s.expires_at)}
                                    </p>
                                </div>
                                <span className="text-[10px] font-bold bg-gray-100 text-gray-500 px-1.5 py-0.5 rounded-full capitalize">
                                    {s.status}
                                </span>
                            </div>
                        ))}
                    </div>
                )}
            </div>

            {/* ── Upcoming Bookings ── */}
            <div className="bg-white rounded-2xl border border-gray-100 overflow-hidden mb-5">
                <div className="flex items-center justify-between px-5 py-3 border-b border-gray-50">
                    <h2 className="text-sm font-bold text-gray-700 uppercase tracking-wide">Upcoming Bookings</h2>
                    <span className="text-xs text-gray-400">{upcomingBookings.length}</span>
                </div>
                {upcomingBookings.length === 0 ? (
                    <p className="px-5 py-5 text-sm text-gray-400">No upcoming bookings.</p>
                ) : (
                    <ul className="divide-y divide-gray-50">
                        {upcomingBookings.map((b) => (
                            <li key={b.id} className="flex items-center gap-3 px-4 py-3">
                                <div className="shrink-0 text-center w-10">
                                    <p className="text-[10px] font-bold text-gray-400 uppercase leading-none">
                                        {b.gym_class ? new Date(b.gym_class.start_time).toLocaleDateString('en-US', { month: 'short' }) : ''}
                                    </p>
                                    <p className="text-lg font-black text-gray-900 leading-none">
                                        {b.gym_class ? new Date(b.gym_class.start_time).getDate() : '—'}
                                    </p>
                                </div>
                                <div className="flex-1 min-w-0">
                                    <div className="flex items-center gap-2 flex-wrap">
                                        <p className="text-sm font-semibold text-gray-900 truncate">{b.gym_class?.name ?? '—'}</p>
                                        <BookingStatusBadge status={b.status} />
                                    </div>
                                    <p className="text-xs text-gray-400">
                                        {b.gym_class ? fmtTime(b.gym_class.start_time) : ''}
                                        {b.gym_class?.coach && ` · ${b.gym_class.coach}`}
                                    </p>
                                </div>
                                <button
                                    onClick={() => cancelBookingForMember(b.id)}
                                    className="text-xs text-red-400 hover:text-red-600 font-medium whitespace-nowrap shrink-0"
                                >
                                    Cancel
                                </button>
                            </li>
                        ))}
                    </ul>
                )}
            </div>

            {/* ── Booking History ── */}
            <div className="bg-white rounded-2xl border border-gray-100 overflow-hidden mb-5">
                <div className="px-5 py-3 border-b border-gray-50">
                    <h2 className="text-sm font-bold text-gray-700 uppercase tracking-wide mb-3">Booking History</h2>
                    <div className="flex gap-1.5 flex-wrap">
                        {BOOKING_TABS.map((tab) => {
                            const count = tab.key === 'all'
                                ? recentBookings.length
                                : recentBookings.filter((b) => b.status === tab.key).length;
                            return (
                                <button
                                    key={tab.key}
                                    type="button"
                                    onClick={() => setHistTab(tab.key)}
                                    className={[
                                        'px-2.5 py-1 rounded-full text-xs font-semibold transition-colors',
                                        histTab === tab.key
                                            ? 'bg-gray-900 text-white'
                                            : 'bg-gray-100 text-gray-500 hover:bg-gray-200',
                                    ].join(' ')}
                                >
                                    {tab.label} {count > 0 && <span className="opacity-70">({count})</span>}
                                </button>
                            );
                        })}
                    </div>
                </div>
                {filteredHistory.length === 0 ? (
                    <p className="px-5 py-5 text-sm text-gray-400">No bookings in this category.</p>
                ) : (
                    <ul className="divide-y divide-gray-50">
                        {filteredHistory.map((b) => (
                            <li key={b.id} className="flex items-center gap-3 px-4 py-3">
                                <div className="shrink-0 text-center w-10">
                                    <p className="text-[10px] font-bold text-gray-400 uppercase leading-none">
                                        {b.gym_class ? new Date(b.gym_class.start_time).toLocaleDateString('en-US', { month: 'short' }) : ''}
                                    </p>
                                    <p className="text-lg font-black text-gray-900 leading-none">
                                        {b.gym_class ? new Date(b.gym_class.start_time).getDate() : '—'}
                                    </p>
                                </div>
                                <div className="flex-1 min-w-0">
                                    <div className="flex items-center gap-2 flex-wrap">
                                        <p className="text-sm font-semibold text-gray-900 truncate">{b.gym_class?.name ?? '—'}</p>
                                        <BookingStatusBadge status={b.status} />
                                    </div>
                                    <p className="text-xs text-gray-400">
                                        {b.gym_class ? fmtDT(b.gym_class.start_time) : ''}
                                    </p>
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
            </div>

            {/* ── Credit History ── */}
            <div className="bg-white rounded-2xl border border-gray-100 overflow-hidden mb-5">
                <div className="flex items-center justify-between px-5 py-3 border-b border-gray-50">
                    <h2 className="text-sm font-bold text-gray-700 uppercase tracking-wide">Credit History</h2>
                    <span className="text-xs text-gray-400">{creditHistory.length} transactions</span>
                </div>
                {creditHistory.length === 0 ? (
                    <p className="px-5 py-5 text-sm text-gray-400">No credit transactions yet.</p>
                ) : (
                    <div className="px-4">
                        {creditHistory.map((tx) => <TxRow key={tx.id} tx={tx} />)}
                    </div>
                )}
            </div>

            {/* ── Modals ── */}
            {modal === 'package' && (
                <AssignPackageModal member={member} packages={packages} onClose={() => setModal(null)} />
            )}
            {modal === 'credits' && (
                <AdjustCreditsModal member={member} onClose={() => setModal(null)} />
            )}
            {modal === 'book' && (
                <BookForMemberModal member={member} upcomingClasses={upcomingClasses} onClose={() => setModal(null)} />
            )}
        </AdminLayout>
    );
}
