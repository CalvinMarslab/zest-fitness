import { useState } from 'react';
import { router, useForm } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';

// ─── Edit modal ───────────────────────────────────────────────────────────────

function EditUserModal({ user, onClose }) {
    const form = useForm({
        name:     user.name,
        credits:  user.credits,
        is_admin: user.is_admin,
    });

    function submit(e) {
        e.preventDefault();
        form.patch(route('admin.users.update', user.id), { onSuccess: onClose });
    }

    return (
        <div className="fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4" onClick={onClose}>
            <div className="bg-white rounded-2xl p-6 w-full max-w-md shadow-2xl" onClick={(e) => e.stopPropagation()}>
                <h2 className="font-bold text-lg mb-4">Edit — {user.name}</h2>
                <form onSubmit={submit} className="flex flex-col gap-3">
                    <div>
                        <label className="text-xs font-semibold text-gray-500 uppercase">Name</label>
                        <input
                            className="mt-1 w-full border rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-orange-300"
                            value={form.data.name}
                            onChange={(e) => form.setData('name', e.target.value)}
                        />
                    </div>
                    <div>
                        <label className="text-xs font-semibold text-gray-500 uppercase">Credits</label>
                        <input
                            type="number" min="0"
                            className="mt-1 w-full border rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-orange-300"
                            value={form.data.credits}
                            onChange={(e) => form.setData('credits', parseInt(e.target.value))}
                        />
                    </div>
                    <label className="flex items-center gap-2 text-sm cursor-pointer">
                        <input
                            type="checkbox"
                            checked={form.data.is_admin}
                            onChange={(e) => form.setData('is_admin', e.target.checked)}
                            className="accent-orange-500"
                        />
                        Admin access
                    </label>
                    <div className="flex gap-2 mt-2">
                        <button type="button" onClick={onClose}
                            className="flex-1 py-2 rounded-xl border text-sm text-gray-600">Cancel</button>
                        <button type="submit" disabled={form.processing}
                            className="flex-1 py-2 rounded-xl bg-orange-500 text-white text-sm font-semibold disabled:opacity-60">
                            Save
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}

// ─── Stat card ────────────────────────────────────────────────────────────────

function StatCard({ label, value, color }) {
    const palette = {
        gray:  { bg: 'bg-gray-50',  label: 'text-gray-600',  val: 'text-gray-900'  },
        green: { bg: 'bg-green-50', label: 'text-green-600', val: 'text-green-700' },
        amber: { bg: 'bg-amber-50', label: 'text-amber-600', val: 'text-amber-700' },
        red:   { bg: 'bg-red-50',   label: 'text-red-500',   val: 'text-red-600'   },
    };
    const c = palette[color] ?? palette.gray;
    return (
        <div className={`${c.bg} rounded-2xl px-5 py-4 flex flex-col gap-1`}>
            <span className={`text-xs font-semibold uppercase tracking-wide ${c.label}`}>{label}</span>
            <span className={`text-3xl font-bold ${c.val}`}>{value}</span>
        </div>
    );
}

// ─── Tab button ───────────────────────────────────────────────────────────────

function Tab({ active, onClick, children, count }) {
    return (
        <button
            onClick={onClick}
            className={[
                'flex items-center gap-2 px-4 py-2.5 text-sm font-semibold border-b-2 transition-colors',
                active
                    ? 'border-orange-500 text-orange-600'
                    : 'border-transparent text-gray-500 hover:text-gray-700',
            ].join(' ')}
        >
            {children}
            <span className={[
                'text-xs font-bold px-2 py-0.5 rounded-full',
                active ? 'bg-orange-100 text-orange-600' : 'bg-gray-100 text-gray-500',
            ].join(' ')}>
                {count}
            </span>
        </button>
    );
}

// ─── Shared avatar cell ───────────────────────────────────────────────────────

function Avatar({ name, color = 'orange' }) {
    const styles = {
        orange: 'bg-orange-100 text-orange-600',
        purple: 'bg-purple-100 text-purple-600',
    };
    return (
        <div className={`w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold shrink-0 ${styles[color]}`}>
            {name[0].toUpperCase()}
        </div>
    );
}

// ─── Management Team table ────────────────────────────────────────────────────

function TeamTable({ team, onEdit, onDelete }) {
    return (
        <div className="bg-white rounded-2xl border border-gray-100 overflow-hidden">
            <div className="overflow-x-auto">
            <table className="w-full text-sm min-w-[480px]">
                <thead className="bg-gray-50 border-b border-gray-100">
                    <tr>
                        {['Name', 'Email', 'Joined', ''].map((h) => (
                            <th key={h} className="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">{h}</th>
                        ))}
                    </tr>
                </thead>
                <tbody className="divide-y divide-gray-50">
                    {team.length === 0 && (
                        <tr>
                            <td colSpan={4} className="px-4 py-8 text-center text-sm text-gray-400">No admin users yet.</td>
                        </tr>
                    )}
                    {team.map((u) => (
                        <tr key={u.id} className="hover:bg-gray-50 transition-colors">
                            <td className="px-4 py-3 font-medium text-gray-900">
                                <div className="flex items-center gap-2">
                                    <Avatar name={u.name} color="purple" />
                                    <div>
                                        <p>{u.name}</p>
                                        <span className="text-[10px] font-semibold text-purple-500 bg-purple-50 px-1.5 py-0.5 rounded-full">Admin</span>
                                    </div>
                                </div>
                            </td>
                            <td className="px-4 py-3 text-gray-500">{u.email}</td>
                            <td className="px-4 py-3 text-gray-400 text-xs">
                                {new Date(u.created_at).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' })}
                            </td>
                            <td className="px-4 py-3">
                                <div className="flex items-center gap-2">
                                    <button onClick={() => onEdit(u)}
                                        className="text-xs text-orange-500 hover:text-orange-700 font-medium">Edit</button>
                                    <button onClick={() => onDelete(u)}
                                        className="text-xs text-red-400 hover:text-red-600 font-medium">Delete</button>
                                </div>
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
            </div>
        </div>
    );
}

// ─── Members table ────────────────────────────────────────────────────────────

function MembersTable({ customers, onEdit, onDelete }) {
    return (
        <div className="bg-white rounded-2xl border border-gray-100 overflow-hidden">
            <div className="overflow-x-auto">
            <table className="w-full text-sm min-w-[580px]">
                <thead className="bg-gray-50 border-b border-gray-100">
                    <tr>
                        {['Name', 'Email', 'Credits', 'Bookings', 'Activities', ''].map((h) => (
                            <th key={h} className="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">{h}</th>
                        ))}
                    </tr>
                </thead>
                <tbody className="divide-y divide-gray-50">
                    {customers.length === 0 && (
                        <tr>
                            <td colSpan={6} className="px-4 py-8 text-center text-sm text-gray-400">No members yet.</td>
                        </tr>
                    )}
                    {customers.map((u) => (
                        <tr key={u.id} className="hover:bg-gray-50 transition-colors">
                            <td className="px-4 py-3 font-medium text-gray-900">
                                <div className="flex items-center gap-2">
                                    <Avatar name={u.name} color="orange" />
                                    {u.name}
                                </div>
                            </td>
                            <td className="px-4 py-3 text-gray-500">{u.email}</td>
                            <td className="px-4 py-3">
                                <span className="bg-orange-50 text-orange-600 px-2 py-0.5 rounded-full text-xs font-semibold">
                                    {u.credits} credits
                                </span>
                            </td>
                            <td className="px-4 py-3 text-gray-500">{u.bookings_count}</td>
                            <td className="px-4 py-3 text-gray-500">{u.activities_count}</td>
                            <td className="px-4 py-3">
                                <div className="flex items-center gap-2">
                                    <button onClick={() => onEdit(u)}
                                        className="text-xs text-orange-500 hover:text-orange-700 font-medium">Edit</button>
                                    <button onClick={() => onDelete(u)}
                                        className="text-xs text-red-400 hover:text-red-600 font-medium">Delete</button>
                                </div>
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
            </div>
        </div>
    );
}

// ─── Page ─────────────────────────────────────────────────────────────────────

export default function Users({ team, customers, stats }) {
    const [tab, setTab]       = useState('customers');
    const [editing, setEditing] = useState(null);

    function deleteUser(user) {
        if (!confirm(`Delete ${user.name}? This cannot be undone.`)) return;
        router.delete(route('admin.users.destroy', user.id));
    }

    return (
        <AdminLayout title="Users">

            {/* ── Tabs ── */}
            <div className="flex border-b border-gray-200 mb-6">
                <Tab active={tab === 'customers'} onClick={() => setTab('customers')} count={customers.length}>
                    Members
                </Tab>
                <Tab active={tab === 'team'} onClick={() => setTab('team')} count={team.length}>
                    Management Team
                </Tab>
            </div>

            {/* ── Members tab ── */}
            {tab === 'customers' && (
                <>
                    <div className="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
                        <StatCard label="Total Members"        value={stats.total}         color="gray"  />
                        <StatCard label="Active"               value={stats.active}        color="green" />
                        <StatCard label="Expiring (3 months)"  value={stats.expiring_soon} color="amber" />
                        <StatCard label="Expired"              value={stats.expired}       color="red"   />
                    </div>
                    <MembersTable customers={customers} onEdit={setEditing} onDelete={deleteUser} />
                </>
            )}

            {/* ── Management Team tab ── */}
            {tab === 'team' && (
                <TeamTable team={team} onEdit={setEditing} onDelete={deleteUser} />
            )}

            {editing && <EditUserModal user={editing} onClose={() => setEditing(null)} />}
        </AdminLayout>
    );
}
