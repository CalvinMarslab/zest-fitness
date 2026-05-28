import AdminLayout from '@/Layouts/AdminLayout';
import { Link } from '@inertiajs/react';
import { parseLocalDT } from '@/utils/date';

function StatCard({ label, value, color = 'orange' }) {
    const colors = {
        orange: 'bg-orange-50 text-orange-600 border-orange-100',
        blue:   'bg-blue-50   text-blue-600   border-blue-100',
        green:  'bg-green-50  text-green-600  border-green-100',
        purple: 'bg-purple-50 text-purple-600 border-purple-100',
    };
    return (
        <div className={`rounded-2xl border p-5 ${colors[color]}`}>
            <p className="text-3xl font-bold">{value}</p>
            <p className="text-sm mt-1 font-medium opacity-80">{label}</p>
        </div>
    );
}

function formatTime(dt) {
    return parseLocalDT(dt).toLocaleString('en-US', {
        month: 'short', day: 'numeric',
        hour: '2-digit', minute: '2-digit',
    });
}

export default function Dashboard({ stats }) {
    return (
        <AdminLayout title="Dashboard">
            {/* Stat cards */}
            <div className="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
                <StatCard label="Total Members"    value={stats.total_users}    color="orange" />
                <StatCard label="Total Classes"    value={stats.total_classes}  color="blue"   />
                <StatCard label="Bookings Today"   value={stats.bookings_today} color="green"  />
                <StatCard label="Results Today"    value={stats.results_today}  color="purple" />
            </div>

            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                {/* Upcoming classes */}
                <div className="bg-white rounded-2xl border border-gray-100 p-5">
                    <div className="flex items-center justify-between mb-4">
                        <h2 className="font-semibold text-gray-900">Upcoming Classes</h2>
                        <Link href="/admin/classes" className="text-xs text-orange-500 hover:underline">View all</Link>
                    </div>
                    {stats.upcoming_classes.length === 0 ? (
                        <p className="text-sm text-gray-400">No upcoming classes.</p>
                    ) : (
                        <div className="flex flex-col gap-3">
                            {stats.upcoming_classes.map((c) => (
                                <div key={c.id} className="flex items-center justify-between">
                                    <div>
                                        <p className="text-sm font-medium text-gray-900">{c.name}</p>
                                        <p className="text-xs text-gray-400">{c.coach} · {formatTime(c.start_time)}</p>
                                    </div>
                                    <span className="text-xs bg-gray-100 text-gray-600 px-2 py-1 rounded-full">
                                        {c.bookings_count}/{c.capacity}
                                    </span>
                                </div>
                            ))}
                        </div>
                    )}
                </div>

                {/* Recent bookings */}
                <div className="bg-white rounded-2xl border border-gray-100 p-5">
                    <div className="flex items-center justify-between mb-4">
                        <h2 className="font-semibold text-gray-900">Recent Bookings</h2>
                        <Link href="/admin/bookings" className="text-xs text-orange-500 hover:underline">View all</Link>
                    </div>
                    {stats.recent_bookings.length === 0 ? (
                        <p className="text-sm text-gray-400">No bookings yet.</p>
                    ) : (
                        <div className="flex flex-col gap-3">
                            {stats.recent_bookings.map((b) => (
                                <div key={b.id} className="flex items-center justify-between">
                                    <div>
                                        <p className="text-sm font-medium text-gray-900">{b.user?.name}</p>
                                        <p className="text-xs text-gray-400">{b.gym_class?.name}</p>
                                    </div>
                                    <p className="text-xs text-gray-400">{formatTime(b.created_at)}</p>
                                </div>
                            ))}
                        </div>
                    )}
                </div>
            </div>
        </AdminLayout>
    );
}
