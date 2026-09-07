import AdminLayout from '@/Layouts/AdminLayout';
import { Link } from '@inertiajs/react';
import { parseLocalDT } from '@/utils/date';

function fmtTime(isoStr) {
    return parseLocalDT(isoStr).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });
}

// ─── KPI card ─────────────────────────────────────────────────────────────────

function KpiCard({ label, value, sub, accent = 'orange' }) {
    const accents = {
        orange: 'border-orange-200 bg-orange-50',
        blue:   'border-blue-200 bg-blue-50',
        green:  'border-green-200 bg-green-50',
        amber:  'border-amber-200 bg-amber-50',
    };
    const vals = {
        orange: 'text-orange-700',
        blue:   'text-blue-700',
        green:  'text-green-700',
        amber:  'text-amber-700',
    };
    return (
        <div className={`rounded-2xl border p-4 ${accents[accent]}`}>
            <p className={`text-3xl font-black tabular-nums ${vals[accent]}`}>{value}</p>
            <p className="text-xs font-bold text-gray-600 uppercase tracking-wide mt-1">{label}</p>
            {sub && <p className="text-xs text-gray-400 mt-0.5">{sub}</p>}
        </div>
    );
}

// ─── Capacity bar ─────────────────────────────────────────────────────────────

function CapBar({ confirmed, capacity }) {
    const pct = capacity > 0 ? Math.round((confirmed / capacity) * 100) : 0;
    const full = confirmed >= capacity;
    return (
        <div className="flex items-center gap-2 flex-1 min-w-0">
            <div className="h-1.5 flex-1 rounded-full bg-gray-100 overflow-hidden">
                <div
                    className={`h-full rounded-full transition-all ${full ? 'bg-red-400' : pct >= 70 ? 'bg-amber-400' : 'bg-green-400'}`}
                    style={{ width: `${Math.min(pct, 100)}%` }}
                />
            </div>
            <span className={`text-xs font-bold tabular-nums whitespace-nowrap ${full ? 'text-red-500' : 'text-gray-500'}`}>
                {confirmed}/{capacity}
            </span>
        </div>
    );
}

// ─── Today's class row ────────────────────────────────────────────────────────

function ClassRow({ cls }) {
    const manageHref = cls.template_id
        ? route('admin.classes.slot', { template_id: cls.template_id, id: cls.id })
        : route('admin.bookings.index');

    return (
        <div className={`flex items-center gap-4 py-3.5 border-b border-gray-50 last:border-0 ${cls.is_cancelled ? 'opacity-50' : ''}`}>
            {/* Time */}
            <div className="w-16 shrink-0 text-right">
                <span className="text-sm font-black text-gray-800">{fmtTime(cls.start_time)}</span>
            </div>

            {/* Name + coach */}
            <div className="flex-1 min-w-0">
                <p className="text-sm font-bold text-gray-900 truncate">{cls.name}</p>
                <p className="text-xs text-gray-400">{cls.coach}</p>
            </div>

            {/* Capacity bar */}
            <div className="w-36 shrink-0 hidden sm:flex items-center">
                <CapBar confirmed={cls.confirmed_count} capacity={cls.capacity} />
            </div>

            {/* Check-in count */}
            <div className="w-20 shrink-0 text-center hidden md:block">
                {cls.checkin_count > 0 ? (
                    <span className="text-xs font-bold text-green-600 bg-green-50 px-2 py-0.5 rounded-full">
                        {cls.checkin_count} in
                    </span>
                ) : (
                    <span className="text-xs text-gray-300">—</span>
                )}
            </div>

            {/* Status / action */}
            <div className="shrink-0 flex items-center gap-2">
                {cls.is_cancelled ? (
                    <span className="text-[10px] font-black text-red-500 bg-red-50 border border-red-100 px-2 py-0.5 rounded-full uppercase">
                        Cancelled
                    </span>
                ) : cls.waitlist_count > 0 ? (
                    <span className="text-[10px] font-bold text-amber-600 bg-amber-50 border border-amber-100 px-2 py-0.5 rounded-full">
                        +{cls.waitlist_count} waitlist
                    </span>
                ) : cls.is_full ? (
                    <span className="text-[10px] font-bold text-red-400 bg-red-50 border border-red-100 px-2 py-0.5 rounded-full">
                        Full
                    </span>
                ) : (
                    <span className="text-[10px] font-bold text-green-600 bg-green-50 border border-green-100 px-2 py-0.5 rounded-full">
                        {cls.spots_left} left
                    </span>
                )}
                <Link
                    href={manageHref}
                    className="text-xs font-semibold text-orange-500 hover:text-orange-700 border border-orange-200 hover:border-orange-400 px-2.5 py-1 rounded-lg transition-colors"
                >
                    Manage
                </Link>
            </div>
        </div>
    );
}

// ─── Attention card ───────────────────────────────────────────────────────────

