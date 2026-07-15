import { useRef, useState } from 'react';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';

// ─── Wallet card ──────────────────────────────────────────────────────────────

function WalletCard({ subscription }) {
    const { auth } = usePage().props;
    const credits = auth?.user?.credits ?? 0;

    const formatDate = (dateStr) => {
        const d = new Date(dateStr);
        return d.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
    };

    return (
        <div className="relative overflow-hidden rounded-3xl bg-[#FFF34D] p-5">
            {/* Background decoration */}
            <div className="absolute -right-6 -top-6 w-32 h-32 rounded-full bg-black/5" />
            <div className="absolute -right-2 bottom-2 w-20 h-20 rounded-full bg-black/5" />

            <div className="relative flex items-center justify-between">
                <div>
                    <p className="text-xs font-bold text-[#333E48]/60 uppercase tracking-widest mb-1">Credit Wallet</p>
                    <div className="flex items-end gap-1.5">
                        <span className="text-4xl font-black text-[#333E48] leading-none">{credits}</span>
                        <span className="text-sm font-bold text-[#333E48]/60 mb-0.5">credits</span>
                    </div>

                    {/* Expiry line */}
                    {subscription ? (
                        <p className={`text-xs mt-1 font-semibold ${subscription.is_expiring_soon ? 'text-red-700' : 'text-[#333E48]/50'}`}>
                            {subscription.is_expiring_soon ? '⚠️ ' : ''}Expires {formatDate(subscription.expires_at)}
                        </p>
                    ) : (
                        <p className="text-xs text-[#333E48]/50 mt-1">
                            {credits === 0 ? 'No credits — top up to book classes' : 'No active subscription'}
                        </p>
                    )}
                </div>

                <Link
                    href={route('packages')}
                    className="shrink-0 flex items-center gap-1.5 bg-[#333E48] text-[#FFF34D] text-xs font-black px-4 py-2.5 rounded-2xl hover:bg-[#1a1a1a] active:scale-95 transition-all"
                >
                    <span>+</span> Top Up
                </Link>
            </div>
        </div>
    );
}

// ─── Shared field components ──────────────────────────────────────────────────

function Field({ label, error, children }) {
    return (
        <div>
            <label className="block text-xs font-bold text-[#888] uppercase tracking-widest mb-1.5">
                {label}
            </label>
            {children}
            {error && <p className="text-xs text-red-400 mt-1">{error}</p>}
        </div>
    );
}

function Input({ type = 'text', value, onChange, autoComplete, autoFocus, ref: refProp, placeholder }) {
    return (
        <input
            ref={refProp}
            type={type}
            value={value}
            onChange={onChange}
            autoComplete={autoComplete}
            autoFocus={autoFocus}
            placeholder={placeholder}
            className="w-full rounded-xl bg-[#CFE0EB] border border-[#DDD5C0] text-[#333E48] px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-[#FFF34D]/50 focus:border-[#FFF34D]/50 transition-all placeholder:text-[#444]"
        />
    );
}

function Section({ title, subtitle, children }) {
    return (
        <div className="bg-[#FFFFFF] rounded-3xl border border-[#DDD5C0] p-5">
            <h2 className="text-base font-black text-[#333E48] mb-0.5">{title}</h2>
            {subtitle && <p className="text-xs text-[#666] mb-5">{subtitle}</p>}
            {children}
        </div>
    );
}

// ─── Update Profile Info ──────────────────────────────────────────────────────

