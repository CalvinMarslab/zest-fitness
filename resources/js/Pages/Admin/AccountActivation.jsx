import { useState } from 'react';
import { router, useForm } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';

const STATUS_BADGE = {
    pending:   'bg-gray-100 text-gray-600',
    sent:      'bg-blue-100 text-blue-700',
    failed:    'bg-red-100 text-red-700',
    activated: 'bg-green-100 text-green-700',
};

function StatCard({ label, value, accent }) {
    return (
        <div className={`rounded-2xl p-5 border ${accent ?? 'bg-white border-gray-200'}`}>
            <p className="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">{label}</p>
            <p className="text-3xl font-black text-gray-900">{value}</p>
        </div>
    );
}

function Flash({ flash }) {
    if (!flash?.success && !flash?.error) return null;
    const isError = !!flash.error;
    return (
        <div className={`rounded-xl px-4 py-3 text-sm font-medium mb-6 ${isError ? 'bg-red-50 text-red-700 border border-red-200' : 'bg-green-50 text-green-700 border border-green-200'}`}>
            {flash.success ?? flash.error}
        </div>
    );
}

export default function AccountActivation({ stats, members, flash }) {
    const [filterStatus, setFilterStatus] = useState('all');
    const [confirmBatch, setConfirmBatch] = useState(false);

    const testForm = useForm({ email: '' });
    const batchForm = useForm({});
    const retryForm = useForm({});

    const filtered = filterStatus === 'all'
        ? members
        : members.filter((m) => m.status === filterStatus);

    function submitTest(e) {
        e.preventDefault();
        testForm.post(route('admin.activation.test'), { preserveScroll: true });
    }

    function submitBatch() {
        setConfirmBatch(false);
        batchForm.post(route('admin.activation.batch'), { preserveScroll: true });
    }

    function submitRetry() {
        retryForm.post(route('admin.activation.retry'), { preserveScroll: true });
    }

    return (
        <AdminLayout title="Account Activation">
            <div className="max-w-5xl mx-auto space-y-8">

                <Flash flash={flash} />

                {/* Stats */}
                <div className="grid grid-cols-2 sm:grid-cols-5 gap-4">
                    <StatCard label="Eligible" value={stats.total_eligible} />
                    <StatCard label="Pending" value={stats.pending} />
                    <StatCard label="Sent" value={stats.sent} accent="bg-blue-50 border-blue-200" />
                    <StatCard label="Failed" value={stats.failed} accent="bg-red-50 border-red-200" />
                    <StatCard label="Activated" value={stats.activated} accent="bg-green-50 border-green-200" />
                </div>

                {/* Actions */}
                <div className="grid sm:grid-cols-2 gap-6">

                    {/* Test email */}
                    <div className="bg-white rounded-2xl border border-gray-200 p-6">
                        <h2 className="font-bold text-gray-900 mb-1">Send Test Email</h2>
                        <p className="text-xs text-gray-500 mb-4">Delivers a preview to an admin address before batch sending.</p>
                        <form onSubmit={submitTest} className="flex gap-2">
                            <input
                                type="email"
                                placeholder="admin@example.com"
                                value={testForm.data.email}
                                onChange={(e) => testForm.setData('email', e.target.value)}
                                className="flex-1 border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-orange-300"
                                required
                            />
                            <button
                                type="submit"
                                disabled={testForm.processing}
                                className="px-4 py-2 rounded-xl bg-gray-900 text-white text-sm font-semibold hover:bg-gray-700 disabled:opacity-50 whitespace-nowrap"
                            >
                                {testForm.processing ? 'Sending…' : 'Send Test'}
                            </button>
                        </form>
                        {testForm.errors.email && (
                            <p className="text-xs text-red-500 mt-1">{testForm.errors.email}</p>
                        )}
                    </div>

                    {/* Batch controls */}
                    <div className="bg-white rounded-2xl border border-gray-200 p-6 flex flex-col gap-3">
                        <h2 className="font-bold text-gray-900 mb-1">Batch Actions</h2>

                        {confirmBatch ? (
                            <div className="rounded-xl bg-orange-50 border border-orange-200 p-4">
                                <p className="text-sm text-orange-800 font-medium mb-3">
                                    Send to all <strong>{stats.pending}</strong> pending/failed members?
                                    Emails will be queued immediately.
                                </p>
                                <div className="flex gap-2">
                                    <button
                                        onClick={submitBatch}
                                        disabled={batchForm.processing}
                                        className="flex-1 py-2 rounded-xl bg-orange-500 text-white text-sm font-semibold hover:bg-orange-600 disabled:opacity-50"
                                    >
                                        {batchForm.processing ? 'Queuing…' : 'Confirm Send'}
                                    </button>
                                    <button
                                        onClick={() => setConfirmBatch(false)}
                                        className="flex-1 py-2 rounded-xl bg-white border border-gray-200 text-sm font-semibold text-gray-600 hover:bg-gray-50"
                                    >
                                        Cancel
                                    </button>
                                </div>
                            </div>
                        ) : (
                            <button
                                onClick={() => setConfirmBatch(true)}
                                disabled={stats.pending === 0}
                                className="w-full py-2.5 rounded-xl bg-orange-500 text-white text-sm font-semibold hover:bg-orange-600 disabled:opacity-40"
                            >
                                Send Batch ({stats.pending} pending)
                            </button>
                        )}

                        <button
                            onClick={submitRetry}
                            disabled={retryForm.processing || stats.failed === 0}
                            className="w-full py-2.5 rounded-xl border border-red-200 text-red-600 text-sm font-semibold hover:bg-red-50 disabled:opacity-40"
                        >
                            {retryForm.processing ? 'Queuing…' : `Retry Failures (${stats.failed})`}
                        </button>
                    </div>
                </div>

                {/* Member list */}
                <div className="bg-white rounded-2xl border border-gray-200 overflow-hidden">
                    <div className="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                        <h2 className="font-bold text-gray-900">Members ({filtered.length})</h2>
                        <div className="flex gap-1">
                            {['all', 'pending', 'sent', 'failed', 'activated'].map((s) => (
                                <button
                                    key={s}
                                    onClick={() => setFilterStatus(s)}
                                    className={`px-3 py-1 rounded-lg text-xs font-semibold capitalize transition-colors ${
                                        filterStatus === s
                                            ? 'bg-gray-900 text-white'
                                            : 'text-gray-500 hover:bg-gray-100'
                                    }`}
                                >
                                    {s}
                                </button>
                            ))}
                        </div>
                    </div>

                    <div className="divide-y divide-gray-50 max-h-[480px] overflow-y-auto">
                        {filtered.length === 0 && (
                            <p className="px-6 py-10 text-center text-sm text-gray-400">No members in this group.</p>
                        )}
                        {filtered.map((m) => (
                            <div key={m.id} className="flex items-center gap-4 px-6 py-3 hover:bg-gray-50">
                                <div className="flex-1 min-w-0">
                                    <p className="text-sm font-semibold text-gray-900 truncate">{m.name}</p>
                                    <p className="text-xs text-gray-400 truncate">{m.email}</p>
                                </div>
                                <div className="text-right shrink-0">
                                    <span className={`inline-block px-2.5 py-0.5 rounded-full text-xs font-semibold capitalize ${STATUS_BADGE[m.status] ?? STATUS_BADGE.pending}`}>
                                        {m.status}
                                    </span>
                                    {m.sent_at && (
                                        <p className="text-xs text-gray-400 mt-0.5">Sent {m.sent_at.slice(0, 10)}</p>
                                    )}
                                    {m.activated_at && (
                                        <p className="text-xs text-green-600 mt-0.5">Activated {m.activated_at.slice(0, 10)}</p>
                                    )}
                                    {m.status === 'failed' && m.error_message && (
                                        <p className="text-xs text-red-500 mt-0.5 max-w-[200px] truncate" title={m.error_message}>
                                            {m.error_message}
                                        </p>
                                    )}
                                </div>
                            </div>
                        ))}
                    </div>
                </div>
            </div>
        </AdminLayout>
    );
}
