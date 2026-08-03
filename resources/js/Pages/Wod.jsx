import { useState, useEffect } from 'react';
import { router, usePage, useForm } from '@inertiajs/react';

// ── Passcode gate ─────────────────────────────────────────────────────────────

function PinDot({ filled }) {
    return (
        <div
            className="w-4 h-4 rounded-full border-2 transition-all duration-150"
            style={{
                background:   filled ? 'white' : 'transparent',
                borderColor:  filled ? 'white' : 'rgba(255,255,255,0.4)',
                transform:    filled ? 'scale(1.1)' : 'scale(1)',
            }}
        />
    );
}

function PasscodeGate() {
    const { flash } = usePage().props;
    const [pin, setPin]       = useState('');
    const [shake, setShake]   = useState(false);
    const [submitting, setSubmitting] = useState(false);

    useEffect(() => {
        if (flash?.wod_error) {
            setShake(true);
            setPin('');
            setTimeout(() => setShake(false), 500);
        }
    }, [flash]);

    function press(digit) {
        if (pin.length >= 4 || submitting) return;
        const next = pin + digit;
        setPin(next);
        if (next.length === 4) {
            setSubmitting(true);
            router.post(route('wod.verify'), { passcode: next }, {
                onFinish: () => setSubmitting(false),
            });
        }
    }

    function del() {
        setPin((p) => p.slice(0, -1));
    }

    const keys = ['1','2','3','4','5','6','7','8','9','','0','⌫'];

    return (
        <div
            className="min-h-screen flex flex-col items-center justify-center px-6"
            style={{ background: 'linear-gradient(160deg, #111827 0%, #1f2937 100%)' }}
        >
            {/* Logo / title */}
            <div className="mb-10 text-center">
                <p className="text-3xl font-extrabold text-white tracking-tight">Zest Athletic</p>
                <p className="text-sm mt-1" style={{ color: '#9ca3af' }}>Today's WOD</p>
            </div>

            {/* PIN dots */}
            <div
                className="flex gap-5 mb-2"
                style={{
                    animation: shake ? 'shake 0.4s ease' : 'none',
                }}
            >
                {[0,1,2,3].map((i) => <PinDot key={i} filled={i < pin.length} />)}
            </div>

            {/* Error */}
            <p className="text-xs mb-8 h-4 transition-opacity" style={{ color: '#f87171', opacity: flash?.wod_error ? 1 : 0 }}>
                {flash?.wod_error ?? ' '}
            </p>

            {/* Numpad */}
            <div className="grid grid-cols-3 gap-3 w-full max-w-xs">
                {keys.map((k, i) => {
                    if (k === '') return <div key={i} />;
                    const isDelete = k === '⌫';
                    return (
                        <button
                            key={i}
                            type="button"
                            onClick={() => isDelete ? del() : press(k)}
                            className="h-16 rounded-2xl text-xl font-semibold flex items-center justify-center transition-all active:scale-95"
                            style={{
                                background: isDelete ? 'rgba(255,255,255,0.05)' : 'rgba(255,255,255,0.08)',
                                color: 'white',
                                border: '1px solid rgba(255,255,255,0.08)',
                            }}
                            onMouseEnter={(e) => { e.currentTarget.style.background = 'rgba(255,255,255,0.14)'; }}
                            onMouseLeave={(e) => { e.currentTarget.style.background = isDelete ? 'rgba(255,255,255,0.05)' : 'rgba(255,255,255,0.08)'; }}
                        >
                            {k}
                        </button>
                    );
                })}
            </div>

            <style>{`
                @keyframes shake {
                    0%,100% { transform: translateX(0); }
                    20%      { transform: translateX(-8px); }
                    40%      { transform: translateX(8px); }
                    60%      { transform: translateX(-5px); }
                    80%      { transform: translateX(5px); }
                }
            `}</style>
        </div>
    );
}

// ── WOD content ───────────────────────────────────────────────────────────────

const WOD_TYPE_COLORS = {
    'AMRAP':    { bg: '#fef3c7', text: '#d97706', border: '#fde68a' },
    'EMOM':     { bg: '#ede9fe', text: '#7c3aed', border: '#ddd6fe' },
    'For Time': { bg: '#fee2e2', text: '#dc2626', border: '#fecaca' },
    'On/Off':   { bg: '#d1fae5', text: '#059669', border: '#a7f3d0' },
};

const WOD_CONFIG_LABELS = {
    duration: 'Duration',
    every:    'Every',
    time_cap: 'Time Cap',
    rounds:   'Rounds',
    work:     'Work',
    rest:     'Rest',
};

function formatTime(iso) {
    return new Date(iso).toLocaleTimeString('en-US', {
        hour: 'numeric', minute: '2-digit', hour12: true,
    });
}

function formatDate(dateStr) {
    return new Date(dateStr + 'T00:00:00').toLocaleDateString('en-US', {
        weekday: 'long', month: 'long', day: 'numeric',
    });
}

function ConfigBadge({ label, value }) {
    return (
        <div className="flex flex-col items-center justify-center rounded-2xl px-4 py-3 min-w-[80px]"
            style={{ background: '#f9fafb', border: '1px solid #f3f4f6' }}>
            <span className="text-xs text-gray-400 font-medium uppercase tracking-wide">{label}</span>
            <span className="text-base font-bold text-gray-900 mt-0.5">{value}</span>
        </div>
    );
}

