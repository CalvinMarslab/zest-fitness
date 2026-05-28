import { router, usePage } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';

function SuccessFlash() {
    const { flash } = usePage().props;
    if (!flash?.success) return null;
    return (
        <div className="mb-4 rounded-2xl bg-[#C8FF00]/10 border border-[#C8FF00]/30 px-4 py-3 text-[#C8FF00] text-sm font-medium">
            {flash.success}
        </div>
    );
}

function ActiveBanner({ sub }) {
    if (!sub) return null;
    return (
        <div className="mb-6 bg-[#C8FF00]/5 border border-[#C8FF00]/20 rounded-2xl px-4 py-4">
            <p className="text-sm font-bold text-[#C8FF00]">
                ✅ Active: <span className="font-black">{sub.package_name}</span>
            </p>
            <p className="text-xs text-[#C8FF00]/60 mt-0.5">
                {sub.credits} credits granted · Expires {new Date(sub.expires_at + 'T00:00:00').toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' })}
            </p>
        </div>
    );
}

function PackageCard({ pkg, onSubscribe }) {
    const hasBadge = !!pkg.badge;

    return (
        <div className={`relative bg-[#1A1A1A] rounded-2xl border p-5 flex flex-col ${
            hasBadge ? 'border-[#C8FF00]/40' : 'border-[#2A2A2A]'
        }`}>
            {hasBadge && (
                <div className="absolute -top-3 left-1/2 -translate-x-1/2">
                    <span className="bg-[#C8FF00] text-[#0D0D0D] text-xs font-black px-4 py-1 rounded-full whitespace-nowrap">
                        {pkg.badge}
                    </span>
                </div>
            )}

            <div className="mb-4 mt-1">
                <h3 className="text-lg font-black text-white">{pkg.name}</h3>
                {pkg.description && (
                    <p className="text-sm text-[#666] mt-1">{pkg.description}</p>
                )}
            </div>

            <div className="mb-4">
                <div className="flex items-baseline gap-1">
                    <span className="text-3xl font-black text-white">
                        RM {parseFloat(pkg.price).toFixed(2)}
                    </span>
                    <span className="text-sm text-[#555]">/ {pkg.period_label}</span>
                </div>
            </div>

            <div className="flex items-center gap-2 mb-4">
                <div className="flex items-center gap-2 bg-[#C8FF00]/5 border border-[#C8FF00]/20 rounded-xl px-3 py-2 w-full">
                    <span className="text-xl">🎟</span>
                    <div>
                        <p className="text-sm font-black text-[#C8FF00]">{pkg.credits} Credits</p>
                        <p className="text-xs text-[#C8FF00]/50">Valid for {pkg.period_label}</p>
                    </div>
                </div>
            </div>

            {pkg.price > 0 && (
                <p className="text-xs text-[#555] mb-4">
                    ≈ RM {(pkg.price / pkg.credits).toFixed(2)} per credit
                </p>
            )}

            <div className="flex-1" />

            <button
                onClick={() => onSubscribe(pkg)}
                className={`w-full py-3 rounded-2xl font-black text-sm transition-all active:scale-[0.98] ${
                    hasBadge
                        ? 'bg-[#C8FF00] text-[#0D0D0D] hover:bg-[#d4ff33]'
                        : 'bg-[#2A2A2A] text-white hover:bg-[#333]'
                }`}
            >
                Get {pkg.name}
            </button>
        </div>
    );
}

export default function Packages({ packages, activeSubscription }) {
    function handleSubscribe(pkg) {
        if (!confirm(`Activate "${pkg.name}" — ${pkg.credits} credits for ${pkg.period_label}?`)) return;
        router.post(route('packages.subscribe', pkg.id));
    }

    return (
        <AppLayout active="Packages">
            <div className="mb-6">
                <h1 className="text-2xl font-black text-white">Packages</h1>
                <p className="text-sm text-[#666] mt-0.5">Top up your credits and keep training.</p>
            </div>

            <SuccessFlash />
            <ActiveBanner sub={activeSubscription} />

            {packages.length === 0 ? (
                <div className="text-center py-16">
                    <p className="text-4xl mb-3">📦</p>
                    <p className="font-bold text-white">No packages available yet.</p>
                </div>
            ) : (
                <div className="grid grid-cols-1 gap-5">
                    {packages.map((pkg) => (
                        <PackageCard key={pkg.id} pkg={pkg} onSubscribe={handleSubscribe} />
                    ))}
                </div>
            )}

            <div className="mt-8 bg-[#111] rounded-2xl border border-[#2A2A2A] p-4 text-xs text-[#555] text-center">
                Credits are used to book classes (1 credit per class). Credits do not expire within the validity period.
            </div>
        </AppLayout>
    );
}
