import { useQuery } from '@tanstack/react-query';
import { Link } from 'react-router-dom';
import clsx from 'clsx';
import api from '../../lib/api';
import { typeMeta } from '../../lib/calendar';

const ESTADO_BOLSA_STYLES = {
    Activat: 'bg-green-100 text-green-700',
    Desactivat: 'bg-slate-100 text-slate-600',
    Adjudicat: 'bg-blue-100 text-blue-700',
};

function formatListadoDate(iso) {
    if (!iso) return null;
    try {
        return new Date(iso).toLocaleDateString('es-ES', { day: 'numeric', month: 'long', year: 'numeric' });
    } catch {
        return null;
    }
}

// Official position read from the LATEST participant listing for a proceso,
// via the authenticated /participantes/{proceso}/mi-posicion endpoint.
function MiPosicionCard({ proceso }) {
    const { data, isLoading, isError, error } = useQuery({
        queryKey: ['mi-posicion', proceso?.id],
        enabled: Boolean(proceso?.id),
        retry: false,
        queryFn: async () => (await api.get(`/participantes/${proceso.id}/mi-posicion`)).data,
    });

    if (!proceso) return null;

    const needsNombre = isError && error?.response?.status === 422;
    const adj = data?.adjudicacion;
    const listadoFecha = formatListadoDate(data?.listado_fecha ?? proceso.fecha);

    return (
        <section className="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200 sm:col-span-2">
            <div className="mb-3 flex items-start justify-between gap-2">
                <div>
                    <h2 className="text-sm font-bold text-slate-700">Mi posición en la lista — {proceso.nombre}</h2>
                    {listadoFecha && (
                        <p className="mt-0.5 text-[11px] text-slate-400">Según el listado del {listadoFecha}</p>
                    )}
                </div>
                {data?.found && data.estado && (
                    <span className={clsx('shrink-0 rounded-full px-2 py-0.5 text-xs font-bold', ESTADO_BOLSA_STYLES[data.estado] ?? 'bg-slate-100 text-slate-600')}>
                        {data.estado}
                    </span>
                )}
            </div>

            {isLoading ? (
                <p className="text-sm text-slate-400">Cargando…</p>
            ) : needsNombre ? (
                <p className="text-sm text-slate-500">
                    Configura tu <span className="font-semibold">Nombre GVA</span> en{' '}
                    <Link to="/dashboard/perfil" className="text-brand-600 hover:underline">tu perfil</Link>{' '}
                    para localizarte en la lista.
                </p>
            ) : isError ? (
                <p className="text-sm text-rose-600">{error?.friendlyMessage ?? 'No se pudo consultar tu posición.'}</p>
            ) : !data?.found ? (
                <p className="text-sm text-slate-400">No apareces en la lista publicada de este proceso (o aún no está cargada).</p>
            ) : (
                <div className="space-y-3">
                    {data.cambio && (
                        <p className="rounded-lg bg-amber-50 px-3 py-2 text-sm font-medium text-amber-800 ring-1 ring-amber-200">
                            📣 {data.cambio === 'nuevo' ? 'Apareces por primera vez en el último listado.' : 'Tu situación ha cambiado en el último listado.'}
                        </p>
                    )}
                    <div className="flex flex-wrap items-end gap-6">
                    <div>
                        <p className="text-xs text-slate-400">Posición</p>
                        <p className="text-3xl font-extrabold tabular-nums text-brand-600">{data.posicion ?? '—'}</p>
                    </div>
                    {adj && (
                        <div className="text-sm text-slate-600">
                            <p className="text-xs font-semibold uppercase text-blue-600">Adjudicación</p>
                            <p className="font-medium text-slate-800">{adj.centro_nombre ?? adj.lloc ?? '—'}</p>
                            <p className="text-xs text-slate-500">
                                {[adj.localitat, adj.especialidad_codigo, adj.jornada].filter(Boolean).join(' · ')}
                            </p>
                        </div>
                    )}
                    </div>
                </div>
            )}
        </section>
    );
}

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

