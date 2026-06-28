import { useState } from 'react';
import { modeSummary } from '../lib/distance';

// Columns of the rich (2025-style) listing — the full set, not the reduced 2026
// layout. Order here drives both the Excel export and the printable PDF.
const COLUMNS = [
    { key: 'orden', label: 'Ordre', align: 'center' },
    { key: 'lloc', label: 'Lloc' },
    { key: 'num', label: 'Núm', align: 'center' },
    { key: 'localidad', label: 'Localitat' },
    { key: 'provincia', label: 'Província' },
    { key: 'centro_codigo', label: 'Codi centre' },
    { key: 'centro', label: 'Centre' },
    { key: 'tipo', label: 'Tipus' },
    { key: 'req_ling', label: 'Requisit ling.', align: 'center' },
    { key: 'itinerante', label: 'Itinerant', align: 'center' },
    { key: 'caracteristicas', label: 'Característiques' },
    { key: 'observ', label: 'Observacions' },
    { key: 'coche', label: 'Temps (cotxe)', align: 'right' },
    { key: 'notas', label: 'Notes' },
];

function buildRows(selected) {
    return selected.map((item, i) => {
        const v = item.vacancy ?? {};
        const driving = modeSummary(v.distances, 'driving');
        const mins = driving?.ida ?? null;
        return {
            orden: i + 1,
            lloc: v.lloc ?? '',
            num: v.num ?? '',
            localidad: v.localidad ?? '',
            provincia: v.provincia ?? '',
            centro_codigo: v.centro_codigo ?? '',
            centro: v.centro_nombre ?? '',
            tipo: v.tipo_centro ?? '',
            req_ling: v.req_ling || v.requisito_linguistico ? 'Sí' : '',
            itinerante: v.itinerante ? 'Sí' : '',
            caracteristicas: (v.observ_tags ?? []).join(', '),
            observ: v.observ ?? '',
            coche: mins != null ? `${mins} min` : '',
            notas: item.notes ?? '',
        };
    });
}

function escapeHtml(s) {
    return String(s ?? '').replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
}

function tableHtml(rows) {
    const head = `<tr>${COLUMNS.map((c) => `<th>${escapeHtml(c.label)}</th>`).join('')}</tr>`;
    const body = rows
        .map(
            (r) =>
                `<tr>${COLUMNS.map((c) => `<td style="text-align:${c.align ?? 'left'}">${escapeHtml(r[c.key])}</td>`).join('')}</tr>`
        )
        .join('');
    return `<table><thead>${head}</thead><tbody>${body}</tbody></table>`;
}

function download(filename, content, type) {
    const blob = new Blob(['﻿', content], { type });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    a.click();
    URL.revokeObjectURL(url);
}

// Excel: an HTML-table workbook (.xls) — opens natively in Excel/LibreOffice,
// no external library, and keeps every column.
function exportExcel(rows, specialty, slug) {
    const title = `Llista de vacants — ${specialty?.name ?? ''}`;
    const html = `<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel"><head><meta charset="utf-8"><style>
        table{border-collapse:collapse} th,td{border:0.5pt solid #999;padding:3px 6px;font-family:Calibri,Arial,sans-serif;font-size:10pt}
        th{background:#1f4e79;color:#fff;font-weight:bold}
        </style></head><body><h3>${escapeHtml(title)}</h3>${tableHtml(rows)}</body></html>`;
    download(`mi-llista-${slug}.xls`, html, 'application/vnd.ms-excel');
}

