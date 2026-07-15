import { Head, Link, useForm } from '@inertiajs/react';
import GuestLayout from '@/Layouts/GuestLayout';

export default function ForgotPassword({ status }) {
    const { data, setData, post, processing, errors } = useForm({ email: '' });

    const submit = (e) => {
        e.preventDefault();
        post(route('password.email'));
    };

    return (
        <GuestLayout>
            <Head title="Forgot Password" />

            <div className="mb-6 text-center">
                <div className="text-4xl mb-3">🔑</div>
                <h1 className="text-xl font-black text-[#333E48]">Forgot your password?</h1>
                <p className="text-sm text-[#666] mt-1">
                    Enter your email and we'll send you a reset link.
                </p>
            </div>

            {status && (
                <div className="mb-4 rounded-2xl bg-[#FFF34D]/10 border border-[#FFF34D]/30 px-4 py-3 text-[#FFF34D] text-sm font-medium">
                    {status}
                </div>
            )}

            <form onSubmit={submit} className="flex flex-col gap-4">
                <div>
                    <label htmlFor="email" className="block text-xs font-bold text-[#888] uppercase tracking-widest mb-1.5">
                        Email
                    </label>
                    <input
                        id="email"
                        type="email"
                        name="email"
                        value={data.email}
                        onChange={(e) => setData('email', e.target.value)}
                        autoFocus
                        required
                        placeholder="you@example.com"
                        className="w-full rounded-xl bg-[#F5EEE0] border border-[#DDD5C0] text-[#333E48] px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-[#FFF34D]/50 focus:border-[#FFF34D]/50 transition-all placeholder:text-[#444]"
                    />
                    {errors.email && <p className="text-xs text-red-400 mt-1">{errors.email}</p>}
                </div>

                <button
                    type="submit"
                    disabled={processing}
                    className="w-full mt-2 py-3.5 rounded-2xl bg-[#FFF34D] text-[#333E48] font-black text-sm hover:bg-[#FFE633] active:scale-[0.98] transition-all disabled:opacity-60"
                >
                    {processing ? 'Sending…' : 'Send Reset Link'}
                </button>
            </form>

            <p className="text-center text-xs text-[#555] mt-6">
                Remember your password?{' '}
                <Link href={route('login')} className="text-[#FFF34D] font-bold hover:text-[#333E48] transition-colors">
                    Sign in
                </Link>
            </p>
        </GuestLayout>
    );
}