function ExerciseRow({ ex, index }) {
    const weights = [
        ex.men_rx   && { label: 'M RX',  value: ex.men_rx },
        ex.men_sc   && { label: 'M SC',  value: ex.men_sc },
        ex.women_rx && { label: 'W RX',  value: ex.women_rx },
        ex.women_sc && { label: 'W SC',  value: ex.women_sc },
    ].filter(Boolean);

    return (
        <div className="flex items-start gap-4 py-4 border-b border-gray-50 last:border-0">
            <div className="w-7 h-7 rounded-full bg-orange-100 text-orange-600 text-xs font-bold flex items-center justify-center shrink-0 mt-0.5">
                {index + 1}
            </div>
            <div className="flex-1 min-w-0">
                <div className="flex items-center gap-2 flex-wrap">
                    <span className="text-sm font-bold text-gray-900">{ex.name}</span>
                    {ex.volume && (
                        <span className="text-xs font-semibold text-orange-600 bg-orange-50 px-2 py-0.5 rounded-full border border-orange-100">
                            {ex.volume}
                        </span>
                    )}
                    {ex.target && (
                        <span className="text-xs text-gray-500 bg-gray-50 px-2 py-0.5 rounded-full border border-gray-100">
                            🎯 {ex.target}
                        </span>
                    )}
                </div>
                {weights.length > 0 && (
                    <div className="flex flex-wrap gap-2 mt-1.5">
                        {weights.map(({ label, value }) => (
                            <span key={label} className="text-xs text-blue-600 bg-blue-50 border border-blue-100 px-2 py-0.5 rounded-full font-medium">
                                {label}: {value}
                            </span>
                        ))}
                    </div>
                )}
                {ex.rest && (
                    <p className="text-xs text-gray-400 mt-1">Rest: {ex.rest}</p>
                )}
            </div>
        </div>
    );
}

function ClassCard({ gymClass }) {
    const typeStyle = WOD_TYPE_COLORS[gymClass.wod_type] ?? { bg: '#f3f4f6', text: '#374151', border: '#e5e7eb' };
    const config = gymClass.wod_config ?? {};
    const configEntries = Object.entries(config).filter(([, v]) => v);
    const exercises = gymClass.exercises ?? [];

    return (
        <div className="bg-white rounded-3xl border border-gray-100 overflow-hidden shadow-sm">
            <div className="px-5 pt-5 pb-4">
                <div className="flex items-start justify-between gap-3">
                    <div className="flex-1 min-w-0">
                        <div className="flex items-center gap-2 flex-wrap mb-1">
                            <span className="text-lg font-bold text-gray-900">{gymClass.name}</span>
                            {gymClass.wod_type && (
                                <span
                                    className="text-xs font-bold px-2.5 py-1 rounded-full border"
                                    style={{ background: typeStyle.bg, color: typeStyle.text, borderColor: typeStyle.border }}
                                >
                                    {gymClass.wod_type}
                                </span>
                            )}
                        </div>
                        <p className="text-sm text-gray-400">
                            {formatTime(gymClass.start_time)} · Coach {gymClass.coach}
                        </p>
                    </div>
                </div>

                {configEntries.length > 0 && (
                    <div className="flex gap-2 mt-4 flex-wrap">
                        {configEntries.map(([key, value]) => (
                            <ConfigBadge key={key} label={WOD_CONFIG_LABELS[key] ?? key} value={value} />
                        ))}
                    </div>
                )}
            </div>

            {exercises.length > 0 ? (
                <div className="border-t border-gray-50 px-5">
                    <p className="text-xs font-bold uppercase tracking-widest text-gray-400 py-3">
                        Exercises · {exercises.length}
                    </p>
                    {exercises.map((ex, i) => (
                        <ExerciseRow key={i} ex={ex} index={i} />
                    ))}
                </div>
            ) : (
                <div className="border-t border-gray-50 px-5 py-6 text-center">
                    <p className="text-sm text-gray-400">Exercises not yet posted</p>
                </div>
            )}
        </div>
    );
}

// ── Page ──────────────────────────────────────────────────────────────────────

export default function Wod({ locked, classes, date }) {
    if (locked) return <PasscodeGate />;

    return (
        <div className="min-h-screen" style={{ background: '#f9fafb' }}>
            <div className="max-w-xl mx-auto px-4 py-8">
                <div className="flex items-start justify-between mb-6">
                    <div>
                        <p className="text-xs font-bold uppercase tracking-widest text-orange-500 mb-1">
                            {formatDate(date)}
                        </p>
                        <h1 className="text-3xl font-extrabold text-gray-900">Today's WOD</h1>
                    </div>
                    <button
                        type="button"
                        onClick={() => router.post(route('wod.logout'))}
                        className="mt-1 flex items-center gap-1.5 text-xs text-gray-400 hover:text-gray-600 transition-colors px-3 py-1.5 rounded-xl border border-gray-200 hover:border-gray-300 bg-white"
                    >
                        🔒 Lock
                    </button>
                </div>

                {classes.length === 0 ? (
                    <div className="bg-white rounded-3xl border border-gray-100 p-12 text-center shadow-sm">
                        <p className="text-4xl mb-3">🛌</p>
                        <p className="text-lg font-bold text-gray-900">Rest Day</p>
                        <p className="text-sm text-gray-400 mt-1">No classes scheduled for today. Recover well.</p>
                    </div>
                ) : (
                    <div className="space-y-4">
                        {classes.map((c) => (
                            <ClassCard key={c.id} gymClass={c} />
                        ))}
                    </div>
                )}
            </div>
        </div>
    );
}
