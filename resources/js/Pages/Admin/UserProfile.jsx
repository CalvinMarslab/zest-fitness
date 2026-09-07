import { useState } from 'react';
import { useForm, router, Link } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';

// ─── Helpers ─────────────────────────────────────────────────────────────────

function fmt(isoStr) {
    if (!isoStr) return '—';
    return new Date(isoStr).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
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

// ─── Flash ────────────────────────────────────────────────────────────────────

function Flash() {
    const url = new URL(window.location.href);
    // Inertia flash is already handled by AdminLayout; expose via usePage if needed
    return null;
}

// ─── Section wrapper ─────────────────────────────────────────────────────────

function Card({ title, children, action }) {
    return (
        <div className="bg-white rounded-2xl border border-gray-100 overflow-hidden mb-5">
            <div className="flex items-center justify-between px-5 py-3 border-b border-gray-50">
                <h3 className="text-sm font-bold text-gray-700 uppercase tracking-wide">{title}</h3>
                {action}
            </div>
            <div className="p-5">{children}</div>
        </div>
    );
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
                        <label className="text-xs font-semibold text-gray-500 uppercase">Start Date (optional — defaults to today)</label>
                        <input
                            type="date"
                            className="mt-1 w-full border rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-orange-300"
                            value={form.data.started_at}
                            onChange={(e) => form.setData('started_at', e.target.value)}
                        />
                    </div>
                    {form.errors.package_id && (
                        <p className="text-xs text-red-500">{form.errors.package_id}</p>
                    )}
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
                    <p className="text-sm text-gray-500 mb-4">Assign a credit-based package first before adjusting credits.</p>
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
                                        {s.package?.name} — {s.credits_remaining} cr remaining (exp {fmt(s.expires_at)})
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

function BookForMemberModal({ member, upcomingClasses, onClose }) {
    const [search, setSearch] = useState('');
    const [selectedClass, setSelectedClass] = useState(null);
    const form = useForm({ gym_class_id: '' });

    function submit(e) {
        e.preventDefault();
        if (!selectedClass) return;
        form.setData('gym_class_id', selectedClass.id);
        form.post(route('admin.users.bookings.store', member.id), { onSuccess: onClose });
    }

    const filtered = (upcomingClasses ?? []).filter((c) => {
        if (!search.trim()) return true;
        const q = search.toLowerCase();
        return c.name.toLowerCase().includes(q) || c.coach.toLowerCase().includes(q);
    });

    return (
        <div className="fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4" onClick={onClose}>
            <div className="bg-white rounded-2xl p-6 w-full max-w-md shadow-2xl max-h-[85vh] flex flex-col" onClick={(e) => e.stopPropagation()}>
                <h2 className="font-bold text-lg mb-4">Book Class for {member.name}</h2>

                {/* Search */}
                <input
                    type="text"
                    placeholder="Search by class name or coach…"
                    className="w-full border rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-orange-300 mb-3"
                    value={search}
                    onChange={(e) => { setSearch(e.target.value); setSelectedClass(null); }}
                    autoFocus
                />

                {/* Class list */}
                <div className="flex-1 overflow-y-auto flex flex-col gap-1.5 mb-4 min-h-0">
                    {filtered.length === 0 ? (
                        <p className="text-center text-sm text-gray-400 py-8">No upcoming classes found.</p>
                    ) : (
                        filtered.slice(0, 20).map((c) => {
                            const isSelected = selectedClass?.id === c.id;
                            const dt = new Date(c.start_time);
                            const dateStr = dt.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric' });
                            const timeStr = dt.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });
                            const full = c.spots_left <= 0;
                            return (
                                <button
                                    key={c.id}
                                    type="button"
                                    onClick={() => setSelectedClass(isSelected ? null : c)}
                                    className={[
                                        'w-full text-left rounded-xl border px-3 py-2.5 transition-colors',
                                        isSelected
                                            ? 'border-orange-400 bg-orange-50'
                                            : 'border-gray-100 hover:border-orange-200 hover:bg-orange-50/30',
                                    ].join(' ')}
                                >
                                    <div className="flex items-start justify-between gap-2">
                                        <div className="min-w-0">
                                            <p className="text-sm font-semibold text-gray-900 truncate">{c.name}</p>
                                            <p className="text-xs text-gray-500">{c.coach} · {dateStr} {timeStr}</p>
                                        </div>
                                        <div className="shrink-0 text-right">
                                            <span className={`text-[10px] font-bold px-1.5 py-0.5 rounded-full ${
                                                full ? 'text-red-500 bg-red-50' : 'text-green-600 bg-green-50'
                                            }`}>
                                                {full ? 'Full' : `${c.spots_left} left`}
                                            </span>
                                        </div>
                                    </div>
                                </button>
                            );
                        })
                    )}
                </div>

                {form.errors.gym_class_id && <p className="text-xs text-red-500 mb-2">{form.errors.gym_class_id}</p>}

                <div className="flex gap-2">
                    <button type="button" onClick={onClose}
                        className="flex-1 py-2 rounded-xl border text-sm text-gray-600">Cancel</button>
                    <button
                        type="button"
                        disabled={!selectedClass || form.processing}
                        onClick={submit}
                        className="flex-1 py-2 rounded-xl bg-orange-500 text-white text-sm font-semibold disabled:opacity-60">
                        {form.processing ? 'Booking…' : selectedClass ? `Book into ${selectedClass.name}` : 'Select a class'}
                    </button>
                </div>
            </div>
        </div>
    );
}

