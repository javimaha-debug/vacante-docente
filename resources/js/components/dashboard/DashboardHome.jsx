import { useQuery } from '@tanstack/react-query';
import { Link } from 'react-router-dom';
import clsx from 'clsx';
import api from '../../lib/api';

const ESTADO_STYLES = {
    pendiente: 'bg-slate-100 text-slate-600',
    publicado: 'bg-green-100 text-green-700',
    cerrado: 'bg-red-100 text-red-700',
};

function EstadoBadge({ estado }) {
    return (
        <span className={clsx('rounded-full px-2 py-0.5 text-xs font-bold capitalize', ESTADO_STYLES[estado] ?? 'bg-slate-100 text-slate-600')}>
            {estado}
        </span>
    );
}

function Card({ title, action, children }) {
    return (
        <section className="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <div className="mb-3 flex items-center justify-between">
                <h2 className="text-sm font-bold text-slate-700">{title}</h2>
                {action}
            </div>
            {children}
        </section>
    );
}

function Empty({ children }) {
    return <p className="text-sm text-slate-400">{children}</p>;
}

export default function DashboardHome() {
    const { data, isLoading, isError, error } = useQuery({
        queryKey: ['dashboard'],
        queryFn: async () => (await api.get('/user/dashboard')).data,
    });

    const { data: noticias } = useQuery({
        queryKey: ['gva-noticias'],
        queryFn: async () => (await api.get('/gva/noticias')).data,
    });

    if (isLoading) {
        return <div className="flex h-40 items-center justify-center text-sm text-slate-400">Cargando…</div>;
    }

    if (isError) {
        return (
            <div className="mx-auto max-w-md rounded-2xl bg-rose-50 p-6 text-center text-sm text-rose-600">
                {error?.friendlyMessage ?? 'No se pudo cargar el panel.'}
            </div>
        );
    }

    const procesos = data?.procesos_activos ?? [];
    const especialidades = data?.mis_especialidades ?? [];
    const plazos = data?.proximos_plazos ?? [];
    const favoritas = data?.mis_vacantes_favoritas ?? [];
    const resumen = data?.resumen_historial ?? {};
    const novedades = (noticias?.data ?? []).slice(0, 5);

    return (
        <div className="mx-auto grid max-w-5xl grid-cols-1 gap-4 sm:grid-cols-2">
            <Card title="Procesos activos">
                {procesos.length === 0 ? (
                    <Empty>No hay procesos activos en este momento.</Empty>
                ) : (
                    <ul className="space-y-2">
                        {procesos.map((p) => (
                            <li key={p.id} className="flex items-center justify-between gap-2">
                                <span className="text-sm text-slate-700">{p.nombre}</span>
                                <EstadoBadge estado={p.estado} />
                            </li>
                        ))}
                    </ul>
                )}
            </Card>

            <Card title="Mi posición en bolsa">
                {especialidades.length === 0 ? (
                    <Empty>Aún no has añadido especialidades.</Empty>
                ) : (
                    <ul className="space-y-3">
                        {especialidades.map((e) => (
                            <li key={`${e.specialty_id}-${e.anyo}`} className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm font-medium text-slate-700">{e.specialty_name}</p>
                                    <p className="text-xs text-slate-400">Curso {e.anyo}</p>
                                </div>
                                <span className="text-2xl font-extrabold tabular-nums text-brand-600">
                                    {e.posicion_bolsa ?? '—'}
                                </span>
                            </li>
                        ))}
                    </ul>
                )}
            </Card>

            <Card title="Próximos plazos">
                {plazos.length === 0 ? (
                    <Empty>Sin fechas publicadas aún</Empty>
                ) : (
                    <ul className="space-y-2">
                        {plazos.slice(0, 5).map((pl, i) => (
                            <li key={i} className="flex items-center justify-between text-sm">
                                <span className="text-slate-600">{pl.proceso}</span>
                                <span className="font-semibold text-slate-800">{pl.fecha}</span>
                            </li>
                        ))}
                    </ul>
                )}
            </Card>

            <Card title="Mi último destino">
                {resumen?.ultimo_centro ? (
                    <div>
                        <p className="text-base font-semibold text-slate-800">{resumen.ultimo_centro}</p>
                        <p className="mt-1 text-sm text-slate-500">
                            {resumen.cursos_trabajados} curso(s) trabajados
                            {resumen.ultima_posicion != null && ` · última posición ${resumen.ultima_posicion}`}
                        </p>
                    </div>
                ) : (
                    <Empty>Todavía no hay historial de adjudicaciones.</Empty>
                )}
            </Card>

            <Card
                title="Mis vacantes guardadas"
                action={
                    <Link to="/dashboard/lista" className="text-xs font-semibold text-brand-600 hover:text-brand-700">
                        Ver lista →
                    </Link>
                }
            >
                <p className="text-3xl font-extrabold tabular-nums text-slate-800">{favoritas.length}</p>
                <p className="mt-1 text-sm text-slate-400">vacantes en tu lista priorizada</p>
            </Card>

            <Card
                title="Últimas novedades GVA"
                action={
                    <a
                        href="https://dogv.gva.es"
                        target="_blank"
                        rel="noreferrer"
                        className="text-xs font-semibold text-brand-600 hover:text-brand-700"
                    >
                        DOGV →
                    </a>
                }
            >
                {novedades.length === 0 ? (
                    <Empty>Sin novedades recientes.</Empty>
                ) : (
                    <ul className="space-y-2">
                        {novedades.map((n) => (
                            <li key={n.id} className="text-sm">
                                <a
                                    href={n.url}
                                    target="_blank"
                                    rel="noreferrer"
                                    className="text-slate-600 hover:text-brand-700 hover:underline"
                                >
                                    {n.titulo}
                                </a>
                                {n.fecha_publicacion && (
                                    <span className="ml-1 text-xs text-slate-400">· {n.fecha_publicacion}</span>
                                )}
                            </li>
                        ))}
                    </ul>
                )}
            </Card>
        </div>
    );
}
