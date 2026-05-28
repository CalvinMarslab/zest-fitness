import { useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';

// ─── Helpers ─────────────────────────────────────────────────────────────────

const DAYS = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

const TYPE_STYLES = {
    Strength: 'text-red-400 bg-red-400/10 border-red-400/20',
    Metcon:   'text-orange-400 bg-orange-400/10 border-orange-400/20',
    Cardio:   'text-blue-400 bg-blue-400/10 border-blue-400/20',
    Aerobic:  'text-sky-400 bg-sky-400/10 border-sky-400/20',
    Skill:    'text-purple-400 bg-purple-400/10 border-purple-400/20',
    Mobility: 'text-green-400 bg-green-400/10 border-green-400/20',
    Rest:     'text-[#555] bg-[#1A1A1A] border-[#2A2A2A]',
    default:  'text-[#888] bg-[#1A1A1A] border-[#2A2A2A]',
};

function typeStyle(type) {
    return TYPE_STYLES[type] ?? TYPE_STYLES.default;
}

// ─── Program Header ───────────────────────────────────────────────────────────

function ProgramHeader({ program, active, onSelect }) {
    const isActive = active === program.id;

    return (
        <button
            onClick={() => onSelect(program.id)}
            className={[
                'flex-1 rounded-2xl p-5 text-left transition-all border',
                isActive
                    ? 'bg-[#C8FF00]/10 border-[#C8FF00]/40'
                    : 'bg-[#1A1A1A] border-[#2A2A2A] hover:border-[#C8FF00]/20',
            ].join(' ')}
        >
            <div className="flex items-center gap-3 mb-2">
                <span className="text-3xl">{program.icon}</span>
                <div>
                    <p className={`font-black text-base leading-tight ${isActive ? 'text-[#C8FF00]' : 'text-white'}`}>
                        {program.name}
                    </p>
                    {isActive && (
                        <span className="inline-block text-[10px] font-bold px-2 py-0.5 rounded-full bg-[#C8FF00] text-[#0D0D0D] mt-0.5">
                            Active
                        </span>
                    )}
                </div>
            </div>
            <p className="text-xs text-[#666] leading-snug">{program.tagline}</p>
        </button>
    );
}

// ─── Day Tab Strip ────────────────────────────────────────────────────────────

function DayTabs({ days, selectedDay, todayLabel, onSelect }) {
    return (
        <div className="flex gap-1.5 overflow-x-auto pb-1 -mx-5 px-5">
            {days.map(({ day }) => {
                const isToday    = day === todayLabel;
                const isSelected = day === selectedDay;
                return (
                    <button
                        key={day}
                        onClick={() => onSelect(day)}
                        className={[
                            'shrink-0 px-3 py-1.5 rounded-xl text-sm font-bold transition-all whitespace-nowrap',
                            isSelected
                                ? 'bg-[#C8FF00] text-[#0D0D0D]'
                                : isToday
                                    ? 'bg-[#C8FF00]/10 text-[#C8FF00] border border-[#C8FF00]/30'
                                    : 'bg-[#1A1A1A] text-[#666] border border-[#2A2A2A] hover:border-[#C8FF00]/20',
                        ].join(' ')}
                    >
                        {isToday ? `Today (${day.slice(0, 3)})` : day.slice(0, 3)}
                    </button>
                );
            })}
        </div>
    );
}

// ─── Workout Block ────────────────────────────────────────────────────────────

function WorkoutBlock({ item }) {
    return (
        <div className="bg-[#111] rounded-2xl border border-[#2A2A2A] p-4 flex gap-3">
            <span className={`shrink-0 self-start text-xs font-bold px-2 py-1 rounded-lg border ${typeStyle(item.type)}`}>
                {item.type}
            </span>
            <div className="min-w-0">
                <p className="font-bold text-white text-sm">{item.name}</p>
                {item.sets && (
                    <p className="text-xs text-[#666] mt-0.5">{item.sets}</p>
                )}
                {item.detail && (
                    <p className="text-xs text-[#555] mt-0.5 leading-relaxed">{item.detail}</p>
                )}
            </div>
        </div>
    );
}

// ─── Weekly Overview Strip ────────────────────────────────────────────────────

function WeeklyOverview({ schedule, todayLabel }) {
    return (
        <div className="grid grid-cols-7 gap-1">
            {schedule.map(({ day, focus }) => {
                const isToday = day === todayLabel;
                const isRest  = focus === 'Rest Day';
                return (
                    <div
                        key={day}
                        className={[
                            'rounded-xl p-1.5 text-center',
                            isToday
                                ? 'bg-[#C8FF00] text-[#0D0D0D]'
                                : isRest
                                    ? 'bg-[#111] text-[#333]'
                                    : 'bg-[#1A1A1A] text-[#666]',
                        ].join(' ')}
                    >
                        <p className="text-xs font-black">{day.slice(0, 1)}</p>
                        <div className={`mt-0.5 mx-auto w-1.5 h-1.5 rounded-full ${
                            isToday ? 'bg-[#0D0D0D]' : isRest ? 'bg-[#2A2A2A]' : 'bg-[#444]'
                        }`} />
                    </div>
                );
            })}
        </div>
    );
}

// ─── Program Detail ───────────────────────────────────────────────────────────

function ProgramDetail({ program }) {
    const todayLabel = DAYS[new Date().getDay()];
    const [selectedDay, setSelectedDay] = useState(todayLabel);

    const dayData = program.schedule.find(s => s.day === selectedDay) ?? program.schedule[0];

    return (
        <div className="space-y-5">
            <p className="text-sm text-[#666] leading-relaxed">{program.description}</p>

            <div>
                <p className="text-xs font-bold uppercase tracking-widest text-[#555] mb-2">This Week</p>
                <WeeklyOverview schedule={program.schedule} todayLabel={todayLabel} />
            </div>

            <DayTabs
                days={program.schedule}
                selectedDay={selectedDay}
                todayLabel={todayLabel}
                onSelect={setSelectedDay}
            />

            <div>
                <div className="flex items-center justify-between mb-3">
                    <div>
                        <h3 className="font-black text-white">{dayData.day}</h3>
                        <p className="text-xs text-[#666]">{dayData.focus}</p>
                    </div>
                    {dayData.day === todayLabel && (
                        <span className="text-xs font-black bg-[#C8FF00] text-[#0D0D0D] px-2.5 py-1 rounded-full">
                            Today
                        </span>
                    )}
                </div>

                {dayData.workout[0].type === 'Rest' ? (
                    <div className="bg-[#111] rounded-2xl border border-[#2A2A2A] p-6 text-center">
                        <p className="text-3xl mb-2">😴</p>
                        <p className="font-bold text-[#888]">Rest Day</p>
                        <p className="text-xs mt-1 text-[#555]">{dayData.workout[0].sets}</p>
                    </div>
                ) : (
                    <div className="flex flex-col gap-2">
                        {dayData.workout.map((item, i) => (
                            <WorkoutBlock key={i} item={item} />
                        ))}
                    </div>
                )}
            </div>
        </div>
    );
}

// ─── Page ─────────────────────────────────────────────────────────────────────

export default function Training({ programs }) {
    const [activeId, setActiveId] = useState(programs[0]?.id);
    const activeProgram = programs.find(p => p.id === activeId);

    return (
        <AppLayout active="Training">
            <div className="mb-6">
                <h1 className="text-2xl font-black text-white">Training Programs</h1>
                <p className="mt-0.5 text-sm text-[#666]">Your daily workout plan — pick a program to follow</p>
            </div>

            <div className="flex gap-3 mb-6">
                {programs.map(p => (
                    <ProgramHeader
                        key={p.id}
                        program={p}
                        active={activeId}
                        onSelect={setActiveId}
                    />
                ))}
            </div>

            {activeProgram && <ProgramDetail program={activeProgram} />}
        </AppLayout>
    );
}
