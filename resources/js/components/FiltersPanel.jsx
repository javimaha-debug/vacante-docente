import { useEffect, useState } from 'react';
import clsx from 'clsx';
import { TRAVEL_MODES } from '../lib/distance';
import {
    PROVINCIAS,
    TIPOS_CENTRO,
    CARACTERISTICAS,
    ESTADOS,
    countActiveFilters,
} from '../lib/vacancyFilters';

export default function FiltersPanel({ filters, setFilters, counts, onClear }) {
    // Local search state debounced into the shared filters (300ms).
    const [searchInput, setSearchInput] = useState(filters.search ?? '');
    useEffect(() => {
        const id = setTimeout(() => setFilters((f) => ({ ...f, search: searchInput.trim() })), 300);
        return () => clearTimeout(id);
    }, [searchInput, setFilters]);

    // Keep the local input in sync when filters are cleared from outside.
    useEffect(() => {
        if ((filters.search ?? '') !== searchInput.trim()) setSearchInput(filters.search ?? '');
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [filters.search]);

    const [obsInput, setObsInput] = useState(filters.observaciones ?? '');
    useEffect(() => {
        const id = setTimeout(() => setFilters((f) => ({ ...f, observaciones: obsInput.trim() })), 300);
        return () => clearTimeout(id);
    }, [obsInput, setFilters]);
    useEffect(() => {
        if ((filters.observaciones ?? '') !== obsInput.trim()) setObsInput(filters.observaciones ?? '');
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [filters.observaciones]);

    const toggleArray = (key, value) =>
        setFilters((f) => {
            const list = f[key] ?? [];
            return { ...f, [key]: list.includes(value) ? list.filter((v) => v !== value) : [...list, value] };
        });

    const set = (key, value) => setFilters((f) => ({ ...f, [key]: value }));
    const activeCount = countActiveFilters(filters);

    return (
        <div className="space-y-5">
            {/* Live match counter + clear */}
            <div className="flex items-center justify-between rounded-lg bg-brand-50 px-3 py-2">
                <span className="text-sm font-semibold text-brand-800" role="status" aria-live="polite">
                    {counts.matching} {counts.matching === 1 ? 'vacante coincide' : 'vacantes coinciden'}
                </span>
                <button
                    type="button"
                    onClick={onClear}
                    disabled={activeCount === 0}
                    className="text-[11px] font-semibold text-brand-600 enabled:hover:text-brand-700 disabled:text-slate-300"
                >
                    Limpiar filtros{activeCount > 0 ? ` (${activeCount})` : ''}
                </button>
            </div>

            <div>
                <Label>Buscar</Label>
                <input
                    type="search"
                    value={searchInput}
                    onChange={(e) => setSearchInput(e.target.value)}
                    placeholder="Localidad o centro…"
                    className={inputCls}
                />
            </div>

            <div>
                <Label>Provincia</Label>
                <div className="space-y-1">
                    {PROVINCIAS.map((p) => (
                        <Checkbox key={p} label={p} checked={(filters.provincias ?? []).includes(p)} onChange={() => toggleArray('provincias', p)} />
                    ))}
                </div>
            </div>

            <div>
                <Label>Tipo de centro</Label>
                <div className="space-y-1">
                    {TIPOS_CENTRO.map((t) => (
                        <Checkbox key={t} label={t} checked={(filters.tiposCentro ?? []).includes(t)} onChange={() => toggleArray('tiposCentro', t)} />
                    ))}
                </div>
            </div>

            <div>
                <Label>Características</Label>
                <div className="flex flex-wrap gap-1.5">
                    {CARACTERISTICAS.map((c) => {
                        const on = (filters.caracteristicas ?? []).includes(c);
                        return (
                            <button
                                key={c}
                                type="button"
                                onClick={() => toggleArray('caracteristicas', c)}
                                className={clsx(
                                    'rounded-full px-2.5 py-1 text-[11px] font-semibold transition',
                                    on ? 'bg-brand-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'
                                )}
                            >
                                {c}
                            </button>
                        );
                    })}
                </div>
            </div>

            <div className="grid grid-cols-2 gap-3">
                <TriState label="Requisit lingüístic" value={filters.reqLing ?? ''} onChange={(v) => set('reqLing', v)} />
                <TriState label="Itinerant" value={filters.itinerante ?? ''} onChange={(v) => set('itinerante', v)} />
            </div>

            <div>
                <Label>Observaciones contienen</Label>
                <input
                    type="text"
                    value={obsInput}
                    onChange={(e) => setObsInput(e.target.value)}
                    placeholder='Ej. "difícil provisió"'
                    className={inputCls}
                />
            </div>

            <div>
                <Label>Distancia (km)</Label>
                <div className="flex items-center gap-2">
                    <input type="number" min="0" inputMode="numeric" value={filters.distMin ?? ''} onChange={(e) => set('distMin', e.target.value)} placeholder="mín" className={inputCls} />
                    <span className="text-slate-400">–</span>
                    <input type="number" min="0" inputMode="numeric" value={filters.distMax ?? ''} onChange={(e) => set('distMax', e.target.value)} placeholder="máx" className={inputCls} />
                </div>
            </div>

            <div>
                <Label>Tiempo máx. (min)</Label>
                <div className="flex items-center gap-2">
                    <input type="number" min="0" inputMode="numeric" value={filters.timeMax ?? ''} onChange={(e) => set('timeMax', e.target.value)} placeholder="Sin límite" className={inputCls} />
                    <select value={filters.timeMode ?? 'driving'} onChange={(e) => set('timeMode', e.target.value)} className={clsx(inputCls, 'w-auto')}>
                        {TRAVEL_MODES.map((m) => (
                            <option key={m.key} value={m.key}>{m.icon} {m.label}</option>
                        ))}
                    </select>
                </div>
                <p className="mt-1 text-[11px] text-slate-400">Calcula las distancias primero (arriba).</p>
            </div>

            <div>
                <Label>Estado en mi lista</Label>
                <div className="space-y-1">
                    {ESTADOS.map((e) => (
                        <Checkbox key={e.key} label={e.label} checked={(filters.estados ?? []).includes(e.key)} onChange={() => toggleArray('estados', e.key)} />
                    ))}
                </div>
                <p className="mt-1 text-[11px] text-slate-400">Sin marcar = todos los estados.</p>
            </div>

            <div>
                <Label>Ordenar por</Label>
                <select value={filters.sort ?? 'priority'} onChange={(e) => set('sort', e.target.value)} className={inputCls}>
                    <option value="priority">Mi prioridad</option>
                    <option value="distance">Distancia (más cerca)</option>
                    <option value="num">Número de vacante</option>
                    <option value="localidad">Localidad (A-Z)</option>
                    <option value="centro">Centro (A-Z)</option>
                </select>
            </div>

            <div className="rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs text-slate-500">
                <span className="font-semibold text-slate-800">{counts.total}</span> en el proceso ·{' '}
                <span className="font-semibold text-emerald-600">{counts.selected}</span> en lista ·{' '}
                <span className="font-semibold text-amber-600">{counts.revisar ?? 0}</span> a revisar ·{' '}
                <span className="font-semibold text-rose-500">{counts.discarded}</span> descartadas
            </div>
        </div>
    );
}

const inputCls = 'w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-brand-400 focus:outline-none focus:ring-1 focus:ring-brand-200';

function Label({ children }) {
    return <p className="mb-1.5 text-xs font-semibold uppercase tracking-wide text-slate-400">{children}</p>;
}

function Checkbox({ label, checked, onChange }) {
    return (
        <label className={clsx('flex cursor-pointer items-center gap-2 text-sm', checked ? 'text-slate-900' : 'text-slate-700')}>
            <input type="checkbox" checked={checked} onChange={onChange} className="rounded text-brand-600 focus:ring-brand-400" />
            {label}
        </label>
    );
}

// Indiferente / Sí / No selector.
function TriState({ label, value, onChange }) {
    const opts = [
        { v: '', t: '–' },
        { v: 'si', t: 'Sí' },
        { v: 'no', t: 'No' },
    ];
    return (
        <div>
            <Label>{label}</Label>
            <div className="inline-flex rounded-lg bg-slate-100 p-0.5">
                {opts.map((o) => (
                    <button
                        key={o.v}
                        type="button"
                        onClick={() => onChange(o.v)}
                        className={clsx(
                            'rounded-md px-2.5 py-1 text-xs font-semibold transition',
                            value === o.v ? 'bg-white text-brand-700 shadow-sm' : 'text-slate-500 hover:text-slate-700'
                        )}
                    >
                        {o.t}
                    </button>
                ))}
            </div>
        </div>
    );
}
