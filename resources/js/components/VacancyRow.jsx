import { useState } from 'react';
import clsx from 'clsx';
import { TRAVEL_MODES, formatDuration, modeSummary, hasAnyDistance, mapsRouteUrl } from '../lib/distance';

const PROVINCIA_STYLES = {
    Alacant: 'bg-rose-100 text-rose-700',
    Castelló: 'bg-amber-100 text-amber-700',
    València: 'bg-emerald-100 text-emerald-700',
};

// Compact one-line vacancy row for the powerful list view. Shows outbound/return
// travel times per mode inline plus a Google Maps route link, and offers quick
// prioritise / discard actions. With a drag handle it can be reordered.
export default function VacancyRow({
    vacancy,
    status = 'neutral',
    position,
    notes = '',
    home = null,
    onStatusChange,
    onNotesChange,
    dragHandleProps,
    isDragging = false,
}) {
    const [showNotes, setShowNotes] = useState(false);
    const tags = vacancy.observ_tags ?? [];
    const driving = modeSummary(vacancy.distances, 'driving');
    const hasDist = hasAnyDistance(vacancy.distances);

    return (
        <div
            className={clsx(
                'rounded-lg border bg-white px-2.5 py-2 text-sm shadow-sm transition',
                status === 'discarded' && 'opacity-50',
                isDragging ? 'border-brand-400 shadow-lg ring-2 ring-brand-200' : 'border-slate-200 hover:border-slate-300'
            )}
        >
            <div className="flex items-center gap-2">
                {dragHandleProps && (
                    <button
                        type="button"
                        {...dragHandleProps}
                        className="cursor-grab touch-none px-0.5 text-slate-300 hover:text-slate-500 active:cursor-grabbing"
                        aria-label="Reordenar"
                        title="Arrastrar para subir/bajar"
                    >
                        ⠿
                    </button>
                )}

                {typeof position === 'number' && (
                    <span className="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-brand-600 text-[11px] font-bold text-white">
                        {position}
                    </span>
                )}

                {/* Centre + locality */}
                <div className="min-w-0 flex-1">
                    <div className="flex items-center gap-2">
                        <span className="truncate font-semibold text-slate-900" title={vacancy.centro_nombre}>
                            {vacancy.centro_nombre}
                        </span>
                        <span className="shrink-0 text-[11px] text-slate-400">#{vacancy.num}</span>
                    </div>
                    <div className="flex flex-wrap items-center gap-1.5 text-[11px] text-slate-500">
                        <span className="truncate">{vacancy.localidad}</span>
                        <span
                            className={clsx(
                                'rounded-full px-1.5 py-0.5 font-semibold',
                                PROVINCIA_STYLES[vacancy.provincia] ?? 'bg-slate-100 text-slate-600'
                            )}
                        >
                            {vacancy.provincia}
                        </span>
                        <span className="rounded-full bg-slate-100 px-1.5 py-0.5 text-slate-600">{vacancy.tipo_centro}</span>
                        {(vacancy.req_ling || vacancy.requisito_linguistico) && (
                            <span className="rounded-full bg-indigo-100 px-1.5 py-0.5 font-medium text-indigo-700">RL</span>
                        )}
                        {vacancy.itinerante && (
                            <span className="rounded-full bg-orange-100 px-1.5 py-0.5 font-medium text-orange-700">Itin.</span>
                        )}
                        {tags.map((t) => (
                            <span key={t} className="rounded-full bg-sky-100 px-1.5 py-0.5 text-sky-700">{t}</span>
                        ))}
                    </div>
                </div>

                {/* Travel times (ida → tornada) */}
                <div className="w-28 shrink-0 text-right">
                    {driving ? (
                        <>
                            <div className="text-xs font-semibold text-slate-800">
                                🚗 {formatDuration(driving.ida) ?? '—'}
                                {driving.tornada != null && <span className="text-slate-400"> · {formatDuration(driving.tornada)}</span>}
                            </div>
                            {driving.km != null && <div className="text-[11px] text-slate-400">{driving.km} km</div>}
                        </>
                    ) : (
                        <div className="text-[11px] text-slate-300">sin calcular</div>
                    )}
                </div>

                {/* Quick actions */}
                <div className="flex shrink-0 items-center gap-1">
                    <a
                        href={mapsRouteUrl(home, vacancy, 'driving')}
                        target="_blank"
                        rel="noreferrer"
                        title="Ver ruta en Google Maps"
                        className="rounded-md px-1.5 py-1 text-xs text-slate-400 hover:bg-slate-100 hover:text-brand-600"
                    >
                        🗺️
                    </a>
                    <button
                        type="button"
                        onClick={() => onStatusChange?.(status === 'selected' ? 'neutral' : 'selected')}
                        title={status === 'selected' ? 'Quitar de mi lista' : 'Añadir a mi lista'}
                        className={clsx(
                            'rounded-md px-1.5 py-1 text-xs font-semibold transition',
                            status === 'selected' ? 'bg-emerald-600 text-white' : 'text-emerald-700 hover:bg-emerald-50'
                        )}
                    >
                        ✓
                    </button>
                    <button
                        type="button"
                        onClick={() => onStatusChange?.(status === 'discarded' ? 'neutral' : 'discarded')}
                        title={status === 'discarded' ? 'Restaurar' : 'Descartar'}
                        className={clsx(
                            'rounded-md px-1.5 py-1 text-xs font-semibold transition',
                            status === 'discarded' ? 'bg-rose-600 text-white' : 'text-rose-600 hover:bg-rose-50'
                        )}
                    >
                        ✕
                    </button>
                    {onNotesChange && (
                        <button
                            type="button"
                            onClick={() => setShowNotes((v) => !v)}
                            title="Nota"
                            className={clsx('rounded-md px-1.5 py-1 text-xs', notes ? 'text-brand-600' : 'text-slate-400 hover:bg-slate-100')}
                        >
                            ✎
                        </button>
                    )}
                </div>
            </div>

            {hasDist && (
                <div className="mt-1 flex flex-wrap gap-x-3 pl-1 text-[11px] text-slate-400">
                    {TRAVEL_MODES.filter((m) => m.key !== 'driving').map((m) => {
                        const s = modeSummary(vacancy.distances, m.key);
                        if (!s) return null;
                        return (
                            <span key={m.key}>
                                {m.icon} {formatDuration(s.ida) ?? '—'}
                                {s.tornada != null && ` · ${formatDuration(s.tornada)}`}
                            </span>
                        );
                    })}
                    {driving?.trafficNote && <span className="text-slate-300">· {driving.trafficNote}</span>}
                </div>
            )}

            {vacancy.observ && !showNotes && (
                <p className="mt-1 truncate pl-1 text-[11px] italic text-slate-400" title={vacancy.observ}>{vacancy.observ}</p>
            )}

            {showNotes && onNotesChange && (
                <textarea
                    value={notes}
                    onChange={(e) => onNotesChange(e.target.value)}
                    placeholder="Nota privada…"
                    rows={2}
                    className="mt-2 w-full resize-y rounded-lg border border-slate-200 px-2 py-1.5 text-xs focus:border-brand-400 focus:outline-none focus:ring-1 focus:ring-brand-200"
                />
            )}
        </div>
    );
}
