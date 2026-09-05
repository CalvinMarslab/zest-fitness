import { useState } from 'react';
import { router, useForm } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';

const PERIOD_OPTIONS = [
    { label: '7 Days (Trial)',  days: 7   },
    { label: '14 Days (Trial)', days: 14  },
    { label: '1 Month',        days: 30  },
    { label: '2 Months',       days: 60  },
    { label: '3 Months',       days: 90  },
    { label: '6 Months',       days: 180 },
    { label: '12 Months',      days: 365 },
];

const UNLIMITED = 999;

function PackageForm({ initial = {}, onSubmit, onCancel }) {
    const isUnlimitedInit = !!(initial.is_unlimited) || (initial.credits ?? 10) >= UNLIMITED;
    const [unlimited, setUnlimited] = useState(isUnlimitedInit);

    const form = useForm({
        name:         initial.name         ?? '',
        description:  initial.description  ?? '',
        credits:      isUnlimitedInit ? UNLIMITED : (initial.credits ?? 10),
        period_days:  initial.period_days  ?? 30,
        price:        initial.price        ?? '',
        badge:        initial.badge        ?? '',
        is_active:    initial.is_active    ?? true,
        is_unlimited: isUnlimitedInit,
        sort_order:   initial.sort_order   ?? 0,
    });

    function toggleUnlimited(checked) {
        setUnlimited(checked);
        form.setData('credits', checked ? UNLIMITED : 10);
        form.setData('is_unlimited', checked);
    }

    function submit(e) {
        e.preventDefault();
        if (parseFloat(form.data.price) < 0) return alert('Price cannot be negative.');
        if (!unlimited && form.data.credits < 1) return alert('Credits must be at least 1.');
        onSubmit(form);
    }

    return (
        <form onSubmit={submit} className="grid grid-cols-2 gap-3">
            {/* Name */}
            <div>
                <label className="text-xs font-semibold text-gray-500 uppercase">Package Name</label>
                <input className="mt-1 w-full border rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-orange-300"
                    placeholder="e.g. Starter, Pro, Elite"
                    value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} required />
            </div>

            {/* Price */}
            <div>
                <label className="text-xs font-semibold text-gray-500 uppercase">Price (MYR)</label>
                <input type="number" min="0" step="0.01"
                    className="mt-1 w-full border rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-orange-300"
                    placeholder="0.00"
                    value={form.data.price} onChange={(e) => form.setData('price', e.target.value)} required />
            </div>

            {/* Credits */}
            <div>
                <label className="text-xs font-semibold text-gray-500 uppercase">Credits</label>
                <div className="flex items-center gap-2 mt-1">
                    <input type="number" min="1" disabled={unlimited}
                        className="w-full border rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-orange-300 disabled:bg-gray-50 disabled:text-gray-400"
                        value={unlimited ? '∞' : form.data.credits}
                        onChange={(e) => form.setData('credits', parseInt(e.target.value))} />
                </div>
                <label className="flex items-center gap-1.5 text-xs text-gray-500 mt-1.5 cursor-pointer">
                    <input type="checkbox" checked={unlimited} onChange={(e) => toggleUnlimited(e.target.checked)}
                        className="accent-orange-500" />
                    Unlimited access
                </label>
            </div>

            {/* Period */}
            <div>
                <label className="text-xs font-semibold text-gray-500 uppercase">Validity Period</label>
                <select className="mt-1 w-full border rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-orange-300 bg-white"
                    value={form.data.period_days} onChange={(e) => form.setData('period_days', parseInt(e.target.value))}>
                    {PERIOD_OPTIONS.map((o) => (
                        <option key={o.days} value={o.days}>{o.label}</option>
                    ))}
                </select>
            </div>

            {/* Badge */}
            <div>
                <label className="text-xs font-semibold text-gray-500 uppercase">Badge <span className="normal-case font-normal text-gray-400">(optional)</span></label>
                <input className="mt-1 w-full border rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-orange-300"
                    placeholder="e.g. Most Popular, Best Value"
                    value={form.data.badge} onChange={(e) => form.setData('badge', e.target.value)} />
            </div>

            {/* Sort order */}
            <div>
                <label className="text-xs font-semibold text-gray-500 uppercase">Sort Order</label>
                <input type="number" min="0"
                    className="mt-1 w-full border rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-orange-300"
                    value={form.data.sort_order} onChange={(e) => form.setData('sort_order', parseInt(e.target.value))} />
            </div>

            {/* Description */}
            <div className="col-span-2">
                <label className="text-xs font-semibold text-gray-500 uppercase">Description <span className="normal-case font-normal text-gray-400">(optional)</span></label>
                <textarea rows={2}
                    className="mt-1 w-full border rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-orange-300 resize-none"
                    placeholder="Short description shown on the pricing page"
                    value={form.data.description} onChange={(e) => form.setData('description', e.target.value)} />
            </div>

            {/* Active toggle */}
            <div className="col-span-2">
                <label className="flex items-center gap-2 text-sm cursor-pointer">
                    <input type="checkbox" checked={form.data.is_active}
                        onChange={(e) => form.setData('is_active', e.target.checked)}
                        className="accent-orange-500" />
                    <span className="font-medium">Active (visible to members)</span>
                </label>
            </div>

            <div className="col-span-2 flex gap-2 mt-1">
                {onCancel && (
                    <button type="button" onClick={onCancel}
                        className="flex-1 py-2 rounded-xl border text-sm text-gray-600">Cancel</button>
                )}
                <button type="submit" disabled={form.processing}
                    className="flex-1 py-2 rounded-xl bg-orange-500 text-white text-sm font-semibold disabled:opacity-60">
                    {form.processing ? 'Saving…' : initial.id ? 'Update Package' : 'Create Package'}
                </button>
            </div>
        </form>
    );
}