// ─── Subscription badge ───────────────────────────────────────────────────────

function SubBadge({ status }) {
    if (status === 'active')   return <span className="text-[10px] font-bold bg-green-100 text-green-700 px-1.5 py-0.5 rounded-full">Active</span>;
    if (status === 'expired')  return <span className="text-[10px] font-bold bg-gray-100 text-gray-500 px-1.5 py-0.5 rounded-full">Expired</span>;
    if (status === 'cancelled') return <span className="text-[10px] font-bold bg-red-100 text-red-500 px-1.5 py-0.5 rounded-full">Cancelled</span>;
    return <span className="text-[10px] font-bold bg-gray-100 text-gray-500 px-1.5 py-0.5 rounded-full">{status}</span>;
}

// ─── Booking status badge ─────────────────────────────────────────────────────

function BookingStatusBadge({ status }) {
    const styles = {
        booked:      'bg-yellow-100 text-yellow-700',
        checked_in:  'bg-green-100 text-green-700',
        waitlisted:  'bg-amber-100 text-amber-700',
        late_cancel: 'bg-red-100 text-red-600',
        cancelled:   'bg-gray-100 text-gray-500',
        no_show:     'bg-red-100 text-red-600',
    };
    const labels = {
        booked: 'Booked', checked_in: 'Checked In', waitlisted: 'Waitlisted',
        late_cancel: 'Late Cancel', cancelled: 'Cancelled', no_show: 'No Show',
    };
    return (
        <span className={`text-[10px] font-bold px-1.5 py-0.5 rounded-full ${styles[status] ?? 'bg-gray-100 text-gray-500'}`}>
            {labels[status] ?? status}
        </span>
    );
}

// ─── Credit transaction type label ────────────────────────────────────────────

function TxType({ type, amount }) {
    const labels = {
        package_assigned: 'Package assigned',
        booking_deduction: 'Booking deducted',
        booking_refund: 'Booking refund',
        class_cancel_refund: 'Class cancelled refund',
        admin_adjustment: 'Admin adjustment',
        migration: 'Migration import',
        other: 'Other',
    };
    const positive = amount > 0;
    return (
        <span className={`font-medium ${positive ? 'text-green-700' : 'text-red-600'}`}>
            {labels[type] ?? type}
        </span>
    );
}

// ─── Page ─────────────────────────────────────────────────────────────────────

