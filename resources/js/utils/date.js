/**
 * Parse a server datetime string as LOCAL time.
 *
 * Laravel stores times as entered (local) but serialises with a "Z" (UTC)
 * suffix, causing browsers to apply a timezone offset when parsing.
 * Stripping the Z makes JavaScript treat the value as local time, which
 * matches what the admin originally typed.
 */
export function parseLocalDT(dt) {
    if (!dt) return new Date(NaN);
    // Remove the trailing Z or any +HH:MM offset
    return new Date(dt.replace(/Z$/, '').replace(/[+-]\d{2}:\d{2}$/, ''));
}

/**
 * Format a server datetime for display (respects local timezone).
 */
export function formatDT(dt, opts = {}) {
    return parseLocalDT(dt).toLocaleString('en-US', {
        weekday: 'short', month: 'short', day: 'numeric',
        hour: '2-digit', minute: '2-digit',
        ...opts,
    });
}

/**
 * Convert a server datetime string to the value needed by a
 * <input type="datetime-local"> field (YYYY-MM-DDTHH:mm in local time).
 */
export function toLocalInputDT(dt) {
    const d = parseLocalDT(dt);
    const pad = (n) => String(n).padStart(2, '0');
    return [
        d.getFullYear(), '-',
        pad(d.getMonth() + 1), '-',
        pad(d.getDate()), 'T',
        pad(d.getHours()), ':',
        pad(d.getMinutes()),
    ].join('');
}