function plazoFecha(d) {
    if (!d) return '';
    try { return new Date(d + 'T00:00:00').toLocaleDateString('es-ES', { day: 'numeric', month: 'long' }); }
    catch { return d; }
}

function ProximosPlazosCard({ eventos }) {
    return (
        <Card
            title="Próximos plazos"
            action={<Link to="/dashboard/calendario" className="text-xs font-semibold text-brand-600 hover:underline">Ver calendario →</Link>}
        >
            {eventos.length === 0 ? (
                <Empty>Sin fechas publicadas aún</Empty>
            ) : (
                <ul className="space-y-2">
                    {eventos.slice(0, 5).map((ev) => (
                        <li key={ev.id} className="flex items-center gap-2 text-sm">
                            <span className={`h-2.5 w-2.5 shrink-0 rounded-full ${typeMeta(ev.event_type).dot}`} />
                            <span className="flex-1 truncate text-slate-600">{ev.title}</span>
                            {ev.is_estimated && <span className="rounded-full bg-amber-100 px-1.5 py-0.5 text-[10px] font-semibold text-amber-700">Estimado</span>}
                            <span className="font-semibold text-slate-800">{plazoFecha(ev.event_date)}</span>
                        </li>
                    ))}
                </ul>
            )}
        </Card>
    );
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

    const { data: calendar } = useQuery({
        queryKey: ['calendar', 'dashboard'],
        queryFn: async () => (await api.get('/calendar')).data,
    });

    if (isLoading) {
        return (
            <div className="mx-auto grid max-w-5xl grid-cols-1 gap-4 sm:grid-cols-2" role="status" aria-label="Cargando panel">
                {Array.from({ length: 4 }).map((_, i) => (
                    <div key={i} className="animate-pulse rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
                        <div className="mb-4 h-4 w-1/3 rounded bg-slate-200" />
                        <div className="space-y-2">
                            <div className="h-3 w-full rounded bg-slate-100" />
                            <div className="h-3 w-5/6 rounded bg-slate-100" />
                            <div className="h-3 w-2/3 rounded bg-slate-100" />
                        </div>
                    </div>
                ))}
            </div>
        );
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
    const favoritas = data?.mis_vacantes_favoritas ?? [];
    const resumen = data?.resumen_historial ?? {};
    const info = data?.info ?? {};
    const historial = data?.historial ?? [];
    const actualizaciones = data?.actualizaciones ?? { items: [] };
    const novedades = (noticias?.data ?? []).slice(0, 5);
    // Read the official position from the LATEST participant listing; fall back
    // to a published proceso if no listing has been imported yet.
    const procesoListado = data?.proceso_listado ?? null;
    const procesoPublicado = procesoListado ?? procesos.find((p) => p.estado === 'publicado') ?? procesos[0] ?? null;

    return (
        <div className="mx-auto grid max-w-5xl grid-cols-1 gap-4 sm:grid-cols-2">
            <InfoCard info={info} />

            <MiPosicionCard proceso={procesoPublicado} />

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
                    <>
                        <ul className="space-y-3">
                            {especialidades.map((e) => (
                                <li key={`${e.specialty_id}-${e.anyo}`} className="flex items-center justify-between gap-2">
                                    <div className="min-w-0">
                                        <p className="truncate text-sm font-medium text-slate-700">{e.specialty_name}</p>
                                        <p className="text-xs text-slate-400">Curso {e.anyo}</p>
                                    </div>
                                    <div className="flex shrink-0 items-center gap-2">
                                        {e.estado_bolsa && (
                                            <span className={clsx('rounded-full px-2 py-0.5 text-[11px] font-bold', ESTADO_BOLSA_STYLES[e.estado_bolsa] ?? 'bg-slate-100 text-slate-600')}>
                                                {e.estado_bolsa}
                                            </span>
                                        )}
                                        <span className="text-2xl font-extrabold tabular-nums text-brand-600">
                                            {e.posicion_bolsa ?? '—'}
                                        </span>
                                    </div>
                                </li>
                            ))}
                        </ul>
                        {procesoListado?.fecha && (
                            <p className="mt-3 border-t border-slate-100 pt-2 text-[11px] text-slate-400">
                                Según el listado del {formatListadoDate(procesoListado.fecha)}
                            </p>
                        )}
                    </>
                )}
            </Card>

            <ProximosPlazosCard eventos={calendar?.upcoming ?? []} />

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

            <ActualizacionesCard actualizaciones={actualizaciones} />

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

            <AdjudicacionesContinuasCard />

            <HistorialSection historial={historial} />
        </div>
    );
}

