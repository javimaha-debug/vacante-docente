import { useState } from 'react';
import { useQuery, keepPreviousData } from '@tanstack/react-query';
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

function ProcesoCard({ p }) {
    return (
        <div className="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <div className="flex flex-wrap items-start justify-between gap-2">
                <div>
                    <h3 className="font-semibold text-slate-800">{p.proceso}</h3>
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
                    <p className="text-xs font-medium text-slate-400">También aparece en:</p>
                    <ul className="mt-1 space-y-0.5 text-sm text-slate-600">
                        {p.otras.map((o, i) => (
                            <li key={i}>Esp. {o.especialidad_codigo} — posición {o.posicion ?? '—'} ({o.estado ?? '—'})</li>
                        ))}
                    </ul>
                </div>
            )}
        </div>
    );
}

function PersonaResultado({ persona, isOwn }) {
    const { procesos = [], continuas = [] } = persona;
    if (procesos.length === 0 && continuas.length === 0) return null;

    return (
        <div className="space-y-3">
            <div className="flex items-center gap-2">
                <h2 className="text-base font-bold text-slate-800">{persona.nombre_gva}</h2>
                {isOwn && <span className="rounded-full bg-brand-100 px-2 py-0.5 text-[11px] font-semibold text-brand-700">Tú</span>}
            </div>

            {procesos.map((p) => <ProcesoCard key={p.proceso_id} p={p} />)}

            {continuas.length > 0 && (
                <div className="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
                    <h3 className="font-semibold text-slate-800">Adjudicaciones semanales (contínues)</h3>
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

export default function MiPosicion() {
    // `input` is the live text box; `query` is the submitted search (empty = my name).
    const [input, setInput] = useState('');
    const [query, setQuery] = useState('');

    const { data, isLoading, isFetching, isError, error, refetch } = useQuery({
        queryKey: ['mis-listados', query],
        queryFn: async () => (await api.get('/user/mis-listados', { params: query ? { q: query } : {} })).data,
        placeholderData: keepPreviousData,
    });

    const submit = (e) => {
        e.preventDefault();
        setQuery(input.trim());
    };
    const clear = () => { setInput(''); setQuery(''); };

    const ownName = data?.nombre_gva ?? null;
    const isSearch = Boolean(data?.is_search);
    const resultados = data?.resultados ?? [];

    return (
        <div className="mx-auto max-w-3xl space-y-5">
            <div>
                <h1 className="text-lg font-bold text-slate-800">Buscar en las listas</h1>
                <p className="text-sm text-slate-500">
                    Consulta la posición de cualquier persona en los listados importados.
                    {ownName && !isSearch && <> Mostrando tu posición como <span className="font-semibold text-slate-700">{ownName}</span>.</>}
                </p>
            </div>

            {/* Search box: any name. Empty search falls back to your own name. */}
            <form onSubmit={submit} className="flex flex-wrap items-center gap-2">
                <div className="relative flex-1 min-w-[16rem]">
                    <span className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400">🔎</span>
                    <input
                        value={input}
                        onChange={(e) => setInput(e.target.value)}
                        placeholder="Apellidos, Nombre (p. ej. GARCIA LOPEZ, ANA)"
                        className="w-full rounded-lg border border-slate-200 py-2 pl-9 pr-3 text-sm focus:border-brand-400 focus:outline-none focus:ring-1 focus:ring-brand-400"
                    />
                </div>
                <button type="submit" className="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">
                    Buscar
                </button>
                {query && (
                    <button type="button" onClick={clear} className="rounded-lg px-3 py-2 text-sm font-medium text-slate-500 hover:bg-slate-100">
                        {ownName ? 'Ver mi posición' : 'Limpiar'}
                    </button>
                )}
            </form>

            {!ownName && (
                <p className="rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-700">
                    Configura tu nombre GVA en <Link to="/dashboard/perfil" className="font-semibold underline">tu perfil</Link> para
                    ver tu posición automáticamente sin tener que buscarte.
                </p>
            )}

            {isLoading && <p className="text-sm text-slate-400">Buscando en los listados…</p>}

            {isError && (
                <div>
                    <p className="rounded-lg bg-rose-50 px-3 py-2 text-sm text-rose-600">{error?.friendlyMessage ?? 'No se pudo cargar.'}</p>
                    <button onClick={() => refetch()} className="mt-2 text-sm font-semibold text-brand-600">Reintentar</button>
                </div>
            )}

            {!isLoading && !isError && (
                <div className={`space-y-6 ${isFetching ? 'opacity-60' : ''}`}>
                    {resultados.length === 0 && (
                        <div className="rounded-2xl bg-white p-8 text-center shadow-sm ring-1 ring-slate-200">
                            <p className="text-sm text-slate-500">
                                {isSearch
                                    ? `No se encontró «${data?.query}» en ningún listado importado.`
                                    : 'No apareces todavía en ningún listado importado.'}
                            </p>
                            <p className="mt-1 text-xs text-slate-400">
                                Revisa que el nombre coincida con el formato oficial (APELLIDOS, NOMBRE). Cuando la GVA publique
                                nuevas listas y se importen, aparecerán aquí.
                            </p>
                        </div>
                    )}

                    {data?.truncated && (
                        <p className="text-xs text-slate-400">Mostrando los primeros {data.total_personas} resultados. Afina la búsqueda para ver menos.</p>
                    )}

                    {resultados.map((persona, i) => (
                        <PersonaResultado
                            key={`${persona.nombre_gva}-${i}`}
                            persona={persona}
                            isOwn={!isSearch && ownName != null && persona.nombre_gva?.toLowerCase() === ownName.toLowerCase()}
                        />
                    ))}
                </div>
            )}
        </div>
    );
}
