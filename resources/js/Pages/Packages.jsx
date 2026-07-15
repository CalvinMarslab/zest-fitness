import { router, usePage } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';

function SuccessFlash() {
    const { flash } = usePage().props;
    if (!flash?.success) return null;
    return (
        <div className="mb-4 rounded-2xl bg-[#FFF34D]/10 border border-[#FFF34D]/30 px-4 py-3 text-[#5A6A35] text-sm font-medium">
            {flash.success}
        </div>
    );
}

function ActiveBanner({ sub }) {
    if (!sub) return null;
    return (
        <div className="mb-6 bg-[#FFF34D]/5 border border-[#FFF34D]/20 rounded-2xl px-4 py-4">
            <p className="text-sm font-bold text-[#FFF34D]">
                ✅ Active: <span className="font-black">{sub.package_name}</span>
            </p>
            <p className="text-xs text-[#FFF34D]/60 mt-0.5">
                {sub.credits} credits granted · Expires {new Date(sub.expires_at + 'T00:00:00').toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' })}
            </p>
        </div>
    );
}

function PackageCard({ pkg, onSubscribe }) {
    const hasBadge  = !!pkg.badge;
    const trialUsed = pkg.trial_used;

    return (
        <div className={`relative bg-[#FFFFFF] rounded-2xl border p-5 flex flex-col ${
            trialUsed ? 'border-[#DDD5C0] opacity-60' : hasBadge ? 'border-[#FFF34D]/40' : 'border-[#DDD5C0]'
        }`}>
            {hasBadge && (
                <div className="absolute -top-3 left-1/2 -translate-x-1/2">
                    <span className={`text-xs font-black px-4 py-1 rounded-full whitespace-nowrap ${
                        trialUsed
                            ? 'bg-[#EDE5D4] text-[#666]'
                            : 'bg-[#FFF34D] text-[#333E48]'
                    }`}>
                        {trialUsed ? '✓ Trial Used' : pkg.badge}
                    </span>
                </div>
            )}

            <div className="mb-4 mt-1">
                <h3 className="text-lg font-black text-[#333E48]">{pkg.name}</h3>
                {pkg.description && (
                    <p className="text-sm text-[#666] mt-1">{pkg.description}</p>
                )}
            </div>

            <div className="mb-4">
                <div className="flex items-baseline gap-1">
                    <span className="text-3xl font-black text-[#333E48]">
                        RM {parseFloat(pkg.price).toFixed(2)}
                    </span>
                    <span className="text-sm text-[#555]">/ {pkg.period_label}</span>
                </div>
            </div>

            <div className="flex items-center gap-2 mb-4">
                <div className="flex items-center gap-2 bg-[#CFE0EB]/30 border border-[#CFE0EB]/60 rounded-xl px-3 py-2 w-full">
                    <span className="text-xl">🎟</span>
                    <div>
                        <p className="text-sm font-black text-[#333E48]">{pkg.credits} Credits</p>
                        <p className="text-xs text-[#5A7A8A]">Valid for {pkg.period_label}</p>
                    </div>
                </div>
            </div>

            {pkg.is_trial && (
                <p className="text-xs text-amber-500/80 mb-4">⚡ One-time trial — available once per member</p>
            )}

            <div className="flex-1" />

            <button
                onClick={() => !trialUsed && onSubscribe(pkg)}
                disabled={trialUsed}
                className={`w-full py-3 rounded-2xl font-black text-sm transition-all active:scale-[0.98] disabled:cursor-not-allowed ${
                    trialUsed
                        ? 'bg-[#FFFFFF] border border-[#DDD5C0] text-[#444]'
                        : hasBadge
                            ? 'bg-[#FFF34D] text-[#333E48] hover:bg-[#FFE633]'
                            : 'bg-[#DDD5C0] text-[#333E48] hover:bg-[#EDE5D4]'
                }`}
            >
                {trialUsed ? 'Trial Already Used' : `Get ${pkg.name}`}
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
                <h1 className="text-2xl font-black text-[#333E48]">Packages</h1>
                <p className="text-sm text-[#666] mt-0.5">Top up your credits and keep training.</p>
            </div>

            <SuccessFlash />
            <ActiveBanner sub={activeSubscription} />

            {packages.length === 0 ? (
                <div className="text-center py-16">
                    <p className="text-4xl mb-3">📦</p>
                    <p className="font-bold text-[#333E48]">No packages available yet.</p>
                </div>
            ) : (
                <div className="grid grid-cols-1 gap-5">
                    {packages.map((pkg) => (
                        <PackageCard key={pkg.id} pkg={pkg} onSubscribe={handleSubscribe} />
                    ))}
                </div>
            )}

            <div className="mt-8 bg-white/60 rounded-2xl border border-[#DDD5C0] p-4 text-xs text-[#6A7A85] text-center">
                Credits are used to book classes (1 credit per class). Credits do not expire within the validity period.
            </div>
        </AppLayout>
    );
}
