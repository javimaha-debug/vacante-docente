import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api from '../../lib/api';
import { SkeletonRows, ErrorState, Badge, KpiCard } from './ui';

const CUERPOS = ['', 'maestros', 'secundaria', 'fp', 'otros'];

export default function AdminTemarios() {
    const qc = useQueryClient();
    const [cuerpo, setCuerpo] = useState('');
    const [search, setSearch] = useState('');
    const [expanded, setExpanded] = useState(null); // temario id

    const { data: stats } = useQuery({
        queryKey: ['admin', 'temarios', 'stats'],
        queryFn: async () => (await api.get('/superadmin/temarios/stats')).data,
    });

    const { data, isLoading, isError, error, refetch } = useQuery({
        queryKey: ['admin', 'temarios', { cuerpo, search }],
        queryFn: async () => (await api.get('/superadmin/temarios', {
            params: { cuerpo: cuerpo || undefined, search: search || undefined },
        })).data,
    });

    const refresh = () => {
        qc.invalidateQueries({ queryKey: ['admin', 'temarios'] });
    };

    const syncBoe = useMutation({
        mutationFn: async () => (await api.post('/superadmin/temarios/sync-boe')).data,
        onSuccess: refresh,
    });

    const fmt = (iso) => iso ? new Date(iso).toLocaleString('es-ES') : 'nunca';

    return (
        <div className="space-y-4">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <h2 className="text-base font-semibold text-slate-200">Temarios oficiales</h2>
                <button onClick={() => syncBoe.mutate()} disabled={syncBoe.isPending} className="rounded-lg border border-slate-700 px-3 py-1.5 text-sm font-medium text-slate-200 hover:bg-slate-800 disabled:opacity-50">
                    {syncBoe.isPending ? 'Sincronizando…' : 'Sincronizar BOE'}
                </button>
            </div>

            <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                <KpiCard label="Especialidades" value={stats?.total_especialidades ?? '—'} />
                <KpiCard label="Temas" value={stats?.total_temas ?? '—'} />
                <KpiCard label="% con esquema" value={stats ? `${stats.pct_esquema}%` : '—'} accent="text-emerald-300" />
                <KpiCard label="Coste última IA" value={stats?.coste_estimado_usd != null ? `$${stats.coste_estimado_usd}` : '—'} sub={stats?.ultima_generacion ? fmt(stats.ultima_generacion) : null} />
            </div>

            <div className="flex flex-wrap items-center gap-2">
                <select value={cuerpo} onChange={(e) => setCuerpo(e.target.value)} className="rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-slate-100">
                    {CUERPOS.map((c) => <option key={c} value={c}>{c || 'Todos los cuerpos'}</option>)}
                </select>
                <input value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Buscar especialidad…" className="rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-slate-100" />
                <span className="text-xs text-slate-500">BOE sync: {fmt(stats?.last_sync_boe)}</span>
            </div>

            {isLoading ? (
                <SkeletonRows rows={6} />
            ) : isError ? (
                <ErrorState error={error} onRetry={refetch} />
            ) : (
                <div className="overflow-x-auto rounded-xl border border-slate-700/60">
                    <table className="min-w-full divide-y divide-slate-700/60 text-sm">
                        <thead className="bg-slate-800/60 text-left text-xs uppercase tracking-wide text-slate-400">
                            <tr>
                                <th className="px-4 py-2">Especialidad</th>
                                <th className="px-4 py-2">Cuerpo</th>
                                <th className="px-4 py-2">Temas</th>
                                <th className="px-4 py-2">Orden</th>
                                <th className="px-4 py-2">% IA</th>
                                <th className="px-4 py-2"></th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-800">
                            {data.data.map((t) => (
                                <RowGroup key={t.id} temario={t} expanded={expanded === t.id} onToggle={() => setExpanded(expanded === t.id ? null : t.id)} onChanged={refresh} />
                            ))}
                            {data.data.length === 0 && (
                                <tr><td colSpan={6} className="px-4 py-6 text-center text-slate-500">Sin temarios. Pulsa «Sincronizar BOE».</td></tr>
                            )}
                        </tbody>
                    </table>
                </div>
            )}
        </div>
    );
}

