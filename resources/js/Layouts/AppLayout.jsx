import { Link, usePage } from '@inertiajs/react';

// ─── Icons ────────────────────────────────────────────────────────────────────

const ScheduleIcon = ({ active }) => (
    <svg width="22" height="22" viewBox="0 0 24 24" fill="none">
        <rect x="3" y="4" width="18" height="18" rx="2.5" stroke={active ? '#FFF34D' : '#9AA5AE'} strokeWidth="1.8"/>
        <path d="M3 9h18" stroke={active ? '#FFF34D' : '#9AA5AE'} strokeWidth="1.8"/>
        <path d="M8 2v4M16 2v4" stroke={active ? '#FFF34D' : '#9AA5AE'} strokeWidth="1.8" strokeLinecap="round"/>
        <circle cx="8" cy="14" r="1" fill={active ? '#FFF34D' : '#9AA5AE'}/>
        <circle cx="12" cy="14" r="1" fill={active ? '#FFF34D' : '#9AA5AE'}/>
        <circle cx="16" cy="14" r="1" fill={active ? '#FFF34D' : '#9AA5AE'}/>
        <circle cx="8" cy="18" r="1" fill={active ? '#FFF34D' : '#9AA5AE'}/>
        <circle cx="12" cy="18" r="1" fill={active ? '#FFF34D' : '#9AA5AE'}/>
    </svg>
);

// ─── Nav items ────────────────────────────────────────────────────────────────

const BookingsIcon = ({ active }) => (
    <svg width="22" height="22" viewBox="0 0 24 24" fill="none">
        <rect x="3" y="4" width="18" height="18" rx="2.5" stroke={active ? '#FFF34D' : '#9AA5AE'} strokeWidth="1.8"/>
        <path d="M3 9h18" stroke={active ? '#FFF34D' : '#9AA5AE'} strokeWidth="1.8"/>
        <path d="M8 2v4M16 2v4" stroke={active ? '#FFF34D' : '#9AA5AE'} strokeWidth="1.8" strokeLinecap="round"/>
        <path d="M8 14l2 2 4-4" stroke={active ? '#FFF34D' : '#9AA5AE'} strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round"/>
    </svg>
);

const AppointmentsIcon = ({ active }) => (
    <svg width="22" height="22" viewBox="0 0 24 24" fill="none">
        <circle cx="12" cy="12" r="9" stroke={active ? '#FFF34D' : '#9AA5AE'} strokeWidth="1.8"/>
        <path d="M12 7v5l3 2" stroke={active ? '#FFF34D' : '#9AA5AE'} strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round"/>
    </svg>
);

const ProfileIcon = ({ active }) => (
    <svg width="22" height="22" viewBox="0 0 24 24" fill="none">
        <circle cx="12" cy="8" r="4" stroke={active ? '#FFF34D' : '#9AA5AE'} strokeWidth="1.8"/>
        <path d="M4 20c0-3.3 3.6-6 8-6s8 2.7 8 6" stroke={active ? '#FFF34D' : '#9AA5AE'} strokeWidth="1.8" strokeLinecap="round"/>
    </svg>
);

const NAV_ITEMS = [
    { label: 'Schedule', routeName: 'schedule',    Icon: ScheduleIcon  },
    { label: 'Bookings', routeName: 'my-bookings', Icon: BookingsIcon  },
    { label: 'Appointments', routeName: 'appointments.index', Icon: AppointmentsIcon },
    { label: 'Profile', routeName: 'profile.edit', Icon: ProfileIcon },
];

// ─── Layout ───────────────────────────────────────────────────────────────────

export default function AppLayout({ active, title, subtitle, children }) {
    const { auth } = usePage().props;
    const sub     = auth?.active_subscription;
    const credits = auth?.user?.credits ?? 0;

    // Derive badge label from subscription
    let badgeLabel, badgeStyle;
    if (sub?.is_unlimited) {
        badgeLabel = `${sub.package_name ?? 'Unlimited'} ∞`;
        badgeStyle = 'bg-purple-400/20 text-purple-700 border-purple-300/40';
    } else if (sub) {
        const cr = sub.credits_remaining ?? credits;
        badgeLabel = `${cr} cr`;
        badgeStyle = cr > 0
            ? (sub.expires_soon ? 'bg-amber-400/20 text-amber-700 border-amber-400/40' : 'bg-[#FFF34D] text-[#333E48] border-[#FFF34D]')
            : 'bg-red-500/10 text-red-500 border-red-400/40';
    } else {
        badgeLabel = `${credits} cr`;
        badgeStyle = credits > 0
            ? 'bg-[#FFF34D] text-[#333E48] border-[#FFF34D]'
            : 'bg-red-500/10 text-red-500 border-red-400/40';
    }

    const expiryLabel = sub && !sub.is_unlimited
        ? `${sub.package_name ?? 'Package'} · exp ${sub.expires_at}`
        : null;

    return (
        <div className="min-h-screen bg-[#CFE0EB]">

            {/* ── Top bar ── */}
            <header className="sticky top-0 z-20 bg-[#CFE0EB]/95 backdrop-blur border-b border-[#DDD5C0]">
                <div className="max-w-lg mx-auto px-5 h-14 flex items-center justify-between">
                    <Link href={route('schedule')}>
                        <img src="/images/logo.svg" alt="Zest Athletic" className="h-8" />
                    </Link>

                    <div className="flex items-center gap-3">
                        <div className={[
                            'flex items-center gap-1.5 text-xs font-bold px-3 py-1.5 rounded-full border',
                            badgeStyle,
                        ].join(' ')} title={expiryLabel ?? undefined}>
                            <span>🎟</span>
                            <span>{badgeLabel}</span>
                            {sub?.expires_soon && !sub?.is_unlimited && (
                                <span className="text-[9px] font-black uppercase tracking-wide opacity-70">exp soon</span>
                            )}
                        </div>

                    </div>
                </div>
            </header>

            {/* ── Page content ── */}
            <main className="max-w-lg mx-auto px-5 pt-6 pb-28">
                {children}
            </main>

            {/* ── Bottom nav ── */}
            <nav className="fixed bottom-0 inset-x-0 z-20 bg-white/95 backdrop-blur border-t border-[#DDD5C0]">
                <div className="max-w-lg mx-auto px-2 h-[72px] flex items-center justify-around">
                    {NAV_ITEMS.map(({ label, routeName, Icon }) => {
                        const isActive = active === label;
                        return (
                            <Link key={label} href={route(routeName)}
                                className="flex flex-col items-center gap-1 py-1 px-3 min-w-[56px]">
                                <Icon active={isActive} />
                                <span className={`text-[10px] font-semibold ${isActive ? 'text-[#333E48]' : 'text-[#9AA5AE]'}`}>
                                    {label}
                                </span>
                            </Link>
                        );
                    })}

                </div>
            </nav>
        </div>
    );
}
