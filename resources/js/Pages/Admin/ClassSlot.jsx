import { useState } from 'react';
import { router, useForm } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { parseLocalDT, toLocalInputDT } from '@/utils/date';

const DAY_NAMES = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

function formatDate(dtStr) {
    return parseLocalDT(dtStr).toLocaleDateString('en-US', {
        weekday: 'short', month: 'short', day: 'numeric', year: 'numeric',
    });
}

// ── Left panel: date list ─────────────────────────────────────────────────────

function DateList({ instances, selectedId, template }) {
    return (
        <div className="w-56 shrink-0 bg-white rounded-2xl border border-gray-100 overflow-hidden flex flex-col">
            <div className="px-4 py-3 border-b border-gray-100 bg-gray-50">
                <p className="text-xs font-bold uppercase tracking-widest text-gray-500">
                    {DAY_NAMES[template.day_of_week]} · {template.start_time}
                </p>
                <p className="text-sm font-bold text-gray-900 mt-0.5">{template.name}</p>
            </div>
            <div className="flex-1 overflow-y-auto divide-y divide-gray-50">
                {instances.map((inst) => {
                    const isSelected = inst.id === selectedId;
                    return (
                        <button
                            key={inst.id}
                            onClick={() => router.get(route('admin.classes.slot'), {
                                template_id: template.id, id: inst.id,
                            }, { preserveScroll: true })}
                            className={[
                                'w-full text-left px-4 py-3 transition-colors',
                                isSelected ? 'bg-orange-50 border-l-2 border-orange-500' : 'hover:bg-gray-50 border-l-2 border-transparent',
                            ].join(' ')}
                        >
                            <p className={`text-sm font-semibold ${isSelected ? 'text-orange-600' : 'text-gray-900'}`}>
                                {formatDate(inst.start_time)}
                            </p>
                            <div className="flex items-center gap-2 mt-0.5">
                                <span className={`text-xs ${inst.bookings_count > 0 ? 'text-green-600 font-medium' : 'text-gray-400'}`}>
                                    {inst.bookings_count}/{inst.capacity} booked
                                </span>
                                {inst.is_cancelled && (
                                    <span className="text-[10px] font-bold text-red-500 bg-red-50 px-1.5 py-0.5 rounded-full">
                                        Closed
                                    </span>
                                )}
                            </div>
                        </button>
                    );
                })}
            </div>
        </div>
    );
}

// ── WOD type selector ─────────────────────────────────────────────────────────

const WOD_TYPES = [
    { key: 'AMRAP',    label: 'AMRAP',    desc: 'Complete as many rounds as possible in a set time (e.g., "20 min AMRAP: 10 push-ups, 15 squats").' },
    { key: 'EMOM',     label: 'EMOM',     desc: 'Perform work at the start of each minute, rest for remainder (e.g., "10 min EMOM: 5 burpees/min").' },
    { key: 'For Time', label: 'For Time', desc: 'Complete exercises as fast as possible with a time cap (e.g., "21-15-9: Thrusters & Pull-ups").' },
    { key: 'On/Off',   label: 'On / Off', desc: 'Timed work/rest intervals (e.g., "30 sec on, 15 sec off × 8 rounds"). Great for HIIT.' },
];

// Fields per WOD type shown in Block Details
const WOD_CONFIG_FIELDS = {
    'AMRAP':    [{ key: 'duration', label: 'Duration', placeholder: 'e.g. 20 min' }],
    'EMOM':     [
        { key: 'every',    label: 'Every',        placeholder: 'e.g. 1 min' },
        { key: 'duration', label: 'Total Time',   placeholder: 'e.g. 20 min' },
        { key: 'rounds',   label: 'Rounds',       placeholder: 'e.g. 20' },
    ],
    'For Time': [
        { key: 'time_cap', label: 'Time Cap',     placeholder: 'e.g. 20 min' },
        { key: 'rounds',   label: 'Rounds',       placeholder: 'e.g. 3' },
    ],
    'On/Off':   [
        { key: 'work',     label: 'Work',         placeholder: 'e.g. 30 sec' },
        { key: 'rest',     label: 'Rest',         placeholder: 'e.g. 15 sec' },
        { key: 'rounds',   label: 'Rounds',       placeholder: 'e.g. 8' },
    ],
    'Metcon':   [
        { key: 'rounds',   label: 'Rounds',       placeholder: 'e.g. 5' },
        { key: 'time_cap', label: 'Max Time',     placeholder: 'e.g. 30 min' },
    ],
};

