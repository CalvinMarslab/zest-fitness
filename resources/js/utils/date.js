/**
 * Parse a server datetime for display in the browser's local timezone.
 * Laravel serialises model dates as real UTC timestamps (with a trailing Z),
 * so the timezone marker must be preserved for Malaysia time to be restored.
 * Values without a timezone are treated as local time by the browser.
 */
export function parseLocalDT(dt) {
    if (!dt) return new Date(NaN);
    return new Date(dt);
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