// PDF: open a print-optimised window styled like an official listing and let the
// user "Guardar como PDF" from the browser's print dialog.
function printPdf(rows, specialty) {
    const fecha = new Date().toLocaleDateString('es-ES', { day: '2-digit', month: '2-digit', year: 'numeric' });
    const win = window.open('', '_blank');
    if (!win) return;
    const html = `<!doctype html><html lang="ca"><head><meta charset="utf-8"><title>Llista de vacants</title><style>
        @page { size: A4 landscape; margin: 12mm; }
        body { font-family: Arial, Helvetica, sans-serif; color: #111; font-size: 10px; }
        h1 { font-size: 15px; margin: 0 0 2px; }
        .sub { color: #444; font-size: 11px; margin: 0 0 10px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 0.5px solid #888; padding: 3px 5px; text-align: left; vertical-align: top; }
        thead th { background: #1f4e79; color: #fff; font-size: 9px; text-transform: uppercase; letter-spacing: .3px; }
        tbody tr:nth-child(even) { background: #f3f6fb; }
        .foot { margin-top: 10px; color: #666; font-size: 9px; }
        @media print { .noprint { display: none; } }
    </style></head><body>
        <div class="noprint" style="margin-bottom:10px">
            <button onclick="window.print()" style="padding:8px 14px;font-size:13px">🖨️ Imprimir / Guardar PDF</button>
        </div>
        <h1>Llista prioritzada de vacants</h1>
        <p class="sub">${escapeHtml(specialty?.name ?? '')} · ${rows.length} vacants · ${fecha}</p>
        ${tableHtml(rows)}
        <p class="foot">Generat amb VacanteDocente · ${fecha}</p>
    </body></html>`;
    win.document.write(html);
    win.document.close();
    win.focus();
    setTimeout(() => win.print(), 350);
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
                className="flex max-h-[85vh] w-full max-w-3xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl"
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
                            <ActionButton primary onClick={() => exportExcel(rows, specialty, slug)}>
                                📊 Descargar Excel
                            </ActionButton>
                            <ActionButton primary onClick={() => printPdf(rows, specialty)}>
                                🖨️ Imprimir / PDF
                            </ActionButton>
                            <ActionButton onClick={() => download(`mi-llista-${slug}.csv`, toCSV(rows), 'text/csv;charset=utf-8')}>
                                CSV
                            </ActionButton>
                            <ActionButton onClick={() => copy(llocList, 'lloc')}>
                                {copied === 'lloc' ? '✓ Copiado' : 'Copiar códigos lloc'}
                            </ActionButton>
                            <ActionButton onClick={() => copy(readable, 'text')}>
                                {copied === 'text' ? '✓ Copiado' : 'Copiar lista'}
                            </ActionButton>
                        </div>

                        <div className="scroll-thin min-h-0 flex-1 overflow-y-auto px-5 py-3">
                            <table className="w-full text-left text-xs">
                                <thead className="sticky top-0 bg-white text-[11px] uppercase tracking-wide text-slate-400">
                                    <tr>
                                        <th className="py-1.5 pr-2">#</th>
                                        <th className="py-1.5 pr-2">Lloc</th>
                                        <th className="py-1.5 pr-2">Centre</th>
                                        <th className="py-1.5 pr-2">Localitat</th>
                                        <th className="py-1.5 pr-2">Req.</th>
                                        <th className="py-1.5 pr-2 text-right">Cotxe</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {rows.map((r) => (
                                        <tr key={r.lloc + r.num}>
                                            <td className="py-1.5 pr-2 font-bold text-brand-600">{r.orden}</td>
                                            <td className="py-1.5 pr-2 font-mono text-slate-500">{r.lloc}</td>
                                            <td className="py-1.5 pr-2 font-medium text-slate-800">{r.centro}</td>
                                            <td className="py-1.5 pr-2 text-slate-500">{r.localidad}</td>
                                            <td className="py-1.5 pr-2 text-slate-500">{r.req_ling || '—'}</td>
                                            <td className="py-1.5 pr-2 text-right text-slate-500">{r.coche || '—'}</td>
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

function toCSV(rows) {
    const escape = (val) => {
        const s = String(val ?? '');
        return /[",\n;]/.test(s) ? `"${s.replace(/"/g, '""')}"` : s;
    };
    const lines = [COLUMNS.map((c) => c.label).join(';')];
    for (const r of rows) lines.push(COLUMNS.map((c) => escape(r[c.key])).join(';'));
    return lines.join('\n');
}

function ActionButton({ children, onClick, primary }) {
    return (
        <button
            onClick={onClick}
            className={
                primary
                    ? 'rounded-lg bg-brand-600 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-brand-700'
                    : 'rounded-lg bg-slate-100 px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:bg-slate-200'
            }
        >
            {children}
        </button>
    );
}
