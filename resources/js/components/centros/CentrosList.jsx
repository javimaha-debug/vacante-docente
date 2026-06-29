import { useState, useCallback } from 'react';
import { useQuery, keepPreviousData } from '@tanstack/react-query';
import { Link } from 'react-router-dom';
import api from '../../lib/api';
import { useDebounce } from '../../hooks/useDebounce';

const normalizeUrl = (url) => (!url ? url : (/^https?:\/\//i.test(url) ? url : `https://${url}`));
const isCeice = (url) => Boolean(url) && url.toLowerCase().includes('ceice.gva.es');

const TIPOS = ['CEIP', 'IES', 'CEE', 'CIPFP', 'CRA', 'EI', 'CEP', 'CIFP', 'EOI', 'FPA', 'Otro'];
const PROVINCIAS = ['Alacant', 'Castelló', 'València'];

const CARAC_LABELS = {
    JORNADA_CONTINUA: 'Jornada contínua',
    CRA: 'CRA',
    SINGULAR: 'Centre singular',
    UECO: 'Aula UECO',
    EDUCACIO_ESPECIAL: 'Educació especial',
    FPA: 'FPA',
    PENITENCIARI: 'Penitenciari',
};

function CopiarWebButton({ url }) {
    const [copied, setCopied] = useState(false);
    const copy = (e) => {
        e.preventDefault();
        e.stopPropagation();
        navigator.clipboard.writeText(normalizeUrl(url)).then(() => {
            setCopied(true);
            setTimeout(() => setCopied(false), 2000);
        });
    };
    return (
        <button
            type="button"
            onClick={copy}
            title="ceice.gva.es puede no estar accesible — copiar URL al portapapeles"
            className="flex items-center gap-1 rounded px-1.5 py-0.5 text-xs text-amber-600 hover:bg-amber-50 transition"
        >
            {copied ? '✓ Copiado' : '🔗 Copiar'}
        </button>
    );
}

function WebButton({ web }) {
    if (!web) return null;
    if (isCeice(web)) return <CopiarWebButton url={web} />;
    return (
        <button
            type="button"
            onClick={(e) => {
                e.preventDefault();
                e.stopPropagation();
                window.open(normalizeUrl(web), '_blank', 'noopener');
            }}
            title="Ir a la web oficial"
            aria-label="Ir a la web oficial"
            className="text-brand-600 hover:text-brand-700 text-base leading-none"
        >
            🌐
        </button>
    );
}

export default function CentrosList() {
    const [query, setQuery] = useState('');
    const [localidad, setLocalidad] = useState('');
    const [tipos, setTipos] = useState([]);
    const [provincias, setProvincias] = useState([]);
    const [caracteristica, setCaracteristica] = useState('');
    const [page, setPage] = useState(1);
    const [view, setView] = useState('list');

    const debouncedQuery = useDebounce(query, 400);
    const debouncedLocalidad = useDebounce(localidad, 400);

    const { data: profile } = useQuery({
        queryKey: ['profile'],
        queryFn: async () => (await api.get('/user/profile')).data,
        retry: false,
    });

    const { data, isFetching, isError, error } = useQuery({
        queryKey: ['centros', tipos, provincias, caracteristica, debouncedLocalidad, debouncedQuery, page],
        placeholderData: keepPreviousData,
        queryFn: async () => {
            const params = { page };
            if (tipos.length === 1) params.tipo = tipos[0];
            if (provincias.length === 1) params.provincia = provincias[0];
            if (caracteristica) params.caracteristica = caracteristica;
            if (debouncedLocalidad) params.localidad = debouncedLocalidad;
            if (debouncedQuery) params.query = debouncedQuery;
            if (profile?.lat_origen && profile?.lng_origen) {
                params.lat = profile.lat_origen;
                params.lng = profile.lng_origen;
            }
            return (await api.get('/centros', { params })).data;
        },
    });

    const centros = data?.data ?? [];
    const lastPage = data?.last_page ?? 1;
    const total = data?.total ?? 0;

    const toggleTipo = useCallback((t) => {
        setTipos((prev) => prev.includes(t) ? prev.filter((x) => x !== t) : [...prev, t]);
        setPage(1);
    }, []);

    const toggleProvincia = useCallback((p) => {
        setProvincias((prev) => prev.includes(p) ? prev.filter((x) => x !== p) : [...prev, p]);
        setPage(1);
    }, []);

    const clearAll = () => {
        setQuery('');
        setLocalidad('');
        setTipos([]);
        setProvincias([]);
        setCaracteristica('');
        setPage(1);
    };

    const hasFilters = query || localidad || tipos.length > 0 || provincias.length > 0 || caracteristica;

    return (
        <div className="flex gap-6">
            {/* Left sidebar — hidden on small screens */}
            <aside className="hidden lg:block w-60 shrink-0">
                <div className="sticky top-4 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200 space-y-5">
                    <div>
                        <label className="text-xs font-semibold uppercase tracking-wide text-slate-400">Buscar</label>
                        <input
                            value={query}
                            onChange={(e) => { setQuery(e.target.value); setPage(1); }}
                            placeholder="Nombre del centro…"
                            className="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-brand-400 focus:outline-none focus:ring-1 focus:ring-brand-400"
                        />
                    </div>
                    <div>
                        <label className="text-xs font-semibold uppercase tracking-wide text-slate-400">Localidad</label>
                        <input
                            value={localidad}
                            onChange={(e) => { setLocalidad(e.target.value); setPage(1); }}
                            placeholder="Localidad…"
                            className="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-brand-400 focus:outline-none focus:ring-1 focus:ring-brand-400"
                        />
                    </div>
                    <div>
                        <p className="text-xs font-semibold uppercase tracking-wide text-slate-400 mb-2">Provincia</p>
                        {PROVINCIAS.map((p) => (
                            <label key={p} className="flex items-center gap-2 py-0.5 cursor-pointer select-none">
                                <input
                                    type="checkbox"
                                    checked={provincias.includes(p)}
                                    onChange={() => toggleProvincia(p)}
                                    className="rounded border-slate-300 text-brand-600 focus:ring-brand-400"
                                />
                                <span className="text-sm text-slate-700">{p}</span>
                            </label>
                        ))}
                    </div>
                    <div>
                        <p className="text-xs font-semibold uppercase tracking-wide text-slate-400 mb-2">Tipo de centro</p>
                        {TIPOS.map((t) => (
                            <label key={t} className="flex items-center gap-2 py-0.5 cursor-pointer select-none">
                                <input
                                    type="checkbox"
                                    checked={tipos.includes(t)}
                                    onChange={() => toggleTipo(t)}
                                    className="rounded border-slate-300 text-brand-600 focus:ring-brand-400"
                                />
                                <span className="text-sm text-slate-700">{t}</span>
                            </label>
                        ))}
                    </div>
                    <div>
                        <label className="text-xs font-semibold uppercase tracking-wide text-slate-400">Característica</label>
                        <select
                            value={caracteristica}
                            onChange={(e) => { setCaracteristica(e.target.value); setPage(1); }}
                            className="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-brand-400 focus:outline-none"
                        >
                            <option value="">Todas</option>
                            {Object.entries(CARAC_LABELS).map(([v, l]) => (
                                <option key={v} value={v}>{l}</option>
                            ))}
                        </select>
                    </div>
                    {total > 0 && (
                        <p className="text-xs text-slate-400 text-center">{isFetching ? '…' : `${total} centros`}</p>
                    )}
                    {hasFilters && (
                        <button
                            onClick={clearAll}
                            className="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-500 hover:bg-slate-50 transition"
                        >
                            Limpiar filtros
                        </button>
                    )}
                </div>
            </aside>

            {/* Main area */}
            <div className="flex-1 min-w-0">
                <div className="mb-4 flex items-center justify-between gap-2">
                    <div className="flex items-center gap-2">
                        <h1 className="font-heading text-xl font-bold text-slate-800">Centros</h1>
                        {total > 0 && (
                            <span className="text-sm font-normal text-slate-400">{isFetching ? '…' : total}</span>
                        )}
                    </div>
                    <div className="flex items-center gap-2">
                        {/* Mobile search (hidden on lg) */}
                        <input
                            value={query}
                            onChange={(e) => { setQuery(e.target.value); setPage(1); }}
                            placeholder="Buscar…"
                            className="lg:hidden rounded-lg border border-slate-200 px-3 py-1.5 text-sm w-36 focus:border-brand-400 focus:outline-none"
                        />
                        <div className="flex rounded-lg bg-slate-100 p-0.5">
                            <button
                                type="button"
                                onClick={() => setView('grid')}
                                className={`rounded-md px-3 py-1 text-xs font-semibold transition ${view === 'grid' ? 'bg-white text-brand-700 shadow' : 'text-slate-500 hover:text-slate-800'}`}
                            >
                                ▦
                            </button>
                            <button
                                type="button"
                                onClick={() => setView('list')}
                                className={`rounded-md px-3 py-1 text-xs font-semibold transition ${view === 'list' ? 'bg-white text-brand-700 shadow' : 'text-slate-500 hover:text-slate-800'}`}
                            >
                                ☰
                            </button>
                        </div>
                    </div>
                </div>

                {isError ? (
                    <p className="rounded-lg bg-rose-50 px-3 py-2 text-sm text-rose-600">
                        {error?.friendlyMessage ?? 'No se pudo cargar el directorio de centros.'}
                    </p>
                ) : isFetching && centros.length === 0 ? (
                    <p className="text-sm text-slate-400">Cargando…</p>
                ) : centros.length === 0 ? (
                    <p className="rounded-2xl bg-white p-8 text-center text-sm text-slate-400 shadow-sm ring-1 ring-slate-200">
                        No se encontraron centros con esos filtros.
                    </p>
                ) : view === 'list' ? (
                    <div className={`rounded-2xl bg-white shadow-sm ring-1 ring-slate-200 overflow-hidden ${isFetching ? 'opacity-70' : ''}`}>
                        {centros.map((c, idx) => (
                            <Link
                                key={c.codigo}
                                to={`/dashboard/centros/${c.codigo}`}
                                style={{ minHeight: '56px' }}
                                className={`flex items-center gap-3 px-4 py-3 transition hover:bg-slate-50 ${idx > 0 ? 'border-t border-slate-100' : ''}`}
                            >
                                <div className="flex-1 min-w-0">
                                    <div className="flex items-center gap-2 min-w-0">
                                        <p className="truncate text-sm font-semibold text-slate-800">{c.nombre}</p>
                                        <span className="shrink-0 rounded-full bg-brand-100 px-2 py-0.5 text-xs font-bold text-brand-700">{c.tipo}</span>
                                    </div>
                                    <p className="text-xs text-slate-400">{c.localidad} · {c.provincia}</p>
                                </div>
                                <div className="shrink-0 flex items-center gap-3 text-xs text-slate-400">
                                    {c.telefono && <span>{c.telefono}</span>}
                                    <WebButton web={c.web} />
                                    {c.distance_km != null && <span className="font-semibold text-brand-600">{c.distance_km} km</span>}
                                    <span className="text-slate-300">›</span>
                                </div>
                            </Link>
                        ))}
                    </div>
                ) : (
                    <ul className={`grid grid-cols-1 gap-3 sm:grid-cols-2 ${isFetching ? 'opacity-70' : ''}`}>
                        {centros.map((c) => (
                            <li key={c.codigo}>
                                <Link
                                    to={`/dashboard/centros/${c.codigo}`}
                                    className="block rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200 transition hover:ring-brand-300"
                                >
                                    <div className="flex items-start justify-between gap-2">
                                        <p className="text-sm font-semibold text-slate-800">{c.nombre}</p>
                                        <span className="shrink-0 rounded-full bg-brand-100 px-2 py-0.5 text-xs font-bold text-brand-700">{c.tipo}</span>
                                    </div>
                                    <p className="mt-1 text-xs text-slate-500">{c.localidad} · {c.provincia}</p>
                                    {(c.caracteristicas ?? []).some((k) => CARAC_LABELS[k]) && (
                                        <div className="mt-2 flex flex-wrap gap-1">
                                            {(c.caracteristicas ?? [])
                                                .filter((k) => CARAC_LABELS[k])
                                                .map((k) => (
                                                    <span key={k} className="rounded-full bg-emerald-100 px-2 py-0.5 text-[11px] font-medium text-emerald-700">
                                                        {CARAC_LABELS[k]}
                                                    </span>
                                                ))}
                                        </div>
                                    )}
                                    <div className="mt-2 flex items-center justify-between text-xs text-slate-400">
                                        <span className="flex items-center gap-2">
                                            {c.telefono ?? ''}
                                            <WebButton web={c.web} />
                                        </span>
                                        {c.distance_km != null && <span className="font-semibold text-brand-600">{c.distance_km} km</span>}
                                    </div>
                                </Link>
                            </li>
                        ))}
                    </ul>
                )}

                {lastPage > 1 && (
                    <div className="mt-4 flex items-center justify-center gap-3">
                        <button
                            onClick={() => setPage((p) => Math.max(1, p - 1))}
                            disabled={page <= 1}
                            className="rounded-lg bg-white px-3 py-1.5 text-sm ring-1 ring-slate-200 disabled:opacity-50"
                        >
                            Anterior
                        </button>
                        <span className="text-sm text-slate-500">Página {page} de {lastPage}</span>
                        <button
                            onClick={() => setPage((p) => Math.min(lastPage, p + 1))}
                            disabled={page >= lastPage}
                            className="rounded-lg bg-white px-3 py-1.5 text-sm ring-1 ring-slate-200 disabled:opacity-50"
                        >
                            Siguiente
                        </button>
                    </div>
                )}
            </div>
        </div>
    );
}
