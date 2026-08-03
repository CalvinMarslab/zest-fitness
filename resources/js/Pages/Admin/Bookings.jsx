import { router, useForm } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { Link } from '@inertiajs/react';
import { parseLocalDT } from '@/utils/date';

function formatDT(dt) {
    return parseLocalDT(dt).toLocaleString('en-US', {
        month: 'short', day: 'numeric',
        hour: '2-digit', minute: '2-digit',
    });
}

function FilterBar({ filters }) {
    const form = useForm({ search: filters?.search ?? '', date: filters?.date ?? '' });

    function apply(e) {
        e.preventDefault();
        router.get(route('admin.bookings.index'), form.data, { preserveScroll: true, replace: true });
    }

    function clear() {
        router.get(route('admin.bookings.index'), {}, { preserveScroll: true, replace: true });
    }

    const hasFilters = filters?.search || filters?.date;

    return (
        <form onSubmit={apply} className="flex flex-wrap gap-2 mb-4">
            <input
                type="text"
                placeholder="Search member name or email…"
                value={form.data.search}
                onChange={(e) => form.setData('search', e.target.value)}
                className="border rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-orange-300 w-64"
            />
            <input
                type="date"
                value={form.data.date}
                onChange={(e) => form.setData('date', e.target.value)}
                className="border rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-orange-300"
            />
            <button type="submit"
                className="px-4 py-2 rounded-xl bg-orange-500 text-white text-sm font-semibold hover:bg-orange-600">
                Filter
            </button>
            {hasFilters && (
                <button type="button" onClick={clear}
                    className="px-4 py-2 rounded-xl border text-sm text-gray-500 hover:bg-gray-50">
                    Clear
                </button>
            )}
        </form>
    );
}

export default function Bookings({ bookings, filters }) {
    function cancelBooking(b) {
        if (!confirm(`Cancel ${b.user?.name}'s booking for "${b.gym_class?.name}"?\n\n1 credit will be refunded to their account.`)) return;
        router.delete(route('admin.bookings.destroy', b.id));
    }

    const { data, current_page, last_page, total } = bookings;

    return (
        <AdminLayout title="Bookings">
            <FilterBar filters={filters} />

            <div className="bg-white rounded-2xl border border-gray-100 overflow-hidden mb-4">
                <div className="px-5 py-3 border-b border-gray-50 flex items-center justify-between">
                    <p className="text-sm text-gray-500">
                        {total} booking{total !== 1 ? 's' : ''}
                        {filters?.search && ` matching "${filters.search}"`}
                        {filters?.date && ` on ${new Date(filters.date + 'T00:00:00').toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}`}
                    </p>
                </div>
                <div className="overflow-x-auto">
                    <table className="w-full text-sm min-w-[640px]">
                        <thead className="bg-gray-50 border-b border-gray-100">
                            <tr>
                                {['Member', 'Email', 'Class', 'Class Time', 'Booked At', ''].map((h) => (
                                    <th key={h} className="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">{h}</th>
                                ))}
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-50">
                            {data.length === 0 && (
                                <tr>
                                    <td colSpan={6} className="px-4 py-10 text-center text-sm text-gray-400">No bookings found.</td>
                                </tr>
                            )}
                            {data.map((b) => (
                                <tr key={b.id} className="hover:bg-gray-50 transition-colors">
                                    <td className="px-4 py-3 font-medium text-gray-900">{b.user?.name}</td>
                                    <td className="px-4 py-3 text-gray-500 text-xs">{b.user?.email}</td>
                                    <td className="px-4 py-3 text-gray-700">{b.gym_class?.name}</td>
                                    <td className="px-4 py-3 text-gray-500">{b.gym_class ? formatDT(b.gym_class.start_time) : '—'}</td>
                                    <td className="px-4 py-3 text-gray-400 text-xs">{formatDT(b.created_at)}</td>
                                    <td className="px-4 py-3">
                                        <button onClick={() => cancelBooking(b)}
                                            className="text-xs text-red-400 hover:text-red-600 font-medium px-2 py-1 rounded-lg hover:bg-red-50 transition-colors">
                                            Cancel & Refund
                                        </button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>

            {/* Pagination */}
            {last_page > 1 && (
                <div className="flex items-center gap-2 justify-end">
                    {current_page > 1 && (
                        <Link href={`/admin/bookings?page=${current_page - 1}`}
                            className="px-3 py-1.5 rounded-lg border text-sm hover:bg-gray-50">← Prev</Link>
                    )}
                    <span className="text-sm text-gray-500">Page {current_page} of {last_page}</span>
                    {current_page < last_page && (
                        <Link href={`/admin/bookings?page=${current_page + 1}`}
                            className="px-3 py-1.5 rounded-lg border text-sm hover:bg-gray-50">Next →</Link>
                    )}
                </div>
            )}
        </AdminLayout>
    );
}
