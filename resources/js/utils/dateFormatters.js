const DAY_MS = 86_400_000;

/**
 * Returns 'Today', 'Yesterday', 'Tomorrow', or null if none match.
 * Caller provides the fallback format for other dates.
 */
export function formatRelativeDate(date) {
    const d     = date instanceof Date ? date : new Date(date);
    const today = new Date();

    if (d.toDateString() === today.toDateString()) return 'Today';
    if (d.toDateString() === new Date(today.getTime() - DAY_MS).toDateString()) return 'Yesterday';
    if (d.toDateString() === new Date(today.getTime() + DAY_MS).toDateString()) return 'Tomorrow';
    return null;
}