function WodTypeModal({ value, onChange, onClose, onNext }) {
    const [pending, setPending] = useState(value);

    function confirm() {
        onChange(pending);
        onClose();
        if (onNext) onNext();
    }

    return (
        <div className="fixed inset-0 z-50 flex items-end justify-center" style={{ background: 'rgba(0,0,0,0.6)' }}>
            <div
                className="w-full max-w-md flex flex-col rounded-t-3xl overflow-hidden"
                style={{ background: '#111827', maxHeight: '85vh' }}
            >
                {/* Header */}
                <div className="flex items-center justify-between px-5 py-4 border-b border-white/5">
                    <p className="text-base font-bold text-white">Select Training Type</p>
                    <button type="button" onClick={onClose} className="text-sm font-semibold" style={{ color: '#f97316' }}>
                        Cancel
                    </button>
                </div>

                {/* Options */}
                <div className="flex-1 overflow-y-auto py-2">
                    {WOD_TYPES.map(({ key, label, desc }) => {
                        const selected = pending === key;
                        return (
                            <button
                                key={key}
                                type="button"
                                onClick={() => setPending(key)}
                                className="w-full flex items-start gap-4 px-5 py-4 border-b border-white/5 last:border-0 text-left transition-colors"
                                style={{ background: selected ? 'rgba(249,115,22,0.08)' : 'transparent' }}
                            >
                                <div
                                    className="w-5 h-5 rounded-full border-2 flex items-center justify-center shrink-0 mt-0.5"
                                    style={{
                                        borderColor: selected ? '#f97316' : '#4b5563',
                                        background: selected ? '#f97316' : 'transparent',
                                    }}
                                >
                                    {selected && <div className="w-2 h-2 rounded-full bg-white" />}
                                </div>
                                <div className="flex-1 min-w-0">
                                    <p className="text-sm font-bold" style={{ color: selected ? '#f97316' : 'white' }}>{label}</p>
                                    <p className="text-xs mt-1 leading-relaxed" style={{ color: '#9ca3af' }}>{desc}</p>
                                </div>
                            </button>
                        );
                    })}
                </div>

                {/* Continue */}
                <div className="px-5 py-4 border-t border-white/5">
                    <button
                        type="button"
                        onClick={confirm}
                        disabled={!pending}
                        className="w-full py-3.5 rounded-2xl text-sm font-bold transition-all"
                        style={{ background: pending ? '#f97316' : '#374151', color: 'white' }}
                    >
                        Continue
                    </button>
                </div>
            </div>
        </div>
    );
}

// ── Block details modal (step 2) ──────────────────────────────────────────────

function WodBlockModal({ wodType, config, onConfigChange, onBack, onNext }) {
    const typeInfo  = WOD_TYPES.find((t) => t.key === wodType);
    const fields    = WOD_CONFIG_FIELDS[wodType] ?? [];

    // Build a summary badge line from filled fields
    const badgeParts = fields
        .filter((f) => config[f.key])
        .map((f) => `${f.label}: ${config[f.key]}`);

    return (
        <div className="fixed inset-0 z-50 flex items-end justify-center" style={{ background: 'rgba(0,0,0,0.6)' }}>
            <div
                className="w-full max-w-md flex flex-col rounded-t-3xl overflow-hidden"
                style={{ background: '#111827', maxHeight: '85vh' }}
            >
                {/* Header */}
                <div className="flex items-center px-5 py-4 border-b border-white/5 gap-3">
                    <button type="button" onClick={onBack} className="w-8 h-8 flex items-center justify-center rounded-full shrink-0" style={{ background: '#1f2937', color: '#9ca3af' }}>
                        ‹
                    </button>
                    <p className="flex-1 text-center text-sm font-bold text-white">Block Details</p>
                    <div className="w-8" />
                </div>

                <div className="flex-1 overflow-y-auto">
                    {/* Type summary card */}
                    <div className="mx-4 mt-5 rounded-2xl p-4" style={{ background: '#1f2937' }}>
                        <div className="flex items-start justify-between">
                            <div className="flex-1">
                                <p className="text-xs font-bold uppercase tracking-widest mb-2" style={{ color: '#6b7280' }}>Block Details</p>
                                <p className="text-2xl font-extrabold text-white">{typeInfo?.label ?? wodType}</p>
                                <p className="text-sm mt-1 leading-relaxed" style={{ color: '#9ca3af' }}>{typeInfo?.desc}</p>
                            </div>
                            <button
                                type="button"
                                onClick={onBack}
                                className="w-8 h-8 flex items-center justify-center rounded-full shrink-0 ml-3"
                                style={{ background: '#374151' }}
                            >
                                <svg className="w-4 h-4" style={{ color: '#9ca3af' }} fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                </svg>
                            </button>
                        </div>
                        {badgeParts.length > 0 && (
                            <div className="mt-3 flex flex-wrap gap-2">
                                {badgeParts.map((b) => (
                                    <span key={b} className="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-semibold" style={{ background: '#374151', color: '#d1d5db' }}>
                                        ⏱ {b}
                                    </span>
                                ))}
                            </div>
                        )}
                    </div>

                    {/* Type-specific config fields */}
                    {fields.length > 0 && (
                        <div className="mx-4 mt-3 rounded-2xl overflow-hidden" style={{ background: '#1f2937' }}>
                            {fields.map((f, i) => (
                                <div
                                    key={f.key}
                                    className="flex items-center justify-between px-4 py-4"
                                    style={{ borderBottom: i < fields.length - 1 ? '1px solid rgba(255,255,255,0.05)' : 'none' }}
                                >
                                    <p className="text-sm font-bold text-white">{f.label}</p>
                                    <input
                                        className="text-right text-sm bg-transparent focus:outline-none"
                                        style={{ color: config[f.key] ? '#f9fafb' : '#4b5563', width: 110 }}
                                        placeholder={f.placeholder}
                                        value={config[f.key] ?? ''}
                                        onChange={(e) => onConfigChange({ ...config, [f.key]: e.target.value })}
                                    />
                                </div>
                            ))}
                        </div>
                    )}

                    {/* Empty state */}
                    <div className="mx-4 mt-3 mb-4 text-center py-10 rounded-2xl" style={{ background: '#1f2937' }}>
                        <p className="text-base font-bold text-white">No exercises added</p>
                        <p className="text-sm mt-1" style={{ color: '#6b7280' }}>Tap below to start building this WOD.</p>
                    </div>
                </div>

                {/* Add Exercise button */}
                <div className="px-5 py-4 border-t border-white/5">
                    <button
                        type="button"
                        onClick={onNext}
                        className="w-full py-3.5 rounded-2xl text-sm font-bold"
                        style={{ background: '#f97316', color: 'white' }}
                    >
                        Add Exercise
                    </button>
                </div>
            </div>
        </div>
    );
}

