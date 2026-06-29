// Helpers shared by the document area.

export function formatBytes(bytes) {
    const b = Number(bytes) || 0;
    if (b < 1024) return `${b} B`;
    const units = ['KB', 'MB', 'GB', 'TB'];
    let v = b / 1024;
    let i = 0;
    while (v >= 1024 && i < units.length - 1) { v /= 1024; i++; }
    return `${v.toFixed(v < 10 ? 1 : 0)} ${units[i]}`;
}

export const TYPE_ICON = {
    pdf: '📕',
    word: '📘',
    image: '🖼️',
    other: '📄',
};

export const TYPE_LABEL = {
    pdf: 'PDF',
    word: 'Word',
    image: 'Imagen',
    other: 'Archivo',
};

export const STATUS_BADGE = {
    pending: { label: 'En cola', cls: 'bg-slate-100 text-slate-600' },
    processing: { label: 'Procesando', cls: 'bg-amber-100 text-amber-700' },
    ready: { label: 'Listo', cls: 'bg-emerald-100 text-emerald-700' },
    failed: { label: 'Error', cls: 'bg-rose-100 text-rose-700' },
};

export function fechaCorta(d) {
    if (!d) return '';
    try { return new Date(d).toLocaleDateString('es-ES', { day: '2-digit', month: 'short', year: 'numeric' }); }
    catch { return d; }
}
