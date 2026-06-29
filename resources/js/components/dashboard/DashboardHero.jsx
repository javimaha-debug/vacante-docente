import { useQuery } from '@tanstack/react-query';
import { Link } from 'react-router-dom';
import api from '../../lib/api';
import { useAuth } from '../../hooks/useAuth';

export default function DashboardHero() {
    const { user } = useAuth();

    const { data } = useQuery({
        queryKey: ['dashboard-hero'],
        queryFn: async () => (await api.get('/user/hero')).data,
        staleTime: 5 * 60 * 1000,
    });

    const greeting = data?.greeting ?? 'Hola';
    const nombre = data?.nombre ?? user?.name?.split(' ')[0] ?? '';
    const fecha = data?.fecha_texto ?? '';
    const bolsa = data?.stats?.bolsa ?? {};
    const oposicion = data?.stats?.oposicion ?? {};
    const adj = data?.adjudicacion_proxima;

    return (
        <div className="sm:col-span-2 space-y-4">
            {/* Greeting */}
            <div>
                {fecha && <p className="text-xs font-medium uppercase tracking-wide text-slate-400 mb-0.5">{fecha}</p>}
                <h1 className="font-heading text-2xl font-bold text-slate-900">
                    {greeting}{nombre ? `, ${nombre}` : ''}
                </h1>
            </div>

            {/* Adjudicación countdown banner */}
            {adj && adj.dias_restantes != null && adj.dias_restantes <= 30 && (
                <div className="rounded-xl bg-teal-900 px-4 py-3 text-white">
                    <div className="flex items-center justify-between gap-3">
                        <div>
                            <p className="text-xs font-semibold uppercase tracking-wide text-teal-300">Próxima adjudicación</p>
                            <p className="font-semibold">{adj.titulo}</p>
                        </div>
                        <div className="text-right shrink-0">
                            <p className="text-3xl font-black tabular-nums">{adj.dias_restantes}</p>
                            <p className="text-xs text-teal-300">días</p>
                        </div>
                    </div>
                </div>
            )}

            {/* Mode cards */}
            <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                {/* Bolsa */}
                <Link
                    to="/dashboard/vacantes"
                    className="block rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200 transition hover:ring-brand-300"
                >
                    <div className="flex items-center justify-between mb-2">
                        <span className="text-xs font-semibold uppercase tracking-wide text-slate-400">Bolsa</span>
                        <span className="text-lg">📋</span>
                    </div>
                    {bolsa.total_especialidades != null ? (
                        <>
                            <p className="text-2xl font-extrabold tabular-nums text-brand-600">{bolsa.posicion_mejor ?? '—'}</p>
                            <p className="text-xs text-slate-500 mt-0.5">
                                {bolsa.total_especialidades} especialidad{bolsa.total_especialidades !== 1 ? 'es' : ''}
                                {bolsa.estado_mejor ? ` · ${bolsa.estado_mejor}` : ''}
                            </p>
                        </>
                    ) : (
                        <p className="text-sm text-slate-400">Sin posición registrada</p>
                    )}
                </Link>

                {/* Oposición */}
                <Link
                    to="/dashboard/oposicion"
                    className="block rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200 transition hover:ring-brand-300"
                >
                    <div className="flex items-center justify-between mb-2">
                        <span className="text-xs font-semibold uppercase tracking-wide text-slate-400">Oposición</span>
                        <span className="text-lg">🎓</span>
                    </div>
                    {oposicion.temas_total > 0 ? (
                        <>
                            <p className="text-2xl font-extrabold tabular-nums text-brand-600">{oposicion.temas_dominados ?? 0}</p>
                            <p className="text-xs text-slate-500 mt-0.5">temas dominados de {oposicion.temas_total}</p>
                            <div className="mt-2 h-1.5 rounded-full bg-slate-100">
                                <div
                                    className="h-1.5 rounded-full bg-brand-500"
                                    style={{ width: `${Math.round(((oposicion.temas_dominados ?? 0) / oposicion.temas_total) * 100)}%` }}
                                />
                            </div>
                        </>
                    ) : (
                        <p className="text-sm text-slate-400">Sin temas preparados</p>
                    )}
                </Link>

                {/* Docente — coming soon */}
                <div className="rounded-2xl bg-slate-50 p-4 ring-1 ring-slate-200 opacity-60 cursor-default">
                    <div className="flex items-center justify-between mb-2">
                        <span className="text-xs font-semibold uppercase tracking-wide text-slate-400">Docente</span>
                        <span className="text-lg">👩‍🏫</span>
                    </div>
                    <p className="text-sm text-slate-400">Próximamente</p>
                </div>
            </div>
        </div>
    );
}
