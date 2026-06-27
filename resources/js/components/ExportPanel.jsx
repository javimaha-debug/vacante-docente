import { useState } from 'react';

function buildRows(selected) {
    return selected.map((item, i) => {
        const v = item.vacancy;
        const driving = item.vacancy?.distances?.driving ?? item.distances?.driving;
        return {
            orden: i + 1,
            lloc: v.lloc,
            num: v.num,
            centro: v.centro_nombre,
            localidad: v.localidad,
            provincia: v.provincia,
            tipo: v.tipo_centro,
            coche_min: driving?.duration_minutes ?? '',
            notas: item.notes ?? '',
        };
    });
}

function toCSV(rows) {
    const headers = ['Orden', 'Lloc', 'Núm', 'Centro', 'Localidad', 'Provincia', 'Tipo', 'Min (coche)', 'Notas'];
    const escape = (val) => {
        const s = String(val ?? '');
        return /[",\n;]/.test(s) ? `"${s.replace(/"/g, '""')}"` : s;
    };
    const lines = [headers.join(';')];
    for (const r of rows) {
        lines.push([r.orden, r.lloc, r.num, r.centro, r.localidad, r.provincia, r.tipo, r.coche_min, r.notas].map(escape).join(';'));
    }
    return lines.join('\n');
}

function download(filename, content, type) {
    const blob = new Blob([content], { type });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    a.click();
    URL.revokeObjectURL(url);
}

export default function ExportPanel({ selected, specialty, onClose }) {
    const [copied, setCopied] = useState(null);
    const rows = buildRows(selected);

    const llocList = rows.map((r) => r.lloc).join('\n');
    const readable = rows.map((r) => `${r.orden}. [${r.lloc}] ${r.centro} — ${r.localidad} (${r.provincia})`).join('\n');

    const copy = async (text, key) => {
        try {
            await navigator.clipboard.writeText(text);
            setCopied(key);
            setTimeout(() => setCopied(null), 1500);
        } catch {
            setCopied('error');
        }
    };

    const slug = (specialty?.name ?? 'vacantes').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4" onClick={onClose}>
            <div
                className="flex max-h-[85vh] w-full max-w-2xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl"
                onClick={(e) => e.stopPropagation()}
            >
                <header className="flex items-center justify-between border-b border-slate-200 px-5 py-3">
                    <div>
                        <h2 className="text-base font-bold text-slate-900">Exportar mi lista</h2>
                        <p className="text-xs text-slate-500">
                            {rows.length} vacantes priorizadas · {specialty?.name}
                        </p>
                    </div>
                    <button onClick={onClose} className="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700">
                        ✕
                    </button>
                </header>

                {rows.length === 0 ? (
                    <div className="px-5 py-10 text-center text-sm text-slate-400">
                        Todavía no has seleccionado ninguna vacante.
                    </div>
                ) : (
                    <>
                        <div className="flex flex-wrap gap-2 border-b border-slate-200 px-5 py-3">
                            <ActionButton onClick={() => copy(llocList, 'lloc')}>
                                {copied === 'lloc' ? '✓ Copiado' : 'Copiar códigos lloc'}
                            </ActionButton>
                            <ActionButton onClick={() => copy(readable, 'text')}>
                                {copied === 'text' ? '✓ Copiado' : 'Copiar lista'}
                            </ActionButton>
                            <ActionButton onClick={() => download(`mi-lista-${slug}.csv`, toCSV(rows), 'text/csv;charset=utf-8')}>
                                Descargar CSV
                            </ActionButton>
                            <ActionButton
                                onClick={() =>
                                    download(`mi-lista-${slug}.json`, JSON.stringify(rows, null, 2), 'application/json')
                                }
                            >
                                Descargar JSON
                            </ActionButton>
                        </div>

                        <div className="scroll-thin min-h-0 flex-1 overflow-y-auto px-5 py-3">
                            <table className="w-full text-left text-xs">
                                <thead className="sticky top-0 bg-white text-[11px] uppercase tracking-wide text-slate-400">
                                    <tr>
                                        <th className="py-1.5 pr-2">#</th>
                                        <th className="py-1.5 pr-2">Lloc</th>
                                        <th className="py-1.5 pr-2">Centro</th>
                                        <th className="py-1.5 pr-2">Localidad</th>
                                        <th className="py-1.5 pr-2 text-right">Coche</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {rows.map((r) => (
                                        <tr key={r.lloc + r.num}>
                                            <td className="py-1.5 pr-2 font-bold text-brand-600">{r.orden}</td>
                                            <td className="py-1.5 pr-2 font-mono text-slate-500">{r.lloc}</td>
                                            <td className="py-1.5 pr-2 font-medium text-slate-800">{r.centro}</td>
                                            <td className="py-1.5 pr-2 text-slate-500">{r.localidad}</td>
                                            <td className="py-1.5 pr-2 text-right text-slate-500">
                                                {r.coche_min !== '' ? `${r.coche_min} min` : '—'}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </>
                )}
            </div>
        </div>
    );
}

function ActionButton({ children, onClick }) {
    return (
        <button
            onClick={onClick}
            className="rounded-lg bg-slate-100 px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:bg-slate-200"
        >
            {children}
        </button>
    );
}
