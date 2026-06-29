import { useState } from 'react';
import clsx from 'clsx';
import { TRAVEL_MODES, formatDuration, modeSummary, hasAnyDistance, mapsRouteUrl } from '../lib/distance';
import CambioBadge from './CambioBadge';

const PROVINCIA_STYLES = {
    Alacant: 'bg-rose-100 text-rose-700',
    Castelló: 'bg-amber-100 text-amber-700',
    València: 'bg-emerald-100 text-emerald-700',
};

const TAG_STYLES = {
    'Difícil provisión': 'bg-orange-100 text-orange-700',
    CRA: 'bg-violet-100 text-violet-700',
    'Centre singular': 'bg-sky-100 text-sky-700',
};

function DistanceRow({ distances, vacancy, home }) {
    if (!hasAnyDistance(distances)) return null;
    const driving = modeSummary(distances, 'driving');

    return (
        <div className="mt-2 space-y-1 rounded-lg bg-slate-50 px-2.5 py-2">
            <div className="flex flex-col gap-1 text-xs">
                {TRAVEL_MODES.map((m) => {
                    const s = modeSummary(distances, m.key);
                    if (!s) return null;
                    return (
                        <span key={m.key} className="inline-flex items-center gap-1 text-slate-600">
                            <span aria-hidden>{m.icon}</span>
                            <span className="text-slate-400">ida</span>
                            <span className="font-semibold text-slate-800">{formatDuration(s.ida) ?? '—'}</span>
                            {s.tornada != null && (
                                <>
                                    <span className="text-slate-400">· tornada</span>
                                    <span className="font-semibold text-slate-800">{formatDuration(s.tornada)}</span>
                                </>
                            )}
                            {s.km != null && <span className="text-slate-400">· {s.km} km</span>}
                        </span>
                    );
                })}
            </div>
            {driving?.trafficNote && <p className="text-[11px] text-slate-400">{driving.trafficNote}</p>}
            <a
                href={mapsRouteUrl(home, vacancy, 'driving')}
                target="_blank"
                rel="noreferrer"
                className="inline-block text-[11px] font-semibold text-brand-600 hover:text-brand-700"
            >
                🗺️ Ver ruta en Google Maps
            </a>
        </div>
    );
}

export default function VacancyCard({
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
    const [showNotes, setShowNotes] = useState(Boolean(notes));
    const tags = vacancy.observ_tags ?? [];

    return (
        <div
            className={clsx(
                'rounded-2xl bg-white p-4 shadow-sm transition',
                status === 'discarded' && 'opacity-60',
                isDragging ? 'shadow-lg ring-2 ring-brand-300' : 'ring-1 ring-slate-200 hover:ring-slate-300'
            )}
        >
            <div className="flex items-start gap-2">
                {typeof position === 'number' && (
                    <span className="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-brand-600 text-xs font-bold text-white">
                        {position}
                    </span>
                )}

                {dragHandleProps && (
                    <button
                        type="button"
                        {...dragHandleProps}
                        className="mt-0.5 cursor-grab touch-none text-slate-300 hover:text-slate-500 active:cursor-grabbing"
                        aria-label="Reordenar"
                        title="Arrastrar para reordenar"
                    >
                        ⠿
                    </button>
                )}

                <div className="min-w-0 flex-1">
                    <div className="flex items-baseline justify-between gap-2">
                        <h3 className="truncate text-sm font-semibold text-slate-900" title={vacancy.centro_nombre}>
                            {vacancy.centro_nombre}
                        </h3>
                        <div className="flex shrink-0 items-center gap-1">
                            <CambioBadge cambio={vacancy.cambio} />
                            <span className="text-[11px] font-medium text-slate-400">#{vacancy.num}</span>
                        </div>
                    </div>

                    <p className="mt-0.5 text-xs text-slate-500">
                        {vacancy.localidad} · {vacancy.centro_codigo}
                    </p>

                    <div className="mt-2 flex flex-wrap items-center gap-1">
                        <span
                            className={clsx(
                                'rounded-full px-2 py-0.5 text-[11px] font-semibold',
                                PROVINCIA_STYLES[vacancy.provincia] ?? 'bg-slate-100 text-slate-600'
                            )}
                        >
                            {vacancy.provincia}
                        </span>
                        <span className="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-medium text-slate-600">
                            {vacancy.tipo_centro}
                        </span>
                        {tags.map((tag) => (
                            <span
                                key={tag}
                                className={clsx(
                                    'rounded-full px-2 py-0.5 text-[11px] font-medium',
                                    TAG_STYLES[tag] ?? 'bg-slate-100 text-slate-600'
                                )}
                            >
                                {tag}
                            </span>
                        ))}
                        {vacancy.req_ling && (
                            <span className="rounded-full bg-indigo-100 px-2 py-0.5 text-[11px] font-medium text-indigo-700">
                                Req. lingüístic
                            </span>
                        )}
                    </div>

                    {vacancy.observ && <p className="mt-1.5 text-[11px] italic text-slate-400">{vacancy.observ}</p>}

                    <DistanceRow distances={vacancy.distances} vacancy={vacancy} home={home} />
                </div>
            </div>

            <div className="mt-3 flex flex-wrap items-center justify-between gap-2">
                <div className="flex flex-1 flex-wrap gap-1.5">
                    <StatusButton active={status === 'selected'} color="emerald" onClick={() => onStatusChange?.('selected')}>
                        ✓ Mi lista
                    </StatusButton>
                    <StatusButton active={status === 'revisar'} color="amber" onClick={() => onStatusChange?.('revisar')}>
                        ? A revisar
                    </StatusButton>
                    <StatusButton active={status === 'neutral'} color="slate" onClick={() => onStatusChange?.('neutral')}>
                        ↺ Sin revisar
                    </StatusButton>
                    <StatusButton active={status === 'discarded'} color="rose" onClick={() => onStatusChange?.('discarded')}>
                        ✕ Descartar
                    </StatusButton>
                </div>

                {onNotesChange && (
                    <button
                        type="button"
                        onClick={() => setShowNotes((v) => !v)}
                        className="text-[11px] font-medium text-brand-600 hover:text-brand-700"
                    >
                        {notes ? '✎ Nota' : '+ Nota'}
                    </button>
                )}
            </div>

            {showNotes && onNotesChange && (
                <textarea
                    value={notes}
                    onChange={(e) => onNotesChange(e.target.value)}
                    placeholder="Añade una nota privada…"
                    rows={2}
                    className="mt-2 w-full resize-y rounded-lg border border-slate-200 px-2 py-1.5 text-xs focus:border-brand-400 focus:outline-none focus:ring-1 focus:ring-brand-200"
                />
            )}
        </div>
    );
}

function StatusButton({ active, color, children, onClick }) {
    const palette = {
        emerald: active ? 'bg-emerald-600 text-white' : 'text-emerald-700 hover:bg-emerald-50',
        amber: active ? 'bg-amber-500 text-white' : 'text-amber-700 hover:bg-amber-50',
        rose: active ? 'bg-rose-600 text-white' : 'text-rose-700 hover:bg-rose-50',
        slate: active ? 'bg-slate-700 text-white' : 'text-slate-600 hover:bg-slate-100',
    };
    return (
        <button
            type="button"
            onClick={onClick}
            aria-pressed={active}
            className={clsx('flex min-h-[44px] flex-1 items-center justify-center rounded-lg px-2 py-2 text-xs font-semibold transition', palette[color])}
        >
            {children}
        </button>
    );
}