function UpdateProfileForm({ mustVerifyEmail, status }) {
    const user = usePage().props.auth.user;
    const { data, setData, patch, errors, processing, recentlySuccessful } = useForm({
        name: user.name,
        email: user.email,
    });

    const submit = (e) => {
        e.preventDefault();
        patch(route('profile.update'));
    };

    return (
        <Section title="Profile Information" subtitle="Update your name and email address.">
            <form onSubmit={submit} className="flex flex-col gap-4">
                <Field label="Full Name" error={errors.name}>
                    <Input value={data.name} onChange={(e) => setData('name', e.target.value)} autoComplete="name" autoFocus />
                </Field>

                <Field label="Email" error={errors.email}>
                    <Input type="email" value={data.email} onChange={(e) => setData('email', e.target.value)} autoComplete="username" />
                </Field>

                {mustVerifyEmail && user.email_verified_at === null && (
                    <div className="bg-amber-500/10 border border-amber-500/20 rounded-xl px-4 py-3 text-xs text-amber-400">
                        Your email address is unverified.{' '}
                        <Link href={route('verification.send')} method="post" as="button" className="underline font-bold">
                            Resend verification email.
                        </Link>
                        {status === 'verification-link-sent' && (
                            <p className="mt-1 font-bold text-[#FFF34D]">Verification link sent!</p>
                        )}
                    </div>
                )}

                <div className="flex items-center gap-3 mt-1">
                    <button type="submit" disabled={processing}
                        className="px-6 py-2.5 rounded-2xl bg-[#FFF34D] text-[#333E48] font-black text-sm hover:bg-[#FFE633] active:scale-[0.98] transition-all disabled:opacity-50">
                        {processing ? 'Saving…' : 'Save Changes'}
                    </button>
                    {recentlySuccessful && (
                        <span className="text-xs font-bold text-[#FFF34D]">Saved!</span>
                    )}
                </div>
            </form>
        </Section>
    );
}

// ─── Update Password ──────────────────────────────────────────────────────────

function UpdatePasswordForm() {
    const passwordInput = useRef();
    const currentPasswordInput = useRef();

    const { data, setData, errors, put, reset, processing, recentlySuccessful } = useForm({
        current_password: '',
        password: '',
        password_confirmation: '',
    });

    const submit = (e) => {
        e.preventDefault();
        put(route('password.update'), {
            preserveScroll: true,
            onSuccess: () => reset(),
            onError: (errs) => {
                if (errs.password) { reset('password', 'password_confirmation'); passwordInput.current?.focus(); }
                if (errs.current_password) { reset('current_password'); currentPasswordInput.current?.focus(); }
            },
        });
    };

    return (
        <Section title="Update Password" subtitle="Use a long, random password to stay secure.">
            <form onSubmit={submit} className="flex flex-col gap-4">
                <Field label="Current Password" error={errors.current_password}>
                    <input ref={currentPasswordInput} type="password" value={data.current_password}
                        onChange={(e) => setData('current_password', e.target.value)}
                        autoComplete="current-password"
                        className="w-full rounded-xl bg-[#CFE0EB] border border-[#DDD5C0] text-[#333E48] px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-[#FFF34D]/50 focus:border-[#FFF34D]/50 transition-all" />
                </Field>

                <Field label="New Password" error={errors.password}>
                    <input ref={passwordInput} type="password" value={data.password}
                        onChange={(e) => setData('password', e.target.value)}
                        autoComplete="new-password"
                        className="w-full rounded-xl bg-[#CFE0EB] border border-[#DDD5C0] text-[#333E48] px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-[#FFF34D]/50 focus:border-[#FFF34D]/50 transition-all" />
                </Field>

                <Field label="Confirm Password" error={errors.password_confirmation}>
                    <input type="password" value={data.password_confirmation}
                        onChange={(e) => setData('password_confirmation', e.target.value)}
                        autoComplete="new-password"
                        className="w-full rounded-xl bg-[#CFE0EB] border border-[#DDD5C0] text-[#333E48] px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-[#FFF34D]/50 focus:border-[#FFF34D]/50 transition-all" />
                </Field>

                <div className="flex items-center gap-3 mt-1">
                    <button type="submit" disabled={processing}
                        className="px-6 py-2.5 rounded-2xl bg-[#FFF34D] text-[#333E48] font-black text-sm hover:bg-[#FFE633] active:scale-[0.98] transition-all disabled:opacity-50">
                        {processing ? 'Saving…' : 'Update Password'}
                    </button>
                    {recentlySuccessful && (
                        <span className="text-xs font-bold text-[#FFF34D]">Saved!</span>
                    )}
                </div>
            </form>
        </Section>
    );
}