// The user's weekly continuous-adjudication history (full width).
function AdjudicacionesContinuasCard() {
    const { data } = useQuery({
        queryKey: ['adjudicaciones-continuas'],
        queryFn: async () => (await api.get('/user/adjudicaciones-continuas')).data,
        retry: false,
    });

    const items = data?.data ?? [];
    if (items.length === 0) return null;

    return (
        <section className="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200 sm:col-span-2">
            <h2 className="mb-3 text-sm font-bold text-slate-700">Mis adjudicaciones semanales</h2>
            <ul className="divide-y divide-slate-100">
                {items.map((a, i) => (
                    <li key={i} className="flex flex-wrap items-baseline justify-between gap-2 py-2">
                        <div className="min-w-0">
                            <span className="text-sm font-semibold text-slate-800">{formatListadoDate(a.fecha)}</span>
                            <span className="ml-2 text-xs text-slate-400">{a.especialidad_codigo}</span>
                            {a.centro && (
                                <p className="text-xs text-slate-500">
                                    {a.centro}{a.localidad && ` (${a.localidad})`}{a.jornada && ` · ${a.jornada}`}
                                </p>
                            )}
                        </div>
                        <span className={clsx('shrink-0 rounded-full px-2 py-0.5 text-[11px] font-bold', ESTADO_BOLSA_STYLES[a.estado] ?? 'bg-slate-100 text-slate-600')}>
                            {a.estado ?? '—'}
                        </span>
                    </li>
                ))}
            </ul>
        </section>
    );
}

const ESTADO_HISTORIAL_STYLES = {
    Adjudicat: 'bg-blue-100 text-blue-700',
    Activat: 'bg-green-100 text-green-700',
    Desactivat: 'bg-slate-100 text-slate-600',
};

