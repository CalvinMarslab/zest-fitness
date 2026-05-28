import { useState } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import GuestLayout from '@/Layouts/GuestLayout';
import InputError from '@/Components/InputError';

// ── Shared field component ────────────────────────────────────────────────────

function Field({ label, id, error, children }) {
    return (
        <div>
            <label htmlFor={id} className="block text-xs font-semibold text-[#888] uppercase tracking-widest mb-1.5">
                {label}
            </label>
            {children}
            {error && <InputError message={error} className="mt-1" />}
        </div>
    );
}

function Input({ type = 'text', id, name, value, onChange, autoComplete, autoFocus }) {
    return (
        <input
            id={id}
            type={type}
            name={name}
            value={value}
            onChange={onChange}
            autoComplete={autoComplete}
            autoFocus={autoFocus}
            required
            className="w-full rounded-xl bg-[#111] border border-[#2A2A2A] text-white px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-[#C8FF00]/50 focus:border-[#C8FF00]/50 transition-all placeholder:text-[#444]"
        />
    );
}

// ── Login form ────────────────────────────────────────────────────────────────

function LoginForm({ status, canResetPassword }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '', password: '', remember: false,
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('login'), { onFinish: () => reset('password') });
    };

    return (
        <form onSubmit={submit} className="flex flex-col gap-4">
            {status && (
                <p className="text-sm font-medium text-[#C8FF00] bg-[#C8FF00]/10 border border-[#C8FF00]/20 rounded-xl px-3 py-2">{status}</p>
            )}

            <Field label="Email" id="email" error={errors.email}>
                <Input id="email" type="email" name="email" value={data.email}
                    onChange={(e) => setData('email', e.target.value)}
                    autoComplete="username" autoFocus />
            </Field>

            <Field label="Password" id="password" error={errors.password}>
                <Input id="password" type="password" name="password" value={data.password}
                    onChange={(e) => setData('password', e.target.value)}
                    autoComplete="current-password" />
            </Field>

            <div className="flex items-center justify-between">
                <label className="flex items-center gap-2 text-sm text-[#888] cursor-pointer">
                    <input type="checkbox" checked={data.remember}
                        onChange={(e) => setData('remember', e.target.checked)}
                        className="accent-[#C8FF00] rounded" />
                    Remember me
                </label>
                {canResetPassword && (
                    <Link href={route('password.request')}
                        className="text-xs text-[#C8FF00] hover:text-white font-medium transition-colors">
                        Forgot password?
                    </Link>
                )}
            </div>

            <button type="submit" disabled={processing}
                className="w-full mt-2 py-3.5 rounded-2xl bg-[#C8FF00] text-[#0D0D0D] font-black text-sm hover:bg-[#d4ff33] active:scale-[0.98] transition-all disabled:opacity-60">
                {processing ? 'Signing in…' : 'Sign In'}
            </button>
        </form>
    );
}

// ── Register form ─────────────────────────────────────────────────────────────

function RegisterForm() {
    const { data, setData, post, processing, errors, reset } = useForm({
        name: '', email: '', password: '', password_confirmation: '',
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('register'), { onFinish: () => reset('password', 'password_confirmation') });
    };

    return (
        <form onSubmit={submit} className="flex flex-col gap-4">
            <Field label="Full Name" id="reg-name" error={errors.name}>
                <Input id="reg-name" name="name" value={data.name}
                    onChange={(e) => setData('name', e.target.value)}
                    autoComplete="name" autoFocus />
            </Field>

            <Field label="Email" id="reg-email" error={errors.email}>
                <Input id="reg-email" type="email" name="email" value={data.email}
                    onChange={(e) => setData('email', e.target.value)}
                    autoComplete="username" />
            </Field>

            <Field label="Password" id="reg-password" error={errors.password}>
                <Input id="reg-password" type="password" name="password" value={data.password}
                    onChange={(e) => setData('password', e.target.value)}
                    autoComplete="new-password" />
            </Field>

            <Field label="Confirm Password" id="reg-confirm" error={errors.password_confirmation}>
                <Input id="reg-confirm" type="password" name="password_confirmation"
                    value={data.password_confirmation}
                    onChange={(e) => setData('password_confirmation', e.target.value)}
                    autoComplete="new-password" />
            </Field>

            <button type="submit" disabled={processing}
                className="w-full mt-2 py-3.5 rounded-2xl bg-[#C8FF00] text-[#0D0D0D] font-black text-sm hover:bg-[#d4ff33] active:scale-[0.98] transition-all disabled:opacity-60">
                {processing ? 'Creating account…' : 'Create Account'}
            </button>

            <p className="text-xs text-center text-[#555]">
                You'll receive <strong className="text-[#C8FF00]">10 free credits</strong> to start booking classes.
            </p>
        </form>
    );
}

// ── Page ──────────────────────────────────────────────────────────────────────

export default function Login({ status, canResetPassword }) {
    const [tab, setTab] = useState('login');

    return (
        <GuestLayout>
            <Head title={tab === 'login' ? 'Sign In' : 'Create Account'} />

            {/* Tab toggle */}
            <div className="flex bg-[#111] rounded-2xl p-1 mb-6 border border-[#2A2A2A]">
                {[['login', 'Sign In'], ['register', 'Register']].map(([key, label]) => (
                    <button
                        key={key}
                        type="button"
                        onClick={() => setTab(key)}
                        className={[
                            'flex-1 py-2.5 rounded-xl text-sm font-bold transition-all',
                            tab === key
                                ? 'bg-[#C8FF00] text-[#0D0D0D]'
                                : 'text-[#666] hover:text-white',
                        ].join(' ')}
                    >
                        {label}
                    </button>
                ))}
            </div>

            {tab === 'login'
                ? <LoginForm status={status} canResetPassword={canResetPassword} />
                : <RegisterForm />
            }
        </GuestLayout>
    );
}