function WodTypeSelector({ value, onChange, config, onConfigChange, onNext }) {
    const [step, setStep] = useState(null); // null | 'type' | 'block'
    const selected = WOD_TYPES.find((t) => t.key === value);

    return (
        <>
            <div>
                <label className="text-xs font-semibold text-gray-500 uppercase">Training Type</label>
                <button
                    type="button"
                    onClick={() => setStep('type')}
                    className="mt-2 w-full flex items-center justify-between border rounded-xl px-4 py-2.5 text-sm transition-all hover:border-orange-300"
                    style={{ borderColor: value ? '#f97316' : undefined, background: value ? '#fff7f0' : undefined }}
                >
                    <span className={value ? 'font-semibold text-orange-600' : 'text-gray-400'}>
                        {selected ? selected.label : 'Select training type…'}
                    </span>
                    <span className="text-gray-400 text-xs">▼</span>
                </button>
            </div>

            {step === 'type' && (
                <WodTypeModal
                    value={value}
                    onChange={onChange}
                    onClose={() => setStep(null)}
                    onNext={() => setStep('block')}
                />
            )}

            {step === 'block' && (
                <WodBlockModal
                    wodType={value}
                    config={config}
                    onConfigChange={onConfigChange}
                    onBack={() => setStep('type')}
                    onNext={() => { setStep(null); onNext(); }}
                />
            )}
        </>
    );
}

// ── Exercise library ──────────────────────────────────────────────────────────

const POPULAR_EXERCISES = [
    'Run', 'Row Erg', 'Ski Erg', 'Sled Push', 'Sled Pull',
    'Burpee Broad Jump', 'Wall Ball', 'Box Jump',
];

const ALL_EXERCISES = [
    'Air Squat', 'Back Squat', 'Barbell Row', 'Bench Press', 'Box Jump', 'Box Jump Over',
    'Box Step Up', 'Broad Jump', 'Burpee', 'Burpee Box Jump', 'Burpee Broad Jump',
    'Clean', 'Clean & Jerk', 'Deadlift', 'Double Under', 'Dumbbell Clean', 'Dumbbell Lunge',
    'Dumbbell Snatch', 'Dumbbell Thruster', 'Front Squat', 'Handstand Push-Up',
    'Hang Power Clean', 'Jump Rope', 'Kettlebell Swing', 'Knees to Elbow', 'Muscle Up',
    'Overhead Squat', 'Pistol Squat', 'Power Clean', 'Power Snatch', 'Pull-Up',
    'Push-Up', 'Push Press', 'Row Erg', 'Run', 'Sandbag Carry', 'Shoulder Press',
    'Single Under', 'Sit-Up', 'Ski Erg', 'Sled Pull', 'Sled Push', 'Snatch',
    'Split Jerk', 'Sumo Deadlift High Pull', 'Thruster', 'Toes to Bar', 'Wall Ball', 'Wall Walk',
].sort();

