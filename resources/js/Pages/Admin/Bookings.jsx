import { router } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { Link } from '@inertiajs/react';
import { parseLocalDT } from '@/utils/date';

function formatDT(dt) {
    return parseLocalDT(dt).toLocaleString('en-US', {
        month: 'short', day: 'numeric',
        hour: '2-digit', minute: '2-digit',
    });
}

export default function Bookings({ bookings }) {
    function cancelBooking(b) {
        if (!confirm(`Cancel booking for ${b.user?.name}? Their credit will be refunded.`)) return;
        router.delete(route('admin.bookings.destroy', b.id));
    }

    const { data, current_page, last_page } = bookings;

    return (
        <AdminLayout title="Bookings">
            <div className="bg-white rounded-2xl border border-gray-100 overflow-hidden mb-4">
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
                        {data.map((b) => (
                            <tr key={b.id} className="hover:bg-gray-50 transition-colors">
                                <td className="px-4 py-3 font-medium text-gray-900">{b.user?.name}</td>
                                <td className="px-4 py-3 text-gray-500 text-xs">{b.user?.email}</td>
                                <td className="px-4 py-3 text-gray-700">{b.gym_class?.name}</td>
                                <td className="px-4 py-3 text-gray-500">{b.gym_class ? formatDT(b.gym_class.start_time) : '—'}</td>
                                <td className="px-4 py-3 text-gray-400 text-xs">{formatDT(b.created_at)}</td>
                                <td className="px-4 py-3">
                                    <button onClick={() => cancelBooking(b)}
                                        className="text-xs text-red-400 hover:text-red-600 font-medium">
                                        Cancel
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
