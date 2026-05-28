export const ACTIVITY_TYPES = {
    run:     { label: 'Run',     color: 'text-blue-600',   bg: 'bg-blue-50',   icon: '🏃' },
    cycle:   { label: 'Cycle',   color: 'text-green-600',  bg: 'bg-green-50',  icon: '🚴' },
    hike:    { label: 'Hike',    color: 'text-amber-600',  bg: 'bg-amber-50',  icon: '🥾' },
    swim:    { label: 'Swim',    color: 'text-cyan-600',   bg: 'bg-cyan-50',   icon: '🏊' },
    hiit:    { label: 'HIIT',    color: 'text-red-600',    bg: 'bg-red-50',    icon: '🔥' },
    yoga:    { label: 'Yoga',    color: 'text-purple-600', bg: 'bg-purple-50', icon: '🧘' },
    weights: { label: 'Weights', color: 'text-gray-600',   bg: 'bg-gray-100',  icon: '🏋️' },
    spin:    { label: 'Spin',    color: 'text-indigo-600', bg: 'bg-indigo-50', icon: '🚴' },
    pilates: { label: 'Pilates', color: 'text-pink-600',   bg: 'bg-pink-50',   icon: '🤸' },
    boxing:  { label: 'Boxing',  color: 'text-orange-600', bg: 'bg-orange-50', icon: '🥊' },
    default: { label: 'Workout', color: 'text-orange-600', bg: 'bg-orange-50', icon: '⚡' },
};

export function getActivityType(type) {
    return ACTIVITY_TYPES[type?.toLowerCase()] ?? ACTIVITY_TYPES.default;
}
