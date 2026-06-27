import { useState } from 'react';
import { useQuery, keepPreviousData } from '@tanstack/react-query';
import { Link } from 'react-router-dom';
import api from '../../lib/api';
import { useDebounce } from '../../hooks/useDebounce';

const TIPOS = ['', 'CEIP', 'IES', 'CEE', 'CIPFP', 'CRA', 'EI', 'CEP', 'CIFP', 'EOI', 'FPA', 'Otro'];
const PROVINCIAS = ['', 'Alacant', 'Castelló', 'València'];

export default function CentrosList() {
    const [filters, setFilters] = useState({ tipo: '', provincia: '', localidad: '', query: '' });
    const [page, setPage] = useState(1);
    const debouncedQuery = useDebounce(filters.query, 400);
    const debouncedLocalidad = useDebounce(filters.localidad, 400);

    const { data: profile } = useQuery({
        queryKey: ['profile'],
        queryFn: async () => (await api.get('/user/profile')).data,
        retry: false,
    });

    const { data, isFetching, isError, error } = useQuery({
        queryKey: ['centros', filters.tipo, filters.provincia, debouncedLocalidad, debouncedQuery, page],
        placeholderData: keepPreviousData,
        queryFn: async () => {
            const params = { page };
            if (filters.tipo) params.tipo = filters.tipo;
            if (filters.provincia) params.provincia = filters.provincia;
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

    const set = (k, v) => {
        setFilters((f) => ({ ...f, [k]: v }));
        setPage(1);
    };

    return (
        <div className="mx-auto max-w-5xl">
            <h1 className="mb-4 text-lg font-bold text-slate-800">Centros</h1>

            <div className="mb-4 grid grid-cols-1 gap-2 sm:grid-cols-4">
                <input
                    value={filters.query}
                    onChange={(e) => set('query', e.target.value)}
                    placeholder="Buscar por nombre…"
                    className="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-400 focus:ring-brand-400 sm:col-span-2"
                />
                <select value={filters.tipo} onChange={(e) => set('tipo', e.target.value)} className="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    {TIPOS.map((t) => <option key={t} value={t}>{t || 'Todos los tipos'}</option>)}
                </select>
                <select value={filters.provincia} onChange={(e) => set('provincia', e.target.value)} className="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    {PROVINCIAS.map((p) => <option key={p} value={p}>{p || 'Todas las provincias'}</option>)}
                </select>
                <input
                    value={filters.localidad}
                    onChange={(e) => set('localidad', e.target.value)}
                    placeholder="Localidad"
                    className="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-400 focus:ring-brand-400 sm:col-span-2"
                />
            </div>

            {isError ? (
                <p className="rounded-lg bg-rose-50 px-3 py-2 text-sm text-rose-600">
                    {error?.friendlyMessage ?? 'No se pudo cargar el directorio de centros.'}
                </p>
            ) : isFetching && centros.length === 0 ? (
                <p className="text-sm text-slate-400">Cargando…</p>
            ) : centros.length === 0 ? (
                <p className="rounded-2xl bg-white p-8 text-center text-sm text-slate-400 shadow-sm ring-1 ring-slate-200">
                    No se encontraron centros con esos filtros. Si el directorio está vacío, ejecuta <code>php artisan centros:import</code>.
                </p>
            ) : (
                <ul className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    {centros.map((c) => (
                        <li key={c.codigo}>
                            <Link
                                to={`/dashboard/centros/${c.codigo}`}
                                className="block rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200 transition hover:ring-brand-300"
                            >
                                <div className="flex items-start justify-between gap-2">
                                    <p className="text-sm font-semibold text-slate-800">{c.nombre}</p>
                                    <span className="rounded-full bg-brand-100 px-2 py-0.5 text-xs font-bold text-brand-700">{c.tipo}</span>
                                </div>
                                <p className="mt-1 text-xs text-slate-500">{c.localidad} · {c.provincia}</p>
                                <div className="mt-2 flex items-center justify-between text-xs text-slate-400">
                                    <span>{c.telefono ?? ''}</span>
                                    {c.distance_km != null && <span className="font-semibold text-brand-600">{c.distance_km} km</span>}
                                </div>
                            </Link>
                        </li>
                    ))}
                </ul>
            )}

            {lastPage > 1 && (
                <div className="mt-4 flex items-center justify-center gap-3">
                    <button onClick={() => setPage((p) => Math.max(1, p - 1))} disabled={page <= 1} className="rounded-lg bg-white px-3 py-1.5 text-sm ring-1 ring-slate-200 disabled:opacity-50">
                        Anterior
                    </button>
                    <span className="text-sm text-slate-500">Página {page} de {lastPage}</span>
                    <button onClick={() => setPage((p) => Math.min(lastPage, p + 1))} disabled={page >= lastPage} className="rounded-lg bg-white px-3 py-1.5 text-sm ring-1 ring-slate-200 disabled:opacity-50">
                        Siguiente
                    </button>
                </div>
            )}
        </div>
    );
}
