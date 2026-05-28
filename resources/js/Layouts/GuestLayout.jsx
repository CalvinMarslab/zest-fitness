export default function GuestLayout({ children }) {
    return (
        <div className="min-h-screen bg-[#0D0D0D] flex items-center justify-center p-5">
            <div className="w-full max-w-sm">
                {/* Brand */}
                <div className="text-center mb-10">
                    <div className="flex items-baseline justify-center gap-1 mb-2">
                        <span className="text-4xl font-black text-[#C8FF00] tracking-tight">Zest</span>
                        <span className="text-4xl font-black text-white tracking-tight">Fitness</span>
                    </div>
                    <p className="text-sm text-[#666]">Train harder. Track smarter.</p>
                </div>

                <div className="bg-[#1A1A1A] rounded-3xl border border-[#2A2A2A] px-6 py-8">
                    {children}
                </div>
            </div>
        </div>
    );
}
