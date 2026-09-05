import { Link, usePage } from '@inertiajs/react';

export default function CoachLayout({ title, children }) {
    const { auth } = usePage().props;

    const navLinks = [
        { label: 'Dashboard', href: route('coach.dashboard') },
        { label: 'My Classes', href: route('coach.classes.index') },
    ];

    return (
        <div className="min-h-screen bg-[#F5F7FA]">
            <header className="bg-[#333E48] text-white shadow-md">
                <div className="max-w-5xl mx-auto px-4 h-14 flex items-center justify-between">
                    <div className="flex items-center gap-6">
                        <Link href={route('coach.dashboard')}>
                            <img src="/images/logo.svg" alt="Zest Athletic" className="h-7 brightness-0 invert" />
                        </Link>
                        <nav className="hidden sm:flex gap-1">
                            {navLinks.map(({ label, href }) => (
                                <Link
                                    key={label}
                                    href={href}
                                    className="px-3 py-1.5 rounded-lg text-sm font-semibold text-white/70 hover:text-white hover:bg-white/10 transition-colors"
                                >
                                    {label}
                                </Link>
                            ))}
                        </nav>
                    </div>
                    <div className="flex items-center gap-3 text-sm text-white/70">
                        <span>{auth?.user?.name}</span>
                        <span className="text-xs px-2 py-0.5 rounded-full bg-[#FFF34D] text-[#333E48] font-bold">Coach</span>
                        <Link
                            href={route('profile.edit')}
                            className="hover:text-white transition-colors"
                        >
                            Profile
                        </Link>
                    </div>
                </div>
            </header>

            <main className="max-w-5xl mx-auto px-4 py-8">
                {title && (
                    <h1 className="text-2xl font-black text-[#333E48] mb-6">{title}</h1>
                )}
                {children}
            </main>
        </div>
    );
}
