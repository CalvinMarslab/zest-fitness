import { Head, useForm } from '@inertiajs/react';
import GuestLayout from '@/Layouts/GuestLayout';

export default function ResetPassword({ token, email }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        token,
        email,
        password: '',
        password_confirmation: '',
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('password.store'), {
            onFinish: () => reset('password', 'password_confirmation'),
        });
    };

    const inputClass = "w-full rounded-xl bg-[#F5EEE0] border border-[#DDD5C0] text-[#333E48] px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-[#FFF34D]/50 focus:border-[#FFF34D]/50 transition-all placeholder:text-[#444]";
    const labelClass = "block text-xs font-bold text-[#888] uppercase tracking-widest mb-1.5";

    return (
        <GuestLayout>
            <Head title="Reset Password" />

            <div className="mb-6 text-center">
                <div className="text-4xl mb-3">🔒</div>
                <h1 className="text-xl font-black text-[#333E48]">Set new password</h1>
                <p className="text-sm text-[#666] mt-1">Choose a strong password for your account.</p>
            </div>

            <form onSubmit={submit} className="flex flex-col gap-4">
                <div>
                    <label htmlFor="email" className={labelClass}>Email</label>
                    <input id="email" type="email" name="email" value={data.email}
                        onChange={(e) => setData('email', e.target.value)}
                        autoComplete="username" required className={inputClass} />
                    {errors.email && <p className="text-xs text-red-400 mt-1">{errors.email}</p>}
                </div>

                <div>
                    <label htmlFor="password" className={labelClass}>New Password</label>
                    <input id="password" type="password" name="password" value={data.password}
                        onChange={(e) => setData('password', e.target.value)}
                        autoComplete="new-password" autoFocus required className={inputClass} />
                    {errors.password && <p className="text-xs text-red-400 mt-1">{errors.password}</p>}
                </div>

                <div>
                    <label htmlFor="password_confirmation" className={labelClass}>Confirm Password</label>
                    <input id="password_confirmation" type="password" name="password_confirmation"
                        value={data.password_confirmation}
                        onChange={(e) => setData('password_confirmation', e.target.value)}
                        autoComplete="new-password" required className={inputClass} />
                    {errors.password_confirmation && <p className="text-xs text-red-400 mt-1">{errors.password_confirmation}</p>}
                </div>

                <button type="submit" disabled={processing}
                    className="w-full mt-2 py-3.5 rounded-2xl bg-[#FFF34D] text-[#333E48] font-black text-sm hover:bg-[#FFE633] active:scale-[0.98] transition-all disabled:opacity-60">
                    {processing ? 'Resetting…' : 'Reset Password'}
                </button>
            </form>
        </GuestLayout>
    );
}