function EditModal({ pkg, onClose }) {
    function submit(form) {
        form.patch(route('admin.packages.update', pkg.id), { onSuccess: onClose });
    }
    return (
        <div className="fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4" onClick={onClose}>
            <div className="bg-white rounded-2xl p-6 w-full max-w-lg shadow-2xl" onClick={(e) => e.stopPropagation()}>
                <h2 className="font-bold text-lg mb-4">Edit Package</h2>
                <PackageForm initial={pkg} onSubmit={submit} onCancel={onClose} />
            </div>
        </div>
    );
}

function periodLabel(days) {
    if (days === 7)  return '7 Days';
    if (days === 14) return '14 Days';
    const months = Math.round(days / 30);
    if (months === 1)  return '1 Month';
    if (months < 12)   return `${months} Months`;
    return months === 12 ? '1 Year' : `${Math.round(months / 12)} Years`;
}

export default function Packages({ packages }) {
    const [showForm, setShowForm] = useState(false);
    const [editing,  setEditing]  = useState(null);

    function createSubmit(form) {
        form.post(route('admin.packages.store'), { onSuccess: () => setShowForm(false) });
    }

    function deletePackage(pkg) {
        const hasSubscribers = pkg.subscriptions_count > 0;
        const msg = hasSubscribers
            ? `Delete "${pkg.name}"?\n\nWarning: ${pkg.subscriptions_count} active subscription${pkg.subscriptions_count !== 1 ? 's' : ''} will be affected. Members will keep their current credits but cannot renew this plan.`
            : `Delete "${pkg.name}"? This cannot be undone.`;
        if (!confirm(msg)) return;
        router.delete(route('admin.packages.destroy', pkg.id));
    }

    return (
        <AdminLayout title="Packages">
            {/* Add form */}
            <div className="bg-white rounded-2xl border border-gray-100 p-5 mb-6">
                <div className="flex items-center justify-between mb-4">
                    <h2 className="font-semibold text-gray-900">Add New Package</h2>
                    <button onClick={() => setShowForm(!showForm)}
                        className="text-sm text-orange-500 hover:text-orange-700 font-medium">
                        {showForm ? 'Hide' : '+ New Package'}
                    </button>
                </div>
                {showForm && <PackageForm onSubmit={createSubmit} />}
            </div>

            {/* Package cards */}
            <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                {packages.map((pkg) => (
                    <div key={pkg.id}
                        className={`bg-white rounded-2xl border p-5 relative ${pkg.is_active ? 'border-gray-100' : 'border-dashed border-gray-200 opacity-60'}`}>
                        {pkg.badge && (
                            <span className="absolute -top-3 left-5 bg-orange-500 text-white text-xs font-bold px-3 py-1 rounded-full shadow">
                                {pkg.badge}
                            </span>
                        )}
                        <div className="flex items-start justify-between mb-3">
                            <div>
                                <p className="font-bold text-gray-900">{pkg.name}</p>
                                <p className="text-xs text-gray-400 mt-0.5">{periodLabel(pkg.period_days)}</p>
                            </div>
                            <p className="text-2xl font-black text-orange-500">
                                RM {parseFloat(pkg.price).toFixed(2)}
                            </p>
                        </div>

                        <div className="flex items-center gap-2 mb-3">
                            <span className="bg-orange-50 text-orange-600 text-sm font-bold px-3 py-1 rounded-full">
                                {pkg.credits >= UNLIMITED ? '∞ Unlimited' : `${pkg.credits} credits`}
                            </span>
                            {!pkg.is_active && (
                                <span className="bg-gray-100 text-gray-400 text-xs px-2 py-1 rounded-full">Inactive</span>
                            )}
                        </div>

                        {pkg.description && (
                            <p className="text-xs text-gray-500 mb-3">{pkg.description}</p>
                        )}

                        <p className="text-xs text-gray-400 mb-4">
                            {pkg.subscriptions_count} subscription{pkg.subscriptions_count !== 1 ? 's' : ''}
                        </p>

                        <div className="flex gap-2">
                            <button onClick={() => setEditing(pkg)}
                                className="flex-1 py-1.5 rounded-xl border text-xs font-medium text-orange-500 hover:bg-orange-50">
                                Edit
                            </button>
                            <button onClick={() => deletePackage(pkg)}
                                className={[
                                    'py-1.5 px-3 rounded-xl border text-xs font-medium transition-colors',
                                    pkg.subscriptions_count > 0
                                        ? 'text-gray-400 border-gray-200 hover:bg-red-50 hover:text-red-400 hover:border-red-200'
                                        : 'text-red-400 hover:bg-red-50',
                                ].join(' ')}>
                                Delete{pkg.subscriptions_count > 0 ? ` (${pkg.subscriptions_count})` : ''}
                            </button>
                        </div>
                    </div>
                ))}
            </div>

            {editing && <EditModal pkg={editing} onClose={() => setEditing(null)} />}
        </AdminLayout>
    );
}
