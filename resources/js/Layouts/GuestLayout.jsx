export default function GuestLayout({ children }) {
    return (
        <div className="min-h-screen bg-[#CFE0EB] flex items-center justify-center p-5">
            <div className="w-full max-w-sm">
                {/* Brand */}
                <div className="text-center mb-10">
                    <div className="flex justify-center mb-3">
                        <img src="/images/logo.svg" alt="Zest Athletic" className="h-14" />
                    </div>
                    <p className="text-sm text-[#5A6A75]">Train harder. Track smarter.</p>
                </div>

                <div className="bg-[#FFFFFF] rounded-3xl border border-[#DDD5C0] px-6 py-8">
                    {children}
                </div>
            </div>
        </div>
    );
}