function formatUpdateDateTime(iso) {
    if (!iso) return null;
    try {
        return new Date(iso).toLocaleString('es-ES', { day: 'numeric', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit' });
    } catch {
        return null;
    }
}

// Recent listing changes (vacancies + participants) with the last-update date.
function ActualizacionesCard({ actualizaciones }) {
    const items = actualizaciones?.items ?? [];
    const ultima = formatUpdateDateTime(actualizaciones?.ultima_actualizacion);

    const parts = (it) => {
        const out = [];
        if (it.nuevas > 0) out.push(`${it.nuevas} nuevas`);
        if (it.modificadas > 0) out.push(`${it.modificadas} modificadas`);
        if (it.eliminadas > 0) out.push(`${it.eliminadas} eliminadas`);
        return out.join(' · ');
    };

    return (
        <Card title="Modificaciones recientes">
            {ultima && (
                <p className="mb-2 text-xs text-slate-500">
                    Última actualización: <span className="font-semibold text-slate-700">{ultima}</span>
                </p>
            )}
            {items.length === 0 ? (
                <Empty>Sin cambios recientes en los listados.</Empty>
            ) : (
                <ul className="space-y-2">
                    {items.map((it, i) => (
                        <li key={i} className="flex items-start gap-2 text-sm">
                            <span className={clsx('mt-0.5 rounded-full px-1.5 py-0.5 text-[10px] font-bold uppercase', it.tipo === 'participantes' ? 'bg-violet-100 text-violet-700' : 'bg-emerald-100 text-emerald-700')}>
                                {it.tipo === 'participantes' ? 'Bolsa' : 'Vacantes'}
                            </span>
                            <div className="min-w-0">
                                <p className="truncate text-slate-700" title={it.proceso}>{it.proceso}</p>
                                <p className="text-xs text-slate-500">
                                    {parts(it)}
                                    {it.fecha && <span className="text-slate-400"> · {formatListadoDate(it.fecha)}</span>}
                                </p>
                            </div>
                        </li>
                    ))}
                </ul>
            )}
        </Card>
    );
}

// Personal info summary card (full width).
function InfoCard({ info }) {
    const rows = [
        ['Nombre', info.name],
        ['Correo', info.email],
        ['Nombre GVA', info.nombre_gva || '— (configúralo en Mi Perfil)'],
        ['Colectivo', [info.colectivo, info.cuerpo].filter(Boolean).join(' · ') || '—'],
        ['Comunidad', info.ccaa || '—'],
        ['Domicilio', info.direccion_origen || '—'],
        ['Especialidades', info.num_especialidades ?? 0],
        ['Miembro desde', info.miembro_desde || '—'],
    ];

    return (
        <section className="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200 sm:col-span-2">
            <div className="mb-3 flex items-center justify-between">
                <h2 className="text-sm font-bold text-slate-700">Mi información</h2>
                <Link to="/dashboard/perfil" className="text-xs font-semibold text-brand-600 hover:text-brand-700">
                    Editar perfil →
                </Link>
            </div>
            <dl className="grid grid-cols-1 gap-x-6 gap-y-2 sm:grid-cols-2">
                {rows.map(([label, value]) => (
                    <div key={label} className="flex items-baseline justify-between gap-3 border-b border-slate-50 pb-1">
                        <dt className="text-xs font-medium uppercase tracking-wide text-slate-400">{label}</dt>
                        <dd className="truncate text-right text-sm text-slate-700" title={String(value)}>{value}</dd>
                    </div>
                ))}
            </dl>
        </section>
    );
}

// Full adjudication history as a timeline (full width).
function HistorialSection({ historial }) {
    return (
        <section className="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200 sm:col-span-2">
            <h2 className="mb-3 text-sm font-bold text-slate-700">Historial de adjudicaciones</h2>
            {historial.length === 0 ? (
                <p className="text-sm text-slate-400">
                    Aún no hay historial. Se irá completando a medida que participes en los procesos.
                </p>
            ) : (
                <ol className="relative space-y-4 border-l border-slate-200 pl-5">
                    {historial.map((h) => (
                        <li key={h.id} className="relative">
                            <span className="absolute -left-[1.42rem] top-1.5 h-2.5 w-2.5 rounded-full bg-brand-500 ring-2 ring-white" />
                            <div className="flex flex-wrap items-baseline justify-between gap-2">
                                <span className="text-sm font-bold text-slate-800">{h.curso ?? h.anyo}</span>
                                {h.estado && (
                                    <span className={`rounded-full px-2 py-0.5 text-[11px] font-bold ${ESTADO_HISTORIAL_STYLES[h.estado] ?? 'bg-slate-100 text-slate-600'}`}>
                                        {h.estado}
                                    </span>
                                )}
                            </div>
                            <p className="text-sm text-slate-700">
                                {h.especialidad ?? '—'}
                                {h.posicion_definitiva != null && (
                                    <span className="text-slate-400"> · posición {h.posicion_definitiva}</span>
                                )}
                            </p>
                            {h.centro && (
                                <p className="text-xs text-slate-500">
                                    {h.centro}
                                    {h.localidad && ` (${h.localidad})`}
                                    {h.jornada && ` · ${h.jornada}`}
                                </p>
                            )}
                            {h.proceso && <p className="text-[11px] text-slate-400">{h.proceso}</p>}
                        </li>
                    ))}
                </ol>
            )}
        </section>
    );
}
