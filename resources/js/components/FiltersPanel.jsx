import { useEffect, useState } from 'react';
import clsx from 'clsx';

const PROVINCIAS = ['Todas', 'Alacant', 'Castelló', 'València'];
const TIPOS = ['Secundaria', 'Primaria/Infantil', 'Otro'];
const TAGS = ['Difícil provisión', 'CRA', 'Centre singular', 'Req. lingüístico'];

export default function FiltersPanel({ filters, setFilters, counts, showDiscarded, setShowDiscarded }) {
    // Local search state debounced into the shared filters (300ms).
    const [searchInput, setSearchInput] = useState(filters.search ?? '');

    useEffect(() => {
        const id = setTimeout(() => {
            setFilters((f) => ({ ...f, search: searchInput.trim() }));
        }, 300);
        return () => clearTimeout(id);
    }, [searchInput, setFilters]);

    const toggleArray = (key, value) => {
        setFilters((f) => {
            const list = f[key] ?? [];
            return {
                ...f,
                [key]: list.includes(value) ? list.filter((v) => v !== value) : [...list, value],
            };
        });
    };

    return (
        <div className="space-y-5">
            <div>
                <Label>Buscar</Label>
                <input
                    type="search"
                    value={searchInput}
                    onChange={(e) => setSearchInput(e.target.value)}
                    placeholder="Localidad o centro…"
                    className="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-brand-400 focus:outline-none focus:ring-1 focus:ring-brand-200"
                />
            </div>

            <div>
                <Label>Provincia</Label>
                <div className="space-y-1">
                    {PROVINCIAS.map((p) => {
                        const value = p === 'Todas' ? '' : p;
                        return (
                            <label key={p} className="flex cursor-pointer items-center gap-2 text-sm text-slate-700">
                                <input
                                    type="radio"
                                    name="provincia"
                                    checked={(filters.provincia ?? '') === value}
                                    onChange={() => setFilters((f) => ({ ...f, provincia: value }))}
                                    className="text-brand-600 focus:ring-brand-400"
                                />
                                {p}
                            </label>
                        );
                    })}
                </div>
            </div>

            <div>
                <Label>Tipo de centro</Label>
                <div className="space-y-1">
                    {TIPOS.map((t) => (
                        <Checkbox
                            key={t}
                            label={t}
                            checked={(filters.tiposCentro ?? []).includes(t)}
                            onChange={() => toggleArray('tiposCentro', t)}
                        />
                    ))}
                </div>
            </div>

            <div>
                <Label>Etiquetas</Label>
                <div className="space-y-1">
                    {TAGS.map((t) => (
                        <Checkbox
                            key={t}
                            label={t}
                            checked={(filters.tags ?? []).includes(t)}
                            onChange={() => toggleArray('tags', t)}
                        />
                    ))}
                </div>
            </div>

            <div>
                <Label>Características</Label>
                <div className="space-y-1">
                    <Checkbox
                        label="Requisit lingüístic"
                        checked={Boolean(filters.reqLing)}
                        onChange={() => setFilters((f) => ({ ...f, reqLing: !f.reqLing }))}
                    />
                    <Checkbox
                        label="Itinerant"
                        checked={Boolean(filters.itinerante)}
                        onChange={() => setFilters((f) => ({ ...f, itinerante: !f.itinerante }))}
                    />
                </div>
            </div>

            <div>
                <Label>Distancia máxima (km)</Label>
                <input
                    type="number"
                    min="0"
                    inputMode="numeric"
                    value={filters.maxDistance ?? ''}
                    onChange={(e) => setFilters((f) => ({ ...f, maxDistance: e.target.value }))}
                    placeholder="Sin límite"
                    className="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-brand-400 focus:outline-none focus:ring-1 focus:ring-brand-200"
                />
                <p className="mt-1 text-[11px] text-slate-400">Calcula las distancias primero (arriba).</p>
            </div>

            <div>
                <Label>Ordenar por</Label>
                <select
                    value={filters.sort ?? 'priority'}
                    onChange={(e) => setFilters((f) => ({ ...f, sort: e.target.value }))}
                    className="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-brand-400 focus:outline-none focus:ring-1 focus:ring-brand-200"
                >
                    <option value="priority">Mi prioridad</option>
                    <option value="distance">Distancia (más cerca)</option>
                    <option value="num">Número de vacante</option>
                    <option value="localidad">Localidad (A-Z)</option>
                    <option value="centro">Centro (A-Z)</option>
                </select>
            </div>

            <label className="flex cursor-pointer items-center justify-between rounded-lg bg-slate-50 px-3 py-2 text-sm font-medium text-slate-700">
                Mostrar descartadas
                <input
                    type="checkbox"
                    checked={showDiscarded}
                    onChange={(e) => setShowDiscarded(e.target.checked)}
                    className="rounded text-brand-600 focus:ring-brand-400"
                />
            </label>

            <div className="rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs text-slate-500">
                <span className="font-semibold text-slate-800">{counts.total}</span> vacantes ·{' '}
                <span className="font-semibold text-emerald-600">{counts.selected}</span> en lista ·{' '}
                <span className="font-semibold text-amber-600">{counts.revisar ?? 0}</span> a revisar ·{' '}
                <span className="font-semibold text-rose-500">{counts.discarded}</span> descartadas
            </div>
        </div>
    );
}

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