export default function UserProfile({ member, packages, upcomingClasses, upcomingBookings, recentBookings, creditHistory }) {
    const [modal, setModal] = useState(null); // 'package' | 'credits' | 'book'

    const activeSubs = (member.subscriptions ?? []).filter(
        (s) => s.status === 'active' && new Date(s.expires_at) > new Date()
    );
    const totalCredits = member.credits ?? 0;
    const isSuspended = member.status === 'suspended';

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
            {/* ── Back + heading ── */}
            <div className="mb-5">
                <Link href={route('admin.users.index')} className="text-sm text-orange-500 hover:text-orange-700 font-medium mb-3 inline-block">
                    ← All Members
                </Link>
                <div className="flex items-start justify-between gap-3">
                    <div>
                        <div className="flex items-center gap-2">
                            <h1 className="text-2xl font-black text-gray-900">{member.name}</h1>
                            {isSuspended && (
                                <span className="text-xs font-bold bg-red-100 text-red-600 px-2 py-0.5 rounded-full">Suspended</span>
                            )}
                        </div>
                        <p className="text-sm text-gray-500 mt-0.5">{member.email}</p>
                        {member.phone && <p className="text-xs text-gray-400">{member.phone}</p>}
                    </div>
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

            {/* ── Credit summary ── */}
            <div className="grid grid-cols-3 gap-3 mb-5">
                <div className="bg-orange-50 rounded-2xl p-4 text-center">
                    <p className="text-2xl font-black text-orange-600">{totalCredits}</p>
                    <p className="text-xs text-orange-500 font-semibold uppercase">Total Credits</p>
                </div>
                <div className="bg-gray-50 rounded-2xl p-4 text-center">
                    <p className="text-2xl font-black text-gray-800">{activeSubs.length}</p>
                    <p className="text-xs text-gray-500 font-semibold uppercase">Active Subs</p>
                </div>
                <div className="bg-gray-50 rounded-2xl p-4 text-center">
                    <p className="text-2xl font-black text-gray-800">{upcomingBookings.length}</p>
                    <p className="text-xs text-gray-500 font-semibold uppercase">Upcoming</p>
                </div>
            </div>

            {/* ── Admin actions ── */}
            <div className="flex flex-wrap gap-2 mb-5">
                <button
                    onClick={() => setModal('package')}
                    className="px-4 py-2 rounded-xl bg-orange-500 text-white text-sm font-semibold hover:bg-orange-600 transition-colors"
                >
                    + Assign Package
                </button>
                <button
                    onClick={() => setModal('credits')}
                    className="px-4 py-2 rounded-xl bg-gray-100 text-gray-700 text-sm font-semibold hover:bg-gray-200 transition-colors"
                >
                    Adjust Credits
                </button>
                <button
                    onClick={() => setModal('book')}
                    className="px-4 py-2 rounded-xl bg-gray-100 text-gray-700 text-sm font-semibold hover:bg-gray-200 transition-colors"
                >
                    Book Class
                </button>
            </div>

            {/* ── Active subscriptions ── */}
            <Card title="Subscriptions">
                {(member.subscriptions ?? []).length === 0 ? (
                    <p className="text-sm text-gray-400">No packages assigned yet.</p>
                ) : (
                    <div className="flex flex-col gap-3">
                        {member.subscriptions.map((s) => (
                            <div key={s.id} className="flex items-start justify-between gap-2 py-2 border-b border-gray-50 last:border-0">
                                <div>
                                    <div className="flex items-center gap-2 mb-0.5">
                                        <p className="text-sm font-semibold text-gray-800">{s.package?.name ?? '—'}</p>
                                        <SubBadge status={s.status} />
                                    </div>
                                    {s.is_unlimited ? (
                                        <p className="text-xs text-gray-500">∞ Unlimited · exp {fmt(s.expires_at)}</p>
                                    ) : (
                                        <p className="text-xs text-gray-500">
                                            {s.credits_remaining} / {s.credits_granted} credits · exp {fmt(s.expires_at)}
                                        </p>
                                    )}
                                    {s.assigned_by && (
                                        <p className="text-[10px] text-gray-400">Assigned by {s.assigned_by?.name ?? `#${s.assigned_by}`}</p>
                                    )}
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </Card>

            {/* ── Upcoming bookings ── */}
            <Card title="Upcoming Bookings">
                {upcomingBookings.length === 0 ? (
                    <p className="text-sm text-gray-400">No upcoming bookings.</p>
                ) : (
                    <div className="flex flex-col gap-2">
                        {upcomingBookings.map((b) => (
                            <div key={b.id} className="flex items-center justify-between gap-2 py-2 border-b border-gray-50 last:border-0">
                                <div>
                                    <div className="flex items-center gap-2 mb-0.5">
                                        <p className="text-sm font-semibold text-gray-800">{b.gym_class?.name ?? '—'}</p>
                                        <BookingStatusBadge status={b.status} />
                                    </div>
                                    <p className="text-xs text-gray-500">
                                        {b.gym_class ? `${fmt(b.gym_class.start_time)} ${fmtTime(b.gym_class.start_time)}` : '—'}
                                        {b.gym_class?.coach && ` · ${b.gym_class.coach}`}
                                    </p>
                                </div>
                                <button
                                    onClick={() => cancelBookingForMember(b.id)}
                                    className="text-xs text-red-400 hover:text-red-600 font-medium whitespace-nowrap"
                                >
                                    Cancel
                                </button>
                            </div>
                        ))}
                    </div>
                )}
            </Card>

            {/* ── Recent booking history ── */}
            <Card title="Recent Booking History (30 days)">
                {recentBookings.length === 0 ? (
                    <p className="text-sm text-gray-400">No recent bookings.</p>
                ) : (
                    <div className="flex flex-col gap-2">
                        {recentBookings.map((b) => (
                            <div key={b.id} className="flex items-center justify-between gap-2 py-2 border-b border-gray-50 last:border-0">
                                <div>
                                    <div className="flex items-center gap-2 mb-0.5">
                                        <p className="text-sm font-semibold text-gray-800">{b.gym_class?.name ?? '—'}</p>
                                        <BookingStatusBadge status={b.status} />
                                    </div>
                                    <p className="text-xs text-gray-500">
                                        {b.gym_class ? `${fmt(b.gym_class.start_time)} ${fmtTime(b.gym_class.start_time)}` : '—'}
                                    </p>
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </Card>

            {/* ── Credit history ── */}
            <Card title="Credit History">
                {creditHistory.length === 0 ? (
                    <p className="text-sm text-gray-400">No credit transactions yet.</p>
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm min-w-[500px]">
                            <thead>
                                <tr className="text-left text-xs text-gray-400 uppercase">
                                    <th className="pb-2 pr-3">Date</th>
                                    <th className="pb-2 pr-3">Type</th>
                                    <th className="pb-2 pr-3 text-right">Amount</th>
                                    <th className="pb-2 pr-3 text-right">Balance</th>
                                    <th className="pb-2">Notes</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-50">
                                {creditHistory.map((tx) => (
                                    <tr key={tx.id}>
                                        <td className="py-2 pr-3 text-xs text-gray-400 whitespace-nowrap">{fmtDT(tx.created_at)}</td>
                                        <td className="py-2 pr-3"><TxType type={tx.type} amount={tx.amount} /></td>
                                        <td className={`py-2 pr-3 text-right font-bold ${tx.amount > 0 ? 'text-green-600' : 'text-red-500'}`}>
                                            {tx.amount > 0 ? `+${tx.amount}` : tx.amount}
                                        </td>
                                        <td className="py-2 pr-3 text-right text-gray-600">{tx.balance_after}</td>
                                        <td className="py-2 text-xs text-gray-400">
                                            {tx.reason ?? (tx.actor ? `by ${tx.actor.name}` : '')}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </Card>

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