function normalizeExercises(raw) {
    if (!Array.isArray(raw)) return [];
    return raw.map((e) => {
        if (typeof e === 'string') {
            return { name: e, volume: '', target: '', men_rx: '', men_sc: '', women_rx: '', women_sc: '', rest: '' };
        }
        return { men_rx: '', men_sc: '', women_rx: '', women_sc: '', ...e };
    });
}

// ── Exercise modal ────────────────────────────────────────────────────────────

function ModalRow({ name, added, onAction }) {
    return (
        <div className="flex items-center justify-between px-5 py-3.5 border-b border-white/5 last:border-0">
            <span className={`text-sm font-medium ${added ? 'text-gray-500' : 'text-white'}`}>{name}</span>
            <button
                type="button"
                onClick={onAction}
                className={[
                    'w-8 h-8 rounded-full flex items-center justify-center text-base font-bold transition-all shrink-0',
                    added
                        ? 'bg-green-500/20 text-green-400'
                        : 'bg-white/10 text-gray-400 hover:bg-orange-500 hover:text-white',
                ].join(' ')}
            >
                {added ? '✓' : '+'}
            </button>
        </div>
    );
}

function ExerciseModal({ exercises, onChange, onClose }) {
    const [mode, setMode]             = useState('list');   // 'list' | 'configure'
    const [search, setSearch]         = useState('');
    const [configuringIdx, setConfiguringIdx] = useState(null);
    const [draft, setDraft]           = useState({ volume: '', target: '', weight: '', rest: '' });

    const addedNames = new Set(exercises.map((e) => e.name));

    function openConfigure(idx) {
        setConfiguringIdx(idx);
        setDraft({ ...exercises[idx] });
        setMode('configure');
    }

    function handleRowAction(name) {
        if (addedNames.has(name)) {
            openConfigure(exercises.findIndex((e) => e.name === name));
        } else {
            const next = [...exercises, { name, volume: '', target: '', men_rx: '', men_sc: '', women_rx: '', women_sc: '', rest: '' }];
            onChange(next);
            setConfiguringIdx(next.length - 1);
            setDraft({ name, volume: '', target: '', men_rx: '', men_sc: '', women_rx: '', women_sc: '', rest: '' });
            setMode('configure');
        }
    }

    function removeSelected(idx) {
        onChange(exercises.filter((_, i) => i !== idx));
    }

    function backToList() {
        setMode('list');
        setConfiguringIdx(null);
    }

    function saveDraft() {
        onChange(exercises.map((e, i) => (i === configuringIdx ? { ...e, ...draft } : e)));
        backToList();
    }

    const filtered = search.trim()
        ? ALL_EXERCISES.filter((n) => n.toLowerCase().includes(search.toLowerCase()))
        : ALL_EXERCISES;
    const popular  = search.trim() ? [] : POPULAR_EXERCISES;
    const configEx = configuringIdx !== null ? exercises[configuringIdx] : null;

    return (
        <div className="fixed inset-0 z-50 flex items-end justify-center" style={{ background: 'rgba(0,0,0,0.6)' }}>
            <div
                className="w-full max-w-md flex flex-col rounded-t-3xl overflow-hidden"
                style={{ background: '#111827', maxHeight: '85vh' }}
            >
                {mode === 'list' ? (
                    <>
                        {/* Search header */}
                        <div className="flex items-center gap-3 px-4 py-3 border-b border-white/5">
                            <div className="flex-1 flex items-center gap-2 rounded-xl px-3 py-2" style={{ background: '#1f2937' }}>
                                <svg className="w-4 h-4 shrink-0" style={{ color: '#6b7280' }} fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                                <input
                                    autoFocus
                                    className="flex-1 bg-transparent text-sm focus:outline-none"
                                    style={{ color: 'white' }}
                                    placeholder="Search exercises..."
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                />
                            </div>
                            <button type="button" onClick={onClose} className="text-sm font-semibold shrink-0" style={{ color: '#f97316' }}>
                                Cancel
                            </button>
                        </div>

                        {/* Selected pills */}
                        {exercises.length > 0 && (
                            <div className="px-5 py-3 border-b border-white/5">
                                <p className="text-xs mb-2" style={{ color: '#6b7280' }}>Selected ({exercises.length})</p>
                                <div className="flex flex-wrap gap-2">
                                    {exercises.map((ex, idx) => (
                                        <span
                                            key={idx}
                                            className="flex items-center gap-1.5 text-xs px-3 py-1.5 rounded-full cursor-pointer"
                                            style={{ background: '#1f2937', color: 'white' }}
                                            onClick={() => openConfigure(idx)}
                                        >
                                            {ex.name}
                                            {ex.volume && (
                                                <span style={{ color: '#f97316' }}>{ex.volume}</span>
                                            )}
                                            <button
                                                type="button"
                                                onClick={(e) => { e.stopPropagation(); removeSelected(idx); }}
                                                className="font-bold leading-none"
                                                style={{ color: '#6b7280' }}
                                            >
                                                ×
                                            </button>
                                        </span>
                                    ))}
                                </div>
                            </div>
                        )}

                        {/* Exercise list */}
                        <div className="flex-1 overflow-y-auto">
                            {popular.length > 0 && (
                                <>
                                    <p className="px-5 pt-4 pb-2 text-xs font-bold uppercase tracking-widest" style={{ color: '#6b7280' }}>
                                        Popular
                                    </p>
                                    {popular.map((name) => (
                                        <ModalRow
                                            key={name}
                                            name={name}
                                            added={addedNames.has(name)}
                                            onAction={() => handleRowAction(name)}
                                        />
                                    ))}
                                    <p className="px-5 pt-4 pb-2 text-xs font-bold uppercase tracking-widest border-t mt-2" style={{ color: '#6b7280', borderColor: 'rgba(255,255,255,0.05)' }}>
                                        All Exercises
                                    </p>
                                </>
                            )}
                            {filtered.length > 0 ? (
                                filtered.map((name) => (
                                    <ModalRow
                                        key={name}
                                        name={name}
                                        added={addedNames.has(name)}
                                        onAction={() => handleRowAction(name)}
                                    />
                                ))
                            ) : (
                                <p className="px-5 py-10 text-sm text-center" style={{ color: '#6b7280' }}>
                                    No exercises match "{search}"
                                </p>
                            )}
                        </div>
                    </>
                ) : (
                    <>
                        {/* Configure header */}
                        <div className="flex items-center gap-2 px-4 py-3 border-b border-white/5">
                            <button
                                type="button"
                                onClick={backToList}
                                className="w-8 h-8 flex items-center justify-center rounded-full text-lg font-bold transition-colors"
                                style={{ background: '#1f2937', color: '#9ca3af' }}
                            >
                                ×
                            </button>
                            <button
                                type="button"
                                onClick={backToList}
                                className="px-4 py-1.5 rounded-full text-sm transition-colors"
                                style={{ color: '#9ca3af' }}
                            >
                                Add
                            </button>
                            <button
                                type="button"
                                className="px-4 py-1.5 rounded-full text-sm font-semibold"
                                style={{ background: '#f97316', color: 'white' }}
                            >
                                Configure
                            </button>
                        </div>

                        {/* Exercise name */}
                        <div className="px-5 py-5 border-b border-white/5">
                            <p className="text-xl font-bold" style={{ color: 'white' }}>
                                {configEx?.name} <span style={{ color: '#f97316' }}>*</span>
                            </p>
                        </div>

                        {/* Fields */}
                        <div className="flex-1 overflow-y-auto">
                            {[
                                { key: 'volume',   label: 'Volume',   required: true,  placeholder: 'e.g. 400m, 21 reps' },
                                { key: 'target',   label: 'Target',   required: false, placeholder: 'e.g. sub 2 min' },
                                { key: 'men_rx',   label: 'Men RX',   required: false, placeholder: 'e.g. 95 lb' },
                                { key: 'men_sc',   label: 'Men SC',   required: false, placeholder: 'e.g. 65 lb' },
                                { key: 'women_rx', label: 'Women RX', required: false, placeholder: 'e.g. 65 lb' },
                                { key: 'women_sc', label: 'Women SC', required: false, placeholder: 'e.g. 45 lb' },
                                { key: 'rest',     label: 'REST',     required: false, placeholder: 'e.g. 1 min' },
                            ].map(({ key, label, required, placeholder }) => (
                                <div
                                    key={key}
                                    className="flex items-center justify-between px-5 border-b"
                                    style={{ borderColor: 'rgba(255,255,255,0.05)', minHeight: 56 }}
                                >
                                    <span className="text-sm font-medium" style={{ color: 'white' }}>
                                        {label}
                                        {required && <span style={{ color: '#f97316' }}> *</span>}
                                    </span>
                                    <div className="flex items-center gap-2">
                                        {!draft[key] && (
                                            <span className="text-xs" style={{ color: required ? '#f97316' : '#6b7280' }}>
                                                {required ? 'Required' : 'Optional'}
                                            </span>
                                        )}
                                        <input
                                            autoFocus={key === 'volume'}
                                            className="text-right text-sm focus:outline-none w-32 bg-transparent"
                                            style={{ color: '#d1d5db' }}
                                            placeholder={placeholder}
                                            value={draft[key]}
                                            onChange={(e) => setDraft((d) => ({ ...d, [key]: e.target.value }))}
                                        />
                                    </div>
                                </div>
                            ))}
                        </div>

                        {/* Save bar */}
                        <div className="flex items-center justify-between px-5 py-4 border-t border-white/5">
                            <span className="text-sm" style={{ color: '#6b7280' }}>More</span>
                            <button
                                type="button"
                                onClick={saveDraft}
                                disabled={!draft.volume}
                                className="px-8 py-2.5 rounded-2xl text-sm font-semibold transition-all"
                                style={{
                                    background: draft.volume ? '#1f2937' : '#111827',
                                    color: draft.volume ? 'white' : '#4b5563',
                                    border: '1px solid rgba(255,255,255,0.1)',
                                }}
                            >
                                Save
                            </button>
                        </div>
                    </>
                )}
            </div>
        </div>
    );
}

