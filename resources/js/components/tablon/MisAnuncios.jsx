import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Link } from 'react-router-dom';
import clsx from 'clsx';
import api from '../../lib/api';

const BADGES = {
    coche: 'bg-blue-100 text-blue-700',
    alojamiento: 'bg-purple-100 text-purple-700',
    centro: 'bg-amber-100 text-amber-700',
    general: 'bg-slate-100 text-slate-600',
};

export default function MisAnuncios() {
    const queryClient = useQueryClient();

    const { data, isLoading, isError, error } = useQuery({
        queryKey: ['mis-anuncios'],
        queryFn: async () => (await api.get('/tablon/mis-anuncios')).data,
    });

    const del = useMutation({
        mutationFn: async (id) => (await api.delete(`/tablon/${id}`)).data,
        onSuccess: () => queryClient.invalidateQueries({ queryKey: ['mis-anuncios'] }),
    });

    const anuncios = data?.data ?? [];

    return (
        <div className="mx-auto max-w-3xl">
            <div className="mb-4 flex items-center justify-between">
                <h1 className="text-lg font-bold text-slate-800">Mis anuncios</h1>
                <Link to="/dashboard/tablon" className="text-sm text-brand-600 hover:underline">← Volver al tablón</Link>
            </div>

            {isError ? (
                <p className="rounded-lg bg-rose-50 px-3 py-2 text-sm text-rose-600">
                    {error?.friendlyMessage ?? 'No se pudieron cargar tus anuncios.'}
                </p>
            ) : isLoading ? (
                <p className="text-sm text-slate-400">Cargando…</p>
            ) : anuncios.length === 0 ? (
                <p className="rounded-2xl bg-white p-8 text-center text-sm text-slate-400 shadow-sm ring-1 ring-slate-200">
                    Todavía no has publicado anuncios.
                </p>
            ) : (
                <ul className="space-y-3">
                    {anuncios.map((a) => (
                        <li key={a.id} className="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
                            <div className="flex items-start justify-between gap-2">
                                <span className={clsx('rounded-full px-2 py-0.5 text-xs font-bold', BADGES[a.categoria])}>{a.categoria}</span>
                                <div className="flex items-center gap-2">
                                    <span className="rounded-full bg-brand-100 px-2 py-0.5 text-xs font-bold text-brand-700">{a.contactos_count} contactos</span>
                                    <button onClick={() => del.mutate(a.id)} className="rounded-lg p-1.5 text-slate-400 hover:bg-red-50 hover:text-red-600" aria-label="Eliminar">✕</button>
                                </div>
                            </div>
                            <p className="mt-2 text-sm font-semibold text-slate-800">{a.titulo}</p>
                            <p className="mt-1 text-sm text-slate-600">{(a.contenido ?? '').slice(0, 150)}</p>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}