function ExpiryRow({ item }) {
    const daysLeft = Math.ceil((new Date(item.expires_at) - new Date()) / 86400000);
    return (
        <div className="flex items-center justify-between py-2 border-b border-gray-50 last:border-0">
            <div>
                <p className="text-sm font-semibold text-gray-900">{item.user_name}</p>
                <p className="text-xs text-gray-400">{item.package_name}</p>
            </div>
            <div className="text-right">
                <span className={`text-xs font-bold px-2 py-0.5 rounded-full ${
                    daysLeft <= 3 ? 'text-red-600 bg-red-50' : 'text-amber-600 bg-amber-50'
                }`}>
                    {daysLeft <= 0 ? 'Today' : `${daysLeft}d left`}
                </span>
            </div>
        </div>
    );
}

// ─── Page ─────────────────────────────────────────────────────────────────────

export default function Dashboard({ stats, todayClasses, expiringSoon, alerts }) {
    const today = new Date().toLocaleDateString('en-US', {
        weekday: 'long', month: 'long', day: 'numeric',
    });

    const hasAlerts = alerts.suspended_with_bookings > 0 || alerts.expiring_soon > 0;

    return (
        <AdminLayout title="Dashboard">
            {/* Page header */}
            <div className="flex items-center justify-between mb-6">
                <div>
                    <h1 className="text-2xl font-black text-gray-900">Dashboard</h1>
                    <p className="text-sm text-gray-400 mt-0.5">{today}</p>
                </div>
                <div className="flex gap-2">
                    <Link href={route('admin.classes.index')}
                        className="px-3 py-1.5 rounded-xl bg-orange-500 text-white text-xs font-semibold hover:bg-orange-600 transition-colors">
                        + Add Class
                    </Link>
                    <Link href={route('admin.users.index')}
                        className="px-3 py-1.5 rounded-xl bg-gray-100 text-gray-700 text-xs font-semibold hover:bg-gray-200 transition-colors">
                        Members
                    </Link>
                </div>
            </div>

            {/* KPI strip */}
            <div className="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-6">
                <KpiCard
                    label="Today's Classes"
                    value={stats.today_classes_count}
                    sub={`${stats.checkins_today} checked in`}
                    accent="orange"
                />
                <KpiCard
                    label="Bookings Today"
                    value={stats.bookings_today}
                    accent="blue"
                />
                <KpiCard
                    label="Check-ins Today"
                    value={stats.checkins_today}
                    accent="green"
                />
                <KpiCard
                    label="Active Members"
                    value={stats.active_members}
                    sub={`of ${stats.total_members} total`}
                    accent="amber"
                />
            </div>

            {/* Attention required */}
            {hasAlerts && (
                <div className="mb-5 rounded-2xl border border-red-100 bg-red-50 p-4">
                    <p className="text-sm font-bold text-red-700 mb-2">Attention Required</p>
                    <div className="flex flex-col gap-1.5">
                        {alerts.suspended_with_bookings > 0 && (
                            <p className="text-sm text-red-600">
                                ⚠ {alerts.suspended_with_bookings} suspended member{alerts.suspended_with_bookings > 1 ? 's' : ''} with upcoming bookings
                            </p>
                        )}
                        {alerts.expiring_soon > 0 && (
                            <p className="text-sm text-red-600">
                                ⚠ {alerts.expiring_soon} package{alerts.expiring_soon > 1 ? 's' : ''} expiring within 14 days
                            </p>
                        )}
                    </div>
                </div>
            )}

            <div className="grid grid-cols-1 lg:grid-cols-3 gap-5">
                {/* Today's classes — main operational view */}
                <div className="lg:col-span-2 bg-white rounded-2xl border border-gray-100 overflow-hidden">
                    <div className="flex items-center justify-between px-5 py-3 border-b border-gray-50 bg-gray-50">
                        <p className="text-xs font-bold uppercase tracking-widest text-gray-500">Today's Classes</p>
                        <Link href={route('admin.classes.index')} className="text-xs text-orange-500 hover:underline">
                            All classes
                        </Link>
                    </div>
                    <div className="px-5">
                        {todayClasses.length === 0 ? (
                            <p className="py-10 text-center text-sm text-gray-400">No classes scheduled today.</p>
                        ) : (
                            todayClasses.map((cls) => <ClassRow key={cls.id} cls={cls} />)
                        )}
                    </div>
                </div>

                {/* Expiring soon */}
                <div className="bg-white rounded-2xl border border-gray-100 overflow-hidden">
                    <div className="flex items-center justify-between px-5 py-3 border-b border-gray-50 bg-gray-50">
                        <p className="text-xs font-bold uppercase tracking-widest text-gray-500">Expiring Soon</p>
                        <Link href={route('admin.users.index')} className="text-xs text-orange-500 hover:underline">
                            View all
                        </Link>
                    </div>
                    <div className="px-5">
                        {expiringSoon.length === 0 ? (
                            <p className="py-10 text-center text-sm text-gray-400">No packages expiring soon.</p>
                        ) : (
                            expiringSoon.map((item, i) => <ExpiryRow key={i} item={item} />)
                        )}
                    </div>
                </div>
            </div>
        </AdminLayout>
    );
}
