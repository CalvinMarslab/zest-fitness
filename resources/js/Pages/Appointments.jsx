import { Head, router } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';

const when = value => new Intl.DateTimeFormat('en-MY', { weekday:'short', day:'numeric', month:'short', hour:'numeric', minute:'2-digit' }).format(new Date(value));

export default function Appointments({ slots, bookings }) {
    return <AppLayout active="Appointments" title="Appointments">
        <Head title="Appointments" />
        <div className="flex items-center justify-between mb-5"><div><h1 className="text-2xl font-black text-[#333E48]">Appointments</h1><p className="text-sm text-slate-500">Book a private session with your coach.</p></div></div>
        {bookings.length > 0 && <section className="mb-7"><h2 className="font-bold text-[#333E48] mb-3">Your upcoming sessions</h2><div className="space-y-3">{bookings.map(b => <div key={b.id} className="bg-white rounded-2xl p-4 border border-slate-200"><div className="font-bold">{b.slot.service.name}</div><div className="text-sm text-slate-500">{when(b.slot.start_time)} · {b.slot.coach.name}</div><button onClick={() => confirm('Cancel this appointment?') && router.delete(`/appointments/bookings/${b.id}`)} className="mt-3 text-xs font-bold text-red-500">Cancel appointment</button></div>)}</div></section>}
        <section><h2 className="font-bold text-[#333E48] mb-3">Available times</h2>{slots.length === 0 ? <div className="bg-white rounded-2xl p-8 text-center text-slate-400">No appointment times available yet.</div> : <div className="space-y-3">{slots.map(s => <div key={s.id} className="bg-white rounded-2xl p-4 border border-slate-200 flex gap-3"><div className="w-1.5 rounded-full" style={{background:s.color}}/><div className="flex-1"><div className="font-bold text-[#333E48]">{s.service}</div><div className="text-sm text-slate-500">{when(s.start_time)}</div><div className="text-xs text-slate-400 mt-1">{s.coach}{s.location ? ` · ${s.location}` : ''} · {s.credits} credit{s.credits === 1 ? '' : 's'}</div></div><button onClick={() => router.post(`/appointments/${s.id}`)} className="self-center bg-[#FFF34D] text-[#333E48] px-4 py-2 rounded-xl text-sm font-black">Book</button></div>)}</div>}</section>
    </AppLayout>;
}
