import { useQuery } from '@tanstack/react-query';
import { Link } from 'react-router-dom';
import api from '../../lib/api';

function fecha(d) {
    if (!d) return null;
    try { return new Date(d).toLocaleDateString('es-ES'); } catch { return d; }
}

function EstadoBadge({ estado }) {
    const tone = estado === 'Adjudicat'
        ? 'bg-emerald-100 text-emerald-700'
        : estado === 'No adjudicat' || estado === 'Desactivat'
            ? 'bg-rose-100 text-rose-700'
            : 'bg-slate-100 text-slate-600';
    return <span className={`rounded-full px-2 py-0.5 text-xs font-semibold ${tone}`}>{estado ?? '—'}</span>;
}

export default function MiPosicion() {
    const { data, isLoading, isError, error, refetch } = useQuery({
        queryKey: ['mis-listados'],
        queryFn: async () => (await api.get('/user/mis-listados')).data,
    });

    if (isLoading) {
        return <div className="mx-auto max-w-3xl"><p className="text-sm text-slate-400">Buscándote en los listados…</p></div>;
    }
    if (isError) {
        return (
            <div className="mx-auto max-w-3xl">
                <p className="rounded-lg bg-rose-50 px-3 py-2 text-sm text-rose-600">{error?.friendlyMessage ?? 'No se pudo cargar.'}</p>
                <button onClick={() => refetch()} className="mt-2 text-sm font-semibold text-brand-600">Reintentar</button>
            </div>
        );
    }

    // Needs nombre_gva configured.
    if (!data.configured) {
        return (
            <div className="mx-auto max-w-md rounded-2xl bg-white p-8 text-center shadow-sm ring-1 ring-slate-200">
                <div className="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-brand-100 text-2xl">📍</div>
                <h1 className="mt-3 text-lg font-bold text-slate-800">¿Estoy en las listas?</h1>
                <p className="mt-1 text-sm text-slate-500">{data.message}</p>
                <Link to="/dashboard/perfil" className="mt-4 inline-block rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">
                    Configurar mi nombre GVA
                </Link>
            </div>
        );
    }

    const { procesos = [], continuas = [] } = data;
    const nada = procesos.length === 0 && continuas.length === 0;

    return (
        <div className="mx-auto max-w-3xl space-y-5">
            <div>
                <h1 className="text-lg font-bold text-slate-800">Mi posición en las listas</h1>
                <p className="text-sm text-slate-500">Buscando como <span className="font-semibold text-slate-700">{data.nombre_gva}</span> · <Link to="/dashboard/perfil" className="text-brand-600 hover:underline">cambiar</Link></p>
            </div>

            {nada && (
                <div className="rounded-2xl bg-white p-8 text-center shadow-sm ring-1 ring-slate-200">
                    <p className="text-sm text-slate-500">No apareces todavía en ningún listado importado.</p>
                    <p className="mt-1 text-xs text-slate-400">Cuando la GVA publique las listas y se importen, aparecerás aquí con tu posición. Revisa que tu nombre coincida exactamente con el oficial.</p>
                </div>
            )}

            {/* Procesos (listas de adjudicación / participantes) */}
            {procesos.map((p) => (
                <div key={p.proceso_id} className="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
                    <div className="flex flex-wrap items-start justify-between gap-2">
                        <div>
                            <h2 className="font-semibold text-slate-800">{p.proceso}</h2>
                            {p.listado_fecha && <p className="text-xs text-slate-400">Listado del {fecha(p.listado_fecha)}</p>}
                        </div>
                        <EstadoBadge estado={p.estado} />
                    </div>

                    <div className="mt-3 flex flex-wrap items-center gap-4">
                        <div>
                            <p className="text-xs uppercase tracking-wide text-slate-400">Posición</p>
                            <p className="text-2xl font-bold text-brand-700">{p.posicion ?? '—'}</p>
                        </div>
                        {p.especialidad_codigo && (
                            <div>
                                <p className="text-xs uppercase tracking-wide text-slate-400">Especialidad</p>
                                <p className="font-semibold text-slate-700">{p.especialidad_codigo}</p>
                            </div>
                        )}
                    </div>

                    {p.adjudicacion && (
                        <div className="mt-3 rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-800">
                            ✅ <span className="font-semibold">Adjudicado</span>: {p.adjudicacion.centro_nombre}
                            {p.adjudicacion.localitat ? ` · ${p.adjudicacion.localitat}` : ''}
                            {p.adjudicacion.jornada ? ` · ${p.adjudicacion.jornada}` : ''}
                        </div>
                    )}

                    {p.otras?.length > 1 && (
                        <div className="mt-3 border-t border-slate-100 pt-2">
                            <p className="text-xs font-medium text-slate-400">También apareces en:</p>
                            <ul className="mt-1 space-y-0.5 text-sm text-slate-600">
                                {p.otras.map((o, i) => (
                                    <li key={i}>Esp. {o.especialidad_codigo} — posición {o.posicion ?? '—'} ({o.estado ?? '—'})</li>
                                ))}
                            </ul>
                        </div>
                    )}
                </div>
            ))}

            {/* Adjudicaciones contínues semanales */}
            {continuas.length > 0 && (
                <div className="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
                    <h2 className="font-semibold text-slate-800">Adjudicaciones semanales (contínues)</h2>
                    <ul className="mt-2 divide-y divide-slate-100 text-sm">
                        {continuas.map((c, i) => (
                            <li key={i} className="flex flex-wrap items-center justify-between gap-2 py-2">
                                <span className="text-slate-600">
                                    {fecha(c.fecha)} · {c.especialidad_codigo ?? c.cuerpo}
                                    {c.centro ? ` · ${c.centro}` : ''}
                                    {c.localitat ? ` (${c.localitat})` : ''}
                                </span>
                                <span className="flex items-center gap-2">
                                    {c.posicion != null && <span className="text-xs text-slate-400">pos. {c.posicion}</span>}
                                    <EstadoBadge estado={c.estado} />
                                </span>
                            </li>
                        ))}
                    </ul>
                </div>
            )}
        </div>
    );
}
