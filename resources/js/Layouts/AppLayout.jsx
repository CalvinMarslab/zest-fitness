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

const ActivityIcon = ({ active }) => (
    <svg width="22" height="22" viewBox="0 0 24 24" fill="none">
        <path d="M3 12h3l3-7 4 14 3-8 2 4 3-3h3" stroke={active ? '#FFF34D' : '#9AA5AE'} strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round"/>
    </svg>
);

const RecordIcon = () => (
    <svg width="28" height="28" viewBox="0 0 28 28" fill="none">
        <circle cx="14" cy="14" r="10" fill="#FFF34D"/>
        <circle cx="14" cy="14" r="5" fill="#333E48"/>
    </svg>
);

const TrophyIcon = ({ active }) => (
    <svg width="22" height="22" viewBox="0 0 24 24" fill="none">
        <path d="M8 21h8M12 17v4" stroke={active ? '#FFF34D' : '#9AA5AE'} strokeWidth="1.8" strokeLinecap="round"/>
        <path d="M5 3H3a1 1 0 00-1 1v3c0 2.8 1.8 5.1 4.3 5.8C7.2 14.5 9.4 16 12 16s4.8-1.5 5.7-3.2C20.2 12.1 22 9.8 22 7V4a1 1 0 00-1-1h-2M5 3h14M5 3v6a7 7 0 007 7 7 7 0 007-7V3" stroke={active ? '#FFF34D' : '#9AA5AE'} strokeWidth="1.8" strokeLinecap="round"/>
    </svg>
);

const ProfileIcon = ({ active }) => (
    <svg width="22" height="22" viewBox="0 0 24 24" fill="none">
        <circle cx="12" cy="8" r="4" stroke={active ? '#FFF34D' : '#9AA5AE'} strokeWidth="1.8"/>
        <path d="M4 20c0-3.3 3.6-6 8-6s8 2.7 8 6" stroke={active ? '#FFF34D' : '#9AA5AE'} strokeWidth="1.8" strokeLinecap="round"/>
    </svg>
);

// ─── Nav items ────────────────────────────────────────────────────────────────

const NAV_ITEMS = [
    { label: 'Schedule', routeName: 'schedule',   Icon: ScheduleIcon },
    { label: 'Activity', routeName: 'activities', Icon: ActivityIcon  },
    { label: 'Results',  routeName: 'results',    Icon: TrophyIcon    },
    { label: 'Profile',  routeName: 'profile.edit', Icon: ProfileIcon },
];

// ─── Layout ───────────────────────────────────────────────────────────────────

export default function AppLayout({ active, title, subtitle, children }) {
    const { auth } = usePage().props;
    const credits   = auth?.user?.credits ?? 0;

    return (
        <div className="min-h-screen bg-[#CFE0EB]">

            {/* ── Top bar ── */}
            <header className="sticky top-0 z-20 bg-[#CFE0EB]/95 backdrop-blur border-b border-[#DDD5C0]">
                <div className="max-w-lg mx-auto px-5 h-14 flex items-center justify-between">
                    <Link href={route('schedule')}>
                        <img src="/images/logo.svg" alt="Zest Athletic" className="h-8" />
                    </Link>

                    <div className="flex items-center gap-3">
                        {/* Credit badge — taps to packages/top-up */}
                        <Link href={route('packages')} className={[
                            'flex items-center gap-1.5 text-xs font-bold px-3 py-1.5 rounded-full border transition-opacity active:opacity-70',
                            credits > 0
                                ? 'bg-[#FFF34D] text-[#333E48] border-[#FFF34D]'
                                : 'bg-red-500/10 text-red-500 border-red-400/40',
                        ].join(' ')}>
                            <span>🎟</span>
                            <span>{credits} cr</span>
                        </Link>

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
                    {/* Left 2 items */}
                    {NAV_ITEMS.slice(0, 2).map(({ label, routeName, Icon }) => {
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

                    {/* Center Record button */}
                    <Link href={route('training')}
                        className="flex flex-col items-center gap-1 py-1 px-2 -mt-4">
                        <div className="w-14 h-14 rounded-full bg-[#FFF34D] flex items-center justify-center shadow-[0_0_20px_rgba(255,243,77,0.4)]">
                            <div className="w-6 h-6 rounded-full bg-[#333E48]" />
                        </div>
                        <span className={`text-[10px] font-semibold ${active === 'Training' ? 'text-[#333E48]' : 'text-[#9AA5AE]'}`}>
                            Record
                        </span>
                    </Link>

                    {/* Right 2 items */}
                    {NAV_ITEMS.slice(2).map(({ label, routeName, Icon }) => {
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
