import { Link } from '@inertiajs/react';
import CoachLayout from '@/Layouts/CoachLayout';

function formatTime(isoStr) {
    if (!isoStr) return '';
    const d = new Date(isoStr);
    return d.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });
}

function formatDate(isoStr) {
    if (!isoStr) return '';
    const d = new Date(isoStr);
    return d.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric' });
}

function ClassCard({ gymClass }) {
    const now   = new Date();
    const start = new Date(gymClass.start_time);
    const isToday = start.toDateString() === now.toDateString();

    return (
        <div className="bg-white rounded-2xl border border-gray-200 p-4 flex items-center justify-between gap-4">
            <div>
                <div className="flex items-center gap-2 mb-1">
                    {isToday && (
                        <span className="text-[10px] font-bold text-[#333E48] bg-[#FFF34D] px-2 py-0.5 rounded-full">TODAY</span>
                    )}
                    <span className="text-xs text-gray-500">{formatDate(gymClass.start_time)} · {formatTime(gymClass.start_time)}</span>
                </div>
                <p className="font-bold text-[#333E48]">{gymClass.name}</p>
                {gymClass.location && (
                    <p className="text-xs text-gray-500 mt-0.5">{gymClass.location}</p>
                )}
            </div>
            <div className="flex items-center gap-4">
                <div className="text-right">
                    <p className="text-2xl font-black text-[#333E48]">{gymClass.bookings_count}</p>
                    <p className="text-xs text-gray-500">/ {gymClass.capacity}</p>
                </div>
                <Link
                    href={route('coach.classes.show', gymClass.id)}
                    className="px-4 py-2 rounded-xl bg-[#333E48] text-white text-sm font-semibold hover:bg-[#444] transition-colors"
                >
                    View
                </Link>
            </div>
        </div>
    );
}

export default function Dashboard({ classes }) {
    const today    = classes.filter(c => new Date(c.start_time).toDateString() === new Date().toDateString());
    const upcoming = classes.filter(c => new Date(c.start_time).toDateString() !== new Date().toDateString());

    return (
        <CoachLayout title="Coach Dashboard">
            {today.length > 0 && (
                <section className="mb-8">
                    <h2 className="text-sm font-bold text-gray-500 uppercase tracking-wider mb-3">Today</h2>
                    <div className="flex flex-col gap-3">
                        {today.map(c => <ClassCard key={c.id} gymClass={c} />)}
                    </div>
                </section>
            )}

            {upcoming.length > 0 && (
                <section>
                    <h2 className="text-sm font-bold text-gray-500 uppercase tracking-wider mb-3">This Week</h2>
                    <div className="flex flex-col gap-3">
                        {upcoming.map(c => <ClassCard key={c.id} gymClass={c} />)}
                    </div>
                </section>
            )}

            {classes.length === 0 && (
                <div className="text-center py-20">
                    <p className="text-4xl mb-3">📋</p>
                    <p className="font-bold text-gray-600">No classes assigned this week</p>
                    <p className="text-sm text-gray-400 mt-1">Contact an admin to be assigned to classes.</p>
                </div>
            )}
        </CoachLayout>
    );
}
