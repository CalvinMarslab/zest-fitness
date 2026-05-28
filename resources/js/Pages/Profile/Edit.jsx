import { useRef, useState } from 'react';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';

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
            className="w-full rounded-xl bg-[#111] border border-[#2A2A2A] text-white px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-[#C8FF00]/50 focus:border-[#C8FF00]/50 transition-all placeholder:text-[#444]"
        />
    );
}

function Section({ title, subtitle, children }) {
    return (
        <div className="bg-[#1A1A1A] rounded-3xl border border-[#2A2A2A] p-5">
            <h2 className="text-base font-black text-white mb-0.5">{title}</h2>
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
                            <p className="mt-1 font-bold text-[#C8FF00]">Verification link sent!</p>
                        )}
                    </div>
                )}

                <div className="flex items-center gap-3 mt-1">
                    <button type="submit" disabled={processing}
                        className="px-6 py-2.5 rounded-2xl bg-[#C8FF00] text-[#0D0D0D] font-black text-sm hover:bg-[#d4ff33] active:scale-[0.98] transition-all disabled:opacity-50">
                        {processing ? 'Saving…' : 'Save Changes'}
                    </button>
                    {recentlySuccessful && (
                        <span className="text-xs font-bold text-[#C8FF00]">Saved!</span>
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
                        className="w-full rounded-xl bg-[#111] border border-[#2A2A2A] text-white px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-[#C8FF00]/50 focus:border-[#C8FF00]/50 transition-all" />
                </Field>

                <Field label="New Password" error={errors.password}>
                    <input ref={passwordInput} type="password" value={data.password}
                        onChange={(e) => setData('password', e.target.value)}
                        autoComplete="new-password"
                        className="w-full rounded-xl bg-[#111] border border-[#2A2A2A] text-white px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-[#C8FF00]/50 focus:border-[#C8FF00]/50 transition-all" />
                </Field>

                <Field label="Confirm Password" error={errors.password_confirmation}>
                    <input type="password" value={data.password_confirmation}
                        onChange={(e) => setData('password_confirmation', e.target.value)}
                        autoComplete="new-password"
                        className="w-full rounded-xl bg-[#111] border border-[#2A2A2A] text-white px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-[#C8FF00]/50 focus:border-[#C8FF00]/50 transition-all" />
                </Field>

                <div className="flex items-center gap-3 mt-1">
                    <button type="submit" disabled={processing}
                        className="px-6 py-2.5 rounded-2xl bg-[#C8FF00] text-[#0D0D0D] font-black text-sm hover:bg-[#d4ff33] active:scale-[0.98] transition-all disabled:opacity-50">
                        {processing ? 'Saving…' : 'Update Password'}
                    </button>
                    {recentlySuccessful && (
                        <span className="text-xs font-bold text-[#C8FF00]">Saved!</span>
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
                    <div className="relative w-full max-w-lg bg-[#1A1A1A] rounded-t-3xl border-t border-[#2A2A2A] p-6 pb-10 shadow-2xl">
                        <div className="mx-auto mb-5 w-10 h-1 rounded-full bg-[#333]" />
                        <h2 className="text-lg font-black text-white mb-2">Delete your account?</h2>
                        <p className="text-sm text-[#666] mb-5">
                            All your data will be permanently deleted. Enter your password to confirm.
                        </p>
                        <form onSubmit={submit} className="flex flex-col gap-4">
                            <Field label="Password" error={errors.password}>
                                <input ref={passwordInput} type="password" value={data.password}
                                    onChange={(e) => setData('password', e.target.value)}
                                    autoFocus
                                    placeholder="Your current password"
                                    className="w-full rounded-xl bg-[#111] border border-[#2A2A2A] text-white px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-red-500/50 focus:border-red-500/50 transition-all placeholder:text-[#444]" />
                            </Field>
                            <div className="flex gap-3">
                                <button type="button" onClick={() => { setConfirming(false); reset(); }}
                                    className="flex-1 py-3 rounded-2xl bg-[#2A2A2A] text-[#888] font-bold text-sm hover:bg-[#333] transition-colors">
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

export default function Edit({ mustVerifyEmail, status }) {
    return (
        <AppLayout active="Profile">
            <Head title="Profile" />

            <div className="mb-5">
                <h1 className="text-2xl font-black text-white">Profile</h1>
                <p className="text-sm text-[#666] mt-0.5">Manage your account settings</p>
            </div>

            <div className="flex flex-col gap-4">
                <UpdateProfileForm mustVerifyEmail={mustVerifyEmail} status={status} />
                <UpdatePasswordForm />
                <DeleteAccountForm />
            </div>
        </AppLayout>
    );
}
