import { Head, useForm } from '@inertiajs/react';
import GuestLayout from '@/Layouts/GuestLayout';

export default function ChangePassword() {
    const { data, setData, post, processing, errors, reset } = useForm({
        password: '',
        password_confirmation: '',
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('password.change.update'), {
            onFinish: () => reset('password', 'password_confirmation'),
        });
    };

    const inputClass =
        'w-full rounded-xl bg-[#CFE0EB] border border-[#DDD5C0] text-[#333E48] px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-[#FFF34D]/50 focus:border-[#FFF34D]/50 transition-all placeholder:text-[#444]';
    const labelClass =
        'block text-xs font-bold text-[#888] uppercase tracking-widest mb-1.5';

    return (
        <GuestLayout>
            <Head title="Set New Password" />

            <div className="mb-6 text-center">
                <div className="text-4xl mb-3">🔒</div>
                <h1 className="text-xl font-black text-[#333E48]">Set a new password</h1>
                <p className="text-sm text-[#666] mt-1">
                    Your account requires a password change before you can continue.
                </p>
            </div>

            <form onSubmit={submit} className="flex flex-col gap-4">
                <div>
                    <label htmlFor="password" className={labelClass}>
                        New Password
                    </label>
                    <input
                        id="password"
                        type="password"
                        name="password"
                        value={data.password}
                        onChange={(e) => setData('password', e.target.value)}
                        autoComplete="new-password"
                        autoFocus
                        required
                        className={inputClass}
                    />
                    {errors.password && (
                        <p className="text-xs text-red-400 mt-1">{errors.password}</p>
                    )}
                    <p className="text-xs text-[#888] mt-1">
                        Min 8 characters — include upper &amp; lower case and a number.
                    </p>
                </div>

                <div>
                    <label htmlFor="password_confirmation" className={labelClass}>
                        Confirm Password
                    </label>
                    <input
                        id="password_confirmation"
                        type="password"
                        name="password_confirmation"
                        value={data.password_confirmation}
                        onChange={(e) => setData('password_confirmation', e.target.value)}
                        autoComplete="new-password"
                        required
                        className={inputClass}
                    />
                    {errors.password_confirmation && (
                        <p className="text-xs text-red-400 mt-1">
                            {errors.password_confirmation}
                        </p>
                    )}
                </div>

                <button
                    type="submit"
                    disabled={processing}
                    className="w-full mt-2 py-3.5 rounded-2xl bg-[#FFF34D] text-[#333E48] font-black text-sm hover:bg-[#FFE633] active:scale-[0.98] transition-all disabled:opacity-60"
                >
                    {processing ? 'Saving…' : 'Set Password & Continue'}
                </button>
            </form>
        </GuestLayout>
    );
}
