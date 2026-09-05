import { useState } from 'react';
import { useForm, router } from '@inertiajs/react';
import CoachLayout from '@/Layouts/CoachLayout';

function formatDate(isoStr) {
    if (!isoStr) return '';
    return new Date(isoStr).toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric' });
}
function formatTime(isoStr) {
    if (!isoStr) return '';
    return new Date(isoStr).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });
}

function AttendanceBadge({ status }) {
    const map = {
        booked:     { label: 'Booked',     cls: 'bg-blue-100 text-blue-700' },
        checked_in: { label: 'Checked In', cls: 'bg-green-100 text-green-700' },
        no_show:    { label: 'No Show',    cls: 'bg-red-100 text-red-500' },
    };
    const { label, cls } = map[status] ?? { label: status, cls: 'bg-gray-100 text-gray-600' };
    return (
        <span className={`text-[10px] font-bold px-2 py-0.5 rounded-full ${cls}`}>{label}</span>
    );
}

function AttendeeRow({ attendee, gymClassId }) {
    const [loading, setLoading] = useState(false);

    function markStatus(newStatus) {
        setLoading(true);
        router.post(route('coach.classes.attendance', gymClassId), {
            booking_id: attendee.booking_id,
            status:     newStatus,
        }, {
            preserveScroll: true,
            onFinish: () => setLoading(false),
        });
    }

    return (
        <div className="flex items-center justify-between gap-3 py-3 border-b border-gray-100 last:border-0">
            <div>
                <p className="font-semibold text-[#333E48] text-sm">{attendee.name}</p>
                <p className="text-xs text-gray-500">{attendee.email}</p>
            </div>
            <div className="flex items-center gap-2">
                <AttendanceBadge status={attendee.booking_status} />
                {attendee.booking_status !== 'checked_in' && (
                    <button
                        onClick={() => markStatus('checked_in')}
                        disabled={loading}
                        className="text-xs px-3 py-1.5 rounded-lg bg-green-500 text-white font-semibold hover:bg-green-600 transition-colors disabled:opacity-50"
                    >
                        Check In
                    </button>
                )}
                {attendee.booking_status !== 'no_show' && (
                    <button
                        onClick={() => markStatus('no_show')}
                        disabled={loading}
                        className="text-xs px-3 py-1.5 rounded-lg bg-red-100 text-red-500 font-semibold hover:bg-red-200 transition-colors disabled:opacity-50"
                    >
                        No Show
                    </button>
                )}
            </div>
        </div>
    );
}

export default function ClassDetail({ gymClass, attendees, waitlist }) {
    const checkedIn  = attendees.filter(a => a.booking_status === 'checked_in').length;
    const totalBooked = attendees.length;

    return (
        <CoachLayout title={gymClass.name}>
            {/* Class meta */}
            <div className="bg-white rounded-2xl border border-gray-200 p-5 mb-6">
                <div className="grid grid-cols-2 sm:grid-cols-4 gap-4">
                    {[
                        ['Date',     formatDate(gymClass.start_time)],
                        ['Time',     formatTime(gymClass.start_time)],
                        ['Location', gymClass.location ?? 'N/A'],
                        ['Capacity', `${totalBooked} / ${gymClass.capacity}`],
                    ].map(([label, value]) => (
                        <div key={label}>
                            <p className="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-0.5">{label}</p>
                            <p className="font-semibold text-[#333E48] text-sm">{value}</p>
                        </div>
                    ))}
                </div>

                <div className="mt-4 flex gap-3">
                    <div className="flex-1 bg-green-50 rounded-xl p-3 text-center">
                        <p className="text-2xl font-black text-green-600">{checkedIn}</p>
                        <p className="text-xs text-green-700 font-medium">Checked In</p>
                    </div>
                    <div className="flex-1 bg-blue-50 rounded-xl p-3 text-center">
                        <p className="text-2xl font-black text-blue-600">{totalBooked - checkedIn}</p>
                        <p className="text-xs text-blue-700 font-medium">Not Yet In</p>
                    </div>
                    <div className="flex-1 bg-amber-50 rounded-xl p-3 text-center">
                        <p className="text-2xl font-black text-amber-600">{waitlist.length}</p>
                        <p className="text-xs text-amber-700 font-medium">Waitlisted</p>
                    </div>
                </div>
            </div>

            {/* Attendees */}
            <section className="bg-white rounded-2xl border border-gray-200 p-5 mb-6">
                <h2 className="font-bold text-[#333E48] mb-3">Attendees ({totalBooked})</h2>
                {attendees.length === 0 ? (
                    <p className="text-sm text-gray-400 text-center py-4">No attendees yet.</p>
                ) : (
                    <div>
                        {attendees.map(a => (
                            <AttendeeRow key={a.booking_id} attendee={a} gymClassId={gymClass.id} />
                        ))}
                    </div>
                )}
            </section>

            {/* Waitlist */}
            {waitlist.length > 0 && (
                <section className="bg-white rounded-2xl border border-gray-200 p-5">
                    <h2 className="font-bold text-[#333E48] mb-3">Waitlist ({waitlist.length})</h2>
                    <div>
                        {waitlist.map(w => (
                            <div key={w.booking_id} className="flex items-center justify-between py-3 border-b border-gray-100 last:border-0">
                                <div>
                                    <p className="font-semibold text-[#333E48] text-sm">#{w.queue_position} — {w.name}</p>
                                    <p className="text-xs text-gray-500">{w.email}</p>
                                </div>
                            </div>
                        ))}
                    </div>
                </section>
            )}
        </CoachLayout>
    );
}