// ─── Delete Account ───────────────────────────────────────────────────────────

function DeleteAccountForm() {
    const [confirming, setConfirming] = useState(false);
    const passwordInput = useRef();

    const { data, setData, delete: destroy, processing, reset, errors } = useForm({ password: '' });

    const submit = (e) => {
        e.preventDefault();
        destroy(route('profile.destroy'), {
            preserveScroll: true,
            onSuccess: () => setConfirming(false),
            onError: () => passwordInput.current?.focus(),
            onFinish: () => reset(),
        });
    };

    return (
        <Section title="Delete Account">
            <p className="text-xs text-[#666] mb-4">
                Once your account is deleted, all data will be permanently removed. This action cannot be undone.
            </p>

            <button
                onClick={() => setConfirming(true)}
                className="px-6 py-2.5 rounded-2xl bg-red-500/20 border border-red-500/30 text-red-400 font-black text-sm hover:bg-red-500/30 transition-colors"
            >
                Delete Account
            </button>

            {confirming && (
                <div className="fixed inset-0 z-50 flex items-end justify-center">
                    <div className="absolute inset-0 bg-black/70 backdrop-blur-sm" onClick={() => { setConfirming(false); reset(); }} />
                    <div className="relative w-full max-w-lg bg-[#FFFFFF] rounded-t-3xl border-t border-[#DDD5C0] p-6 pb-10 shadow-2xl">
                        <div className="mx-auto mb-5 w-10 h-1 rounded-full bg-[#EDE5D4]" />
                        <h2 className="text-lg font-black text-[#333E48] mb-2">Delete your account?</h2>
                        <p className="text-sm text-[#666] mb-5">
                            All your data will be permanently deleted. Enter your password to confirm.
                        </p>
                        <form onSubmit={submit} className="flex flex-col gap-4">
                            <Field label="Password" error={errors.password}>
                                <input ref={passwordInput} type="password" value={data.password}
                                    onChange={(e) => setData('password', e.target.value)}
                                    autoFocus
                                    placeholder="Your current password"
                                    className="w-full rounded-xl bg-[#CFE0EB] border border-[#DDD5C0] text-[#333E48] px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-red-500/50 focus:border-red-500/50 transition-all placeholder:text-[#444]" />
                            </Field>
                            <div className="flex gap-3">
                                <button type="button" onClick={() => { setConfirming(false); reset(); }}
                                    className="flex-1 py-3 rounded-2xl bg-[#DDD5C0] text-[#888] font-bold text-sm hover:bg-[#EDE5D4] transition-colors">
                                    Cancel
                                </button>
                                <button type="submit" disabled={processing}
                                    className="flex-1 py-3 rounded-2xl bg-red-500/20 border border-red-500/30 text-red-400 font-black text-sm hover:bg-red-500/30 transition-colors disabled:opacity-50">
                                    {processing ? 'Deleting…' : 'Delete Account'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </Section>
    );
}

// ─── Page ─────────────────────────────────────────────────────────────────────

export default function Edit({ mustVerifyEmail, status, subscription }) {
    return (
        <AppLayout active="Profile">
            <Head title="Profile" />

            <div className="mb-5">
                <h1 className="text-2xl font-black text-[#333E48]">Profile</h1>
                <p className="text-sm text-[#666] mt-0.5">Manage your account settings</p>
            </div>

            <div className="flex flex-col gap-4">
                <WalletCard subscription={subscription} />
                <UpdateProfileForm mustVerifyEmail={mustVerifyEmail} status={status} />
                <UpdatePasswordForm />
                <DeleteAccountForm />
            </div>
        </AppLayout>
    );
}
