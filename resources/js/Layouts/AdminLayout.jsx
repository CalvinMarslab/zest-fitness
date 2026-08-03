import { useState } from 'react';
import { Link, usePage, router } from '@inertiajs/react';

const NAV = [
    { label: 'Dashboard', href: '/admin',           icon: '📊' },
    { label: 'Users',     href: '/admin/users',     icon: '👥' },
    { label: 'Classes',   href: '/admin/classes',   icon: '🏋️' },
    { label: 'Bookings',  href: '/admin/bookings',  icon: '📋' },
    { label: 'Packages',  href: '/admin/packages',  icon: '📦' },
];

function HamburgerIcon() {
    return (
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round">
            <line x1="3" y1="6"  x2="21" y2="6"  />
            <line x1="3" y1="12" x2="21" y2="12" />
            <line x1="3" y1="18" x2="21" y2="18" />
        </svg>
    );
}

function CloseIcon() {
    return (
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round">
            <path d="M18 6L6 18M6 6l12 12" />
        </svg>
    );
}

export default function AdminLayout({ children, title }) {
    const { url, auth } = usePage();
    const userName = auth?.user?.name ?? 'Admin';
    const [open, setOpen] = useState(false);

    const close = () => setOpen(false);

    return (
        <div className="min-h-screen bg-gray-50 flex">

            {/* ── Mobile backdrop ── */}
            {open && (
                <div
                    className="fixed inset-0 z-30 bg-black/50 lg:hidden"
                    onClick={close}
                    aria-hidden="true"
                />
            )}

            {/* ── Sidebar ── */}
            <aside
                className={[
                    // positioning: fixed on mobile, static on desktop
                    'fixed inset-y-0 left-0 z-40',
                    'lg:static lg:z-auto lg:shrink-0',
                    // width
                    'w-64 lg:w-56',
                    // colours / layout
                    'bg-gray-900 text-white flex flex-col',
                    // slide animation (disabled on desktop)
                    'transition-transform duration-300 ease-in-out lg:transition-none',
                    open ? 'translate-x-0' : '-translate-x-full lg:translate-x-0',
                ].join(' ')}
            >
                {/* Branding + mobile close button */}
                <div className="px-5 py-5 border-b border-gray-700 flex items-center justify-between">
                    <div>
                        <p className="text-orange-400 font-bold text-lg leading-tight">Zest Athletic</p>
                        <p className="text-gray-400 text-xs mt-0.5">Admin Panel</p>
                    </div>
                    <button
                        onClick={close}
                        className="lg:hidden text-gray-400 hover:text-white p-1 rounded-lg"
                        aria-label="Close menu"
                    >
                        <CloseIcon />
                    </button>
                </div>

                {/* Nav links */}
                <nav className="flex-1 px-3 py-4 flex flex-col gap-1 overflow-y-auto">
                    {NAV.map(({ label, href, icon }) => {
                        const active = url === href || (href !== '/admin' && url.startsWith(href));
                        return (
                            <Link
                                key={href}
                                href={href}
                                onClick={close}
                                className={[
                                    'flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors',
                                    active
                                        ? 'bg-orange-500 text-white'
                                        : 'text-gray-400 hover:bg-gray-800 hover:text-white',
                                ].join(' ')}
                            >
                                <span className="text-base">{icon}</span>
                                {label}
                            </Link>
                        );
                    })}
                </nav>

                {/* Footer — user + portal switch */}
                <div className="px-3 py-4 border-t border-gray-700 flex flex-col gap-2">
                    <div className="flex items-center gap-2.5 px-3 py-2">
                        <div className="w-7 h-7 rounded-full bg-orange-500 flex items-center justify-center text-xs font-bold text-white shrink-0">
                            {userName[0].toUpperCase()}
                        </div>
                        <span className="text-xs text-gray-400 truncate">{userName}</span>
                    </div>
                    <Link
                        href="/schedule"
                        onClick={close}
                        className="flex items-center gap-2 px-3 py-2 rounded-lg text-xs font-semibold text-gray-400 hover:text-white hover:bg-gray-800 transition-colors"
                    >
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                            <path d="M15 3H5a2 2 0 00-2 2v14a2 2 0 002 2h10"/>
                            <polyline points="17 8 12 3 7 8"/>
                            <line x1="12" y1="3" x2="12" y2="15"/>
                        </svg>
                        Switch to Customer App
                    </Link>
                    <button
                        type="button"
                        onClick={() => router.post(route('logout'))}
                        className="flex items-center gap-2 px-3 py-2 rounded-lg text-xs font-semibold text-red-400 hover:text-red-300 hover:bg-gray-800 transition-colors w-full text-left"
                    >
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                            <path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/>
                            <polyline points="16 17 21 12 16 7"/>
                            <line x1="21" y1="12" x2="9" y2="12"/>
                        </svg>
                        Sign Out
                    </button>
                </div>
            </aside>

            {/* ── Main area ── */}
            <div className="flex-1 flex flex-col min-w-0">

                {/* Top bar */}
                <header className="sticky top-0 z-20 bg-white border-b border-gray-200 px-4 lg:px-8 py-4 flex items-center gap-3">
                    {/* Hamburger — mobile only */}
                    <button
                        onClick={() => setOpen(true)}
                        className="lg:hidden text-gray-500 hover:text-gray-800 p-1 rounded-lg"
                        aria-label="Open menu"
                    >
                        <HamburgerIcon />
                    </button>

                    <h1 className="flex-1 text-lg lg:text-xl font-bold text-gray-900 truncate">{title}</h1>

                    <span className="hidden sm:inline-flex text-xs font-semibold text-orange-500 bg-orange-50 border border-orange-100 px-2.5 py-1 rounded-full whitespace-nowrap">
                        Admin Panel
                    </span>
                </header>

                {/* Page content */}
                <main className="flex-1 p-4 lg:p-8 overflow-x-auto">
                    {children}
                </main>
            </div>
        </div>
    );
}