function RowGroup({ temario, expanded, onToggle, onChanged }) {
    const qc = useQueryClient();
    const regenerate = useMutation({
        mutationFn: async () => (await api.post(`/superadmin/temarios/${temario.id}/regenerate`)).data,
    });

    const { data: temasData } = useQuery({
        queryKey: ['admin', 'temarios', temario.id, 'temas'],
        queryFn: async () => (await api.get(`/superadmin/temarios/${temario.id}/temas`)).data,
        enabled: expanded,
    });

    const refreshTemas = () => qc.invalidateQueries({ queryKey: ['admin', 'temarios', temario.id, 'temas'] });

    return (
        <>
            <tr className="hover:bg-slate-800/40">
                <td className="px-4 py-2">
                    <button onClick={onToggle} className="text-left font-medium text-slate-200 hover:text-sky-300">
                        {expanded ? '▾ ' : '▸ '}{temario.especialidad_nombre}
                    </button>
                    <p className="text-xs text-slate-500">{temario.especialidad_code}</p>
                </td>
                <td className="px-4 py-2 capitalize text-slate-300">{temario.cuerpo}</td>
                <td className="px-4 py-2 text-slate-300">{temario.total_temas}</td>
                <td className="px-4 py-2 text-xs text-slate-500">{temario.source_order ?? '—'}</td>
                <td className="px-4 py-2"><Badge tone={temario.pct_enriquecido >= 100 ? 'green' : temario.pct_enriquecido > 0 ? 'amber' : 'slate'}>{temario.pct_enriquecido}%</Badge></td>
                <td className="px-4 py-2 text-right">
                    <button onClick={() => regenerate.mutate()} disabled={regenerate.isPending} className="text-xs font-medium text-sky-400 hover:text-sky-300 disabled:opacity-50">
                        {regenerate.isPending ? 'Encolando…' : 'Regenerar IA'}
                    </button>
                </td>
            </tr>
            {expanded && (
                <tr className="bg-slate-900/60">
                    <td colSpan={6} className="px-4 py-3">
                        {!temasData ? (
                            <p className="text-xs text-slate-500">Cargando temas…</p>
                        ) : (
                            <ul className="space-y-1">
                                {temasData.data.map((tema) => <TemaEditRow key={tema.id} tema={tema} onChanged={refreshTemas} />)}
                            </ul>
                        )}
                    </td>
                </tr>
            )}
        </>
    );
}

function TemaEditRow({ tema, onChanged }) {
    const [editing, setEditing] = useState(false);
    const [titulo, setTitulo] = useState(tema.titulo);
    const save = useMutation({
        mutationFn: async () => (await api.patch(`/superadmin/temas-oficiales/${tema.id}`, { titulo })).data,
        onSuccess: () => { setEditing(false); onChanged(); },
    });

    return (
        <li className="flex items-center gap-2 text-sm">
            <span className="w-8 shrink-0 text-right text-xs text-slate-500">{tema.numero}.</span>
            {editing ? (
                <>
                    <input value={titulo} onChange={(e) => setTitulo(e.target.value)} className="flex-1 rounded border border-slate-700 bg-slate-800 px-2 py-1 text-slate-100" />
                    <button onClick={() => save.mutate()} className="text-xs text-emerald-400">Guardar</button>
                    <button onClick={() => { setEditing(false); setTitulo(tema.titulo); }} className="text-xs text-slate-400">Cancelar</button>
                </>
            ) : (
                <>
                    <span className="flex-1 text-slate-300">{tema.titulo}</span>
                    {tema.enriquecido
                        ? <span className="rounded-full bg-emerald-500/20 px-1.5 py-0.5 text-[10px] font-bold text-emerald-300">IA ✓</span>
                        : <span className="rounded-full bg-slate-700/60 px-1.5 py-0.5 text-[10px] font-bold text-slate-400">sin IA</span>}
                    <button onClick={() => setEditing(true)} className="text-xs text-sky-400 hover:text-sky-300">Editar</button>
                </>
            )}
        </li>
    );
}
