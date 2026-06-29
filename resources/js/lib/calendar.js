// Shared calendar metadata + month-grid helpers for the academic calendar
// (used by both the superadmin manager and the user-facing read-only view).

export const EVENT_TYPES = [
    { key: 'solicitud', label: 'Solicitud' },
    { key: 'listado_provisional', label: 'Listado provisional' },
    { key: 'listado_definitivo', label: 'Listado definitivo' },
    { key: 'adjudicacion', label: 'Adjudicación' },
    { key: 'plazo_alegaciones', label: 'Plazo alegaciones' },
    { key: 'resolucion', label: 'Resolución' },
    { key: 'convocatoria', label: 'Convocatoria' },
    { key: 'otro', label: 'Otro' },
];

// Tailwind colour sets per event type. `dot`/`bg`/`text` are static class names
// so Tailwind keeps them in the build.
export const EVENT_TYPE_META = {
    solicitud: { label: 'Solicitud', dot: 'bg-amber-500', chip: 'bg-amber-100 text-amber-800', darkChip: 'bg-amber-500/20 text-amber-300' },
    listado_provisional: { label: 'Listado provisional', dot: 'bg-blue-500', chip: 'bg-blue-100 text-blue-800', darkChip: 'bg-blue-500/20 text-blue-300' },
    listado_definitivo: { label: 'Listado definitivo', dot: 'bg-teal-500', chip: 'bg-teal-100 text-teal-800', darkChip: 'bg-teal-500/20 text-teal-300' },
    adjudicacion: { label: 'Adjudicación', dot: 'bg-emerald-500', chip: 'bg-emerald-100 text-emerald-800', darkChip: 'bg-emerald-500/20 text-emerald-300' },
    plazo_alegaciones: { label: 'Plazo alegaciones', dot: 'bg-rose-500', chip: 'bg-rose-100 text-rose-800', darkChip: 'bg-rose-500/20 text-rose-300' },
    resolucion: { label: 'Resolución', dot: 'bg-indigo-500', chip: 'bg-indigo-100 text-indigo-800', darkChip: 'bg-indigo-500/20 text-indigo-300' },
    convocatoria: { label: 'Convocatoria', dot: 'bg-purple-500', chip: 'bg-purple-100 text-purple-800', darkChip: 'bg-purple-500/20 text-purple-300' },
    otro: { label: 'Otro', dot: 'bg-slate-400', chip: 'bg-slate-100 text-slate-700', darkChip: 'bg-slate-600/40 text-slate-300' },
};

export function typeMeta(type) {
    return EVENT_TYPE_META[type] ?? EVENT_TYPE_META.otro;
}

export const MONTHS_ES = [
    'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio',
    'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre',
];

export const WEEKDAYS_ES = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];

/** Local YYYY-MM-DD (avoids UTC off-by-one from toISOString). */
export function ymd(date) {
    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, '0');
    const d = String(date.getDate()).padStart(2, '0');
    return `${y}-${m}-${d}`;
}

/**
 * Build a Monday-first month grid: an array of weeks, each an array of 7 cells.
 * Each cell is { date: 'YYYY-MM-DD', inMonth: bool, day: number }.
 */
export function buildMonthGrid(year, month) {
    const first = new Date(year, month, 1);
    // JS getDay: 0=Sun..6=Sat → shift so Monday=0.
    const lead = (first.getDay() + 6) % 7;
    const start = new Date(year, month, 1 - lead);

    const weeks = [];
    let cursor = start;
    for (let w = 0; w < 6; w++) {
        const week = [];
        for (let d = 0; d < 7; d++) {
            week.push({ date: ymd(cursor), inMonth: cursor.getMonth() === month, day: cursor.getDate() });
            cursor = new Date(cursor.getFullYear(), cursor.getMonth(), cursor.getDate() + 1);
        }
        weeks.push(week);
        // Stop after we've covered the month and filled the final week.
        if (cursor.getMonth() !== month && w >= 4) break;
    }
    return weeks;
}

export function monthLabel(year, month) {
    return `${MONTHS_ES[month]} ${year}`.replace(/^\w/, (c) => c.toUpperCase());
}
