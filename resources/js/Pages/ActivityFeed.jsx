import { Fragment } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { getActivityType } from '@/config/activityTypes';
import { formatRelativeDate } from '@/utils/dateFormatters';

function formatDuration(seconds) {
    const h = Math.floor(seconds / 3600);
    const m = Math.floor((seconds % 3600) / 60);
    const s = seconds % 60;
    if (h > 0) return `${h}:${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
    return `${m}:${String(s).padStart(2, '0')}`;
}

function formatDistance(meters) {
    if (!meters) return null;
    return (meters / 1000).toFixed(1) + ' km';
}

function formatPace(meters, seconds) {
    if (!meters || !seconds) return null;
    const minsPerKm = seconds / 60 / (meters / 1000);
    const m = Math.floor(minsPerKm);
    const s = Math.round((minsPerKm - m) * 60);
    return `${m}:${String(s).padStart(2, '0')} /km`;
}

function MapPlaceholder({ seed = 0 }) {
    const r = (n) => ((seed * 9301 + n * 49297) % 233280) / 233280;

    const points = [
        [10,  60 + (r(1) - 0.5) * 30],
        [50,  40 + (r(2) - 0.5) * 40],
        [90,  55 + (r(3) - 0.5) * 35],
        [130, 35 + (r(4) - 0.5) * 40],
        [170, 50 + (r(5) - 0.5) * 35],
        [195, 45 + (r(6) - 0.5) * 30],
    ];

    const d = points.map(([x, y], i) => (i === 0 ? `M ${x} ${y}` : `L ${x} ${y}`)).join(' ');
    const [startX, startY] = points[0];
    const [endX,   endY]   = points[points.length - 1];

    return (
        <svg viewBox="0 0 200 120" xmlns="http://www.w3.org/2000/svg" className="w-full h-full" aria-hidden="true">
            <defs>
                <linearGradient id={`mapBg${seed}`} x1="0" y1="0" x2="1" y2="1">
                    <stop offset="0%" stopColor="#2d3a4a" />
                    <stop offset="100%" stopColor="#1a2535" />
                </linearGradient>
            </defs>
            <rect width="200" height="120" fill={`url(#mapBg${seed})`} rx="4" />
            {/* Subtle road lines */}
            <line x1="0" y1="90" x2="200" y2="90" stroke="#3a4a5a" strokeWidth="1" />
            <line x1="0" y1="60" x2="200" y2="60" stroke="#3a4a5a" strokeWidth="1" />
            <line x1="60" y1="0" x2="60" y2="120" stroke="#3a4a5a" strokeWidth="1" />
            <line x1="130" y1="0" x2="130" y2="120" stroke="#3a4a5a" strokeWidth="1" />
            {/* Route glow */}
            <path d={d} fill="none" stroke="#FFF34D" strokeWidth="6" strokeLinecap="round" strokeLinejoin="round" opacity="0.15" />
            {/* Route line */}
            <path d={d} fill="none" stroke="#FFF34D" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round" />
            <circle cx={startX} cy={startY} r="5" fill="#16a34a" stroke="#fff" strokeWidth="1.5" />
            <circle cx={endX}   cy={endY}   r="5" fill="#dc2626" stroke="#fff" strokeWidth="1.5" />
        </svg>
    );
}

function ActivityCard({ activity }) {
    const cfg      = getActivityType(activity.type);
    const distance = formatDistance(activity.distance);
    const duration = formatDuration(activity.duration);
    const pace     = formatPace(activity.distance, activity.duration);
    const date     = new Date(activity.recorded_at);

    const relativeDate = formatRelativeDate(date)
        ?? date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });

    return (
        <article className="bg-[#FFFFFF] rounded-3xl border border-[#DDD5C0] overflow-hidden">
            <div className="h-36 w-full relative">
                <MapPlaceholder seed={activity.id} />
                <div className="absolute inset-x-0 bottom-0 h-8 bg-gradient-to-t from-[#FFFFFF]/80 to-transparent" />
                <div className="absolute top-3 left-3 inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-bold bg-[#333E48]/80 backdrop-blur-sm text-[#FFF34D] border border-[#FFF34D]/30">
                    <span>{cfg.icon}</span>
                    {cfg.label}
                </div>
                <div className="absolute top-3 right-3 rounded-full bg-[#333E48]/80 backdrop-blur-sm px-2.5 py-1 text-xs font-medium text-[#888]">
                    {relativeDate}
                </div>
            </div>

            <div className="px-4 py-4">
                {activity.class_name && (
                    <p className="text-xs text-[#FFF34D]/70 font-medium mb-3 truncate">
                        From: {activity.class_name}
                    </p>
                )}

                <div className="flex items-center gap-4">
                    {[
                        distance && { label: 'Distance', value: distance },
                        { label: 'Duration', value: duration },
                        pace && { label: 'Avg pace', value: pace },
                    ].filter(Boolean).map((stat, i) => (
                        <Fragment key={stat.label}>
                            {i > 0 && <div className="h-8 w-px bg-[#DDD5C0]" />}
                            <div>
                                <p className="text-xl font-black text-[#333E48] leading-none">{stat.value}</p>
                                <p className="text-xs text-[#555] mt-0.5">{stat.label}</p>
                            </div>
                        </Fragment>
                    ))}
                </div>
            </div>
        </article>
    );
}

function SummaryBar({ activities }) {
    if (activities.length === 0) return null;

    const { totalMeters, totalSec } = activities.reduce(
        (acc, a) => ({
            totalMeters: acc.totalMeters + (a.distance ?? 0),
            totalSec:    acc.totalSec + a.duration,
        }),
        { totalMeters: 0, totalSec: 0 },
    );

    const totalKm = totalMeters / 1000;
    const h = Math.floor(totalSec / 3600);
    const m = Math.floor((totalSec % 3600) / 60);

    return (
        <div className="grid grid-cols-3 gap-3 mb-6">
            {[
                { label: 'Activities',     value: activities.length },
                { label: 'Total distance', value: totalKm.toFixed(1) + ' km' },
                { label: 'Total time',     value: h > 0 ? `${h}h ${m}m` : `${m}m` },
            ].map(({ label, value }) => (
                <div key={label} className="bg-[#FFFFFF] rounded-2xl border border-[#DDD5C0] px-3 py-3 text-center">
                    <p className="text-lg font-black text-[#333E48]">{value}</p>
                    <p className="text-xs text-[#555] mt-0.5">{label}</p>
                </div>
            ))}
        </div>
    );
}

export default function ActivityFeed({ activities }) {
    return (
        <AppLayout active="Activity">
            <div className="mb-6">
                <h1 className="text-2xl font-black text-[#333E48]">Activity Feed</h1>
                <p className="mt-0.5 text-sm text-[#666]">
                    {activities.length === 0
                        ? 'No workouts recorded yet.'
                        : `${activities.length} workout${activities.length === 1 ? '' : 's'} recorded`}
                </p>
            </div>

            <SummaryBar activities={activities} />

            {activities.length === 0 ? (
                <div className="text-center py-20">
                    <p className="text-5xl mb-4">🗺️</p>
                    <p className="font-bold text-[#333E48]">No workouts yet</p>
                    <p className="text-sm mt-1 text-[#666]">Complete a class and record your first activity!</p>
                </div>
            ) : (
                <div className="grid gap-4">
                    {activities.map((activity) => (
                        <ActivityCard key={activity.id} activity={activity} />
                    ))}
                </div>
            )}
        </AppLayout>
    );
}