// ── Exercise builder (main form section) ──────────────────────────────────────

function ExerciseBuilder({ exercises, onChange, showModal, setShowModal }) {
    const [expandedIdx, setExpandedIdx]   = useState(null);

    function removeExercise(idx) {
        onChange(exercises.filter((_, i) => i !== idx));
        setExpandedIdx((prev) => {
            if (prev === idx) return null;
            if (prev > idx) return prev - 1;
            return prev;
        });
    }

    function updateField(idx, field, value) {
        onChange(exercises.map((e, i) => (i === idx ? { ...e, [field]: value } : e)));
    }

    return (
        <>
            <div>
                <div className="flex items-center justify-between mb-2">
                    <label className="text-xs font-semibold text-gray-500 uppercase">WOD Exercises</label>
                    <button
                        type="button"
                        onClick={() => setShowModal(true)}
                        className="px-3 py-1 rounded-full text-xs font-semibold border bg-orange-50 text-orange-600 border-orange-200 hover:bg-orange-100 transition-all"
                    >
                        + Add Exercise
                    </button>
                </div>

                {/* Empty state */}
                {exercises.length === 0 && (
                    <div
                        className="text-center py-8 border-2 border-dashed border-gray-100 rounded-2xl cursor-pointer hover:border-orange-200 transition-colors"
                        onClick={() => setShowModal(true)}
                    >
                        <p className="text-sm font-medium text-gray-400">No exercises added</p>
                        <p className="text-xs text-gray-300 mt-0.5">Tap to start building the WOD</p>
                    </div>
                )}

                {/* Exercise cards */}
                {exercises.length > 0 && (
                    <div className="space-y-2">
                        {exercises.map((ex, idx) => (
                            <div key={idx} className="border border-gray-100 rounded-2xl overflow-hidden">
                                <div
                                    className="flex items-center justify-between px-4 py-3 bg-gray-50 cursor-pointer select-none"
                                    onClick={() => setExpandedIdx(expandedIdx === idx ? null : idx)}
                                >
                                    <div className="flex items-center gap-2 min-w-0">
                                        <span className="text-sm font-semibold text-gray-900">{ex.name}</span>
                                        {ex.volume ? (
                                            <span className="text-xs text-gray-500 bg-white border border-gray-100 px-2 py-0.5 rounded-full shrink-0">
                                                {ex.volume}
                                            </span>
                                        ) : (
                                            <span className="text-xs text-orange-400 shrink-0">Set volume</span>
                                        )}
                                        {(ex.men_rx || ex.women_rx) && (
                                            <span className="text-xs text-blue-500 bg-blue-50 border border-blue-100 px-2 py-0.5 rounded-full shrink-0">
                                                {[ex.men_rx && `M:${ex.men_rx}`, ex.women_rx && `W:${ex.women_rx}`].filter(Boolean).join(' · ')}
                                            </span>
                                        )}
                                    </div>
                                    <div className="flex items-center gap-2 shrink-0">
                                        <span className="text-gray-400 text-xs">{expandedIdx === idx ? '▲' : '▼'}</span>
                                        <button
                                            type="button"
                                            onClick={(e) => { e.stopPropagation(); removeExercise(idx); }}
                                            className="w-5 h-5 flex items-center justify-center text-gray-300 hover:text-red-400 font-bold text-lg leading-none transition-colors"
                                        >
                                            ×
                                        </button>
                                    </div>
                                </div>

                                {expandedIdx === idx && (
                                    <div className="px-4 py-3 grid grid-cols-2 gap-3 bg-white border-t border-gray-50">
                                        {[
                                            { key: 'volume',   label: 'Volume *',  placeholder: 'e.g. 21 reps, 400m' },
                                            { key: 'target',   label: 'Target',    placeholder: 'e.g. sub 2 min' },
                                            { key: 'men_rx',   label: 'Men RX',    placeholder: 'e.g. 95 lb' },
                                            { key: 'men_sc',   label: 'Men SC',    placeholder: 'e.g. 65 lb' },
                                            { key: 'women_rx', label: 'Women RX',  placeholder: 'e.g. 65 lb' },
                                            { key: 'women_sc', label: 'Women SC',  placeholder: 'e.g. 45 lb' },
                                            { key: 'rest',     label: 'Rest',      placeholder: 'e.g. 1 min' },
                                        ].map(({ key, label, placeholder }) => (
                                            <div key={key}>
                                                <label className="text-[10px] font-bold uppercase tracking-wide text-gray-400">{label}</label>
                                                <input
                                                    autoFocus={key === 'volume' && !ex.volume}
                                                    className="mt-1 w-full border rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-orange-300"
                                                    placeholder={placeholder}
                                                    value={ex[key]}
                                                    onChange={(e) => updateField(idx, key, e.target.value)}
                                                />
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </div>
                        ))}
                    </div>
                )}
            </div>

            {showModal && (
                <ExerciseModal
                    exercises={exercises}
                    onChange={onChange}
                    onClose={() => setShowModal(false)}
                />
            )}
        </>
    );
}

// ── Edit panel ────────────────────────────────────────────────────────────────

function EditPanel({ gymClass, template }) {
    const [showExerciseModal, setShowExerciseModal] = useState(false);
    const form = useForm({
        name:         gymClass.name,
        coach:        gymClass.coach,
        start_time:   toLocalInputDT(gymClass.start_time),
        capacity:     gymClass.capacity,
        exercises:    normalizeExercises(gymClass.exercises ?? []),
        wod_type:   gymClass.wod_type ?? null,
        wod_config: gymClass.wod_config ?? {},
        is_cancelled: gymClass.is_cancelled ?? false,
    });

    function submit(e) {
        e.preventDefault();
        form.patch(route('admin.classes.update', gymClass.id), {
            preserveScroll: true,
            onSuccess: () => router.get(route('admin.classes.slot'), {
                template_id: template.id, id: gymClass.id,
            }, { preserveScroll: true }),
        });
    }

    function deleteClass() {
        if (!confirm('Delete this class instance? All bookings will be removed.')) return;
        router.delete(route('admin.classes.destroy', gymClass.id), {
            onSuccess: () => router.get(route('admin.classes.slot'), { template_id: template.id }),
        });
    }

    return (
        <div className="flex-1 flex flex-col gap-4">
            <div className="bg-white rounded-2xl border border-gray-100 p-6">
                <div className="flex items-start justify-between mb-6">
                    <div>
                        <h2 className="text-lg font-bold text-gray-900">{formatDate(gymClass.start_time)}</h2>
                        <p className="text-sm text-gray-400 mt-0.5">{gymClass.name} · {gymClass.coach}</p>
                    </div>
                    <button onClick={deleteClass}
                        className="text-xs text-red-400 hover:text-red-600 font-medium border border-red-200 px-3 py-1.5 rounded-xl transition-colors">
                        Delete
                    </button>
                </div>

                <div className={`flex items-center justify-between p-4 rounded-2xl mb-6 ${form.data.is_cancelled ? 'bg-red-50 border border-red-100' : 'bg-green-50 border border-green-100'}`}>
                    <div>
                        <p className={`text-sm font-bold ${form.data.is_cancelled ? 'text-red-600' : 'text-green-700'}`}>
                            {form.data.is_cancelled ? '🔴 Class Closed' : '🟢 Class Open'}
                        </p>
                        <p className="text-xs text-gray-500 mt-0.5">
                            {form.data.is_cancelled ? 'This session is cancelled — members cannot book.' : 'This session is active.'}
                        </p>
                    </div>
                    <button
                        type="button"
                        onClick={() => form.setData('is_cancelled', !form.data.is_cancelled)}
                        className={[
                            'relative w-12 h-6 rounded-full transition-colors duration-200',
                            form.data.is_cancelled ? 'bg-red-400' : 'bg-green-400',
                        ].join(' ')}
                    >
                        <span className={[
                            'absolute top-0.5 w-5 h-5 bg-white rounded-full shadow transition-transform duration-200',
                            form.data.is_cancelled ? 'translate-x-6' : 'translate-x-0.5',
                        ].join(' ')} />
                    </button>
                </div>

                <form onSubmit={submit} className="space-y-4">
                    <div className="grid grid-cols-2 gap-3">
                        <div>
                            <label className="text-xs font-semibold text-gray-500 uppercase">Class Name</label>
                            <input
                                className="mt-1 w-full border rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-orange-300"
                                value={form.data.name}
                                onChange={(e) => form.setData('name', e.target.value)}
                                required
                            />
                        </div>
                        <div>
                            <label className="text-xs font-semibold text-gray-500 uppercase">Coach</label>
                            <input
                                className="mt-1 w-full border rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-orange-300"
                                value={form.data.coach}
                                onChange={(e) => form.setData('coach', e.target.value)}
                                required
                            />
                        </div>
                        <div>
                            <label className="text-xs font-semibold text-gray-500 uppercase">Capacity</label>
                            <input
                                type="number" min="1" max="100"
                                className="mt-1 w-full border rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-orange-300"
                                value={form.data.capacity}
                                onChange={(e) => form.setData('capacity', parseInt(e.target.value))}
                                required
                            />
                        </div>
                        <div>
                            <label className="text-xs font-semibold text-gray-500 uppercase">Start Time</label>
                            <input
                                type="datetime-local"
                                className="mt-1 w-full border rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-orange-300"
                                value={form.data.start_time}
                                onChange={(e) => form.setData('start_time', e.target.value)}
                                required
                            />
                        </div>
                    </div>

                    <WodTypeSelector
                        value={form.data.wod_type}
                        onChange={(t) => { form.setData('wod_type', t); form.setData('wod_config', {}); }}
                        config={form.data.wod_config}
                        onConfigChange={(c) => form.setData('wod_config', c)}
                        onNext={() => setShowExerciseModal(true)}
                    />

                    <ExerciseBuilder
                        exercises={form.data.exercises}
                        onChange={(ex) => form.setData('exercises', ex)}
                        showModal={showExerciseModal}
                        setShowModal={setShowExerciseModal}
                    />

                    <div className="flex gap-2 pt-2">
                        <button type="button"
                            onClick={() => router.get(route('admin.classes.index'))}
                            className="px-4 py-2 rounded-xl border text-sm text-gray-600 hover:bg-gray-50">
                            ← Back to Classes
                        </button>
                        <button type="submit" disabled={form.processing}
                            className="flex-1 py-2 rounded-xl bg-orange-500 text-white text-sm font-semibold disabled:opacity-60">
                            {form.processing ? 'Saving…' : 'Save Changes'}
                        </button>
                    </div>
                </form>
            </div>

            <div className="bg-white rounded-2xl border border-gray-100 overflow-hidden">
                <div className="flex items-center justify-between px-4 py-2.5 bg-gray-50 border-b border-gray-100">
                    <span className="text-xs font-bold uppercase tracking-wide text-gray-500">Booked</span>
                    <span className="text-xs font-bold text-gray-700">{gymClass.attendees?.length ?? 0} / {gymClass.capacity}</span>
                </div>
                {gymClass.attendees?.length > 0 ? (
                    <ul className="divide-y divide-gray-50">
                        {gymClass.attendees.map((a) => (
                            <li key={a.id} className="flex items-center gap-3 px-4 py-2.5">
                                <div className="w-7 h-7 rounded-full bg-orange-100 text-orange-600 text-xs font-bold flex items-center justify-center shrink-0">
                                    {a.name[0].toUpperCase()}
                                </div>
                                <div className="min-w-0">
                                    <p className="text-sm font-medium text-gray-900 truncate">{a.name}</p>
                                    <p className="text-xs text-gray-400 truncate">{a.email}</p>
                                </div>
                            </li>
                        ))}
                    </ul>
                ) : (
                    <p className="px-4 py-4 text-xs text-gray-400 text-center">No bookings yet</p>
                )}
            </div>
        </div>
    );
}

// ── Page ─────────────────────────────────────────────────────────────────────

export default function ClassSlot({ instances, selected, template }) {
    return (
        <AdminLayout title={`${template.name} · ${DAY_NAMES[template.day_of_week]} ${template.start_time}`}>
            <div className="flex gap-4 items-start">
                <DateList instances={instances} selectedId={selected?.id} template={template} />

                {selected ? (
                    <EditPanel gymClass={selected} template={template} />
                ) : (
                    <div className="flex-1 bg-white rounded-2xl border border-gray-100 flex items-center justify-center py-24 text-gray-400">
                        <p className="text-sm">No instances found for this slot.</p>
                    </div>
                )}
            </div>
        </AdminLayout>
    );
}
