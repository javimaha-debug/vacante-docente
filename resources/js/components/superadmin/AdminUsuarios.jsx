import { useState } from 'react';
import { useQuery, keepPreviousData } from '@tanstack/react-query';
import { useNavigate } from 'react-router-dom';
import api from '../../lib/api';
import { SkeletonRows, ErrorState, Badge, statusTone, enumLabel } from './ui';

const PLANES = ['', 'free', 'interino', 'opositor', 'docente_pro', 'todo_en_uno'];

export default function AdminUsuarios() {
    const navigate = useNavigate();
    const [search, setSearch] = useState('');
    const [plan, setPlan] = useState('');
    const [estado, setEstado] = useState('');
    const [page, setPage] = useState(1);

    const { data, isLoading, isError, error, refetch, isFetching } = useQuery({
        queryKey: ['admin', 'usuarios', { search, plan, estado, page }],
        queryFn: async () => (await api.get('/superadmin/usuarios', {
            params: { search: search || undefined, plan: plan || undefined, estado: estado || undefined, page },
        })).data,
        placeholderData: keepPreviousData,
    });

    return (
        <div className="space-y-4">
            <div className="flex flex-wrap items-center gap-2">
                <input
                    value={search}
                    onChange={(e) => { setSearch(e.target.value); setPage(1); }}
                    placeholder="Buscar nombre, email o nombre GVA…"
                    className="min-w-0 flex-1 rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-slate-100 placeholder:text-slate-500"
                />
                <select value={plan} onChange={(e) => { setPlan(e.target.value); setPage(1); }} className={selectCls}>
                    {PLANES.map((p) => <option key={p} value={p}>{p ? p : 'Todos los planes'}</option>)}
                </select>
                <select value={estado} onChange={(e) => { setEstado(e.target.value); setPage(1); }} className={selectCls}>
                    <option value="">Todos</option>
                    <option value="activo">Activos</option>
                    <option value="suspendido">Suspendidos</option>
                </select>
            </div>

            {isLoading ? (
                <SkeletonRows rows={8} />
            ) : isError ? (
                <ErrorState error={error} onRetry={refetch} />
            ) : (
                <>
                    <div className="overflow-x-auto rounded-xl border border-slate-700/60">
                        <table className="min-w-full divide-y divide-slate-700/60 text-sm">
                            <thead className="bg-slate-800/60 text-left text-xs uppercase tracking-wide text-slate-400">
                                <tr>
                                    <th className="px-4 py-2">Usuario</th>
                                    <th className="px-4 py-2">Plan</th>
                                    <th className="px-4 py-2">Estado</th>
                                    <th className="px-4 py-2">Rol</th>
                                    <th className="px-4 py-2">Última act.</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-800">
                                {data.data.map((u) => (
                                    <tr
                                        key={u.id}
                                        onClick={() => navigate(`/superadmin/usuarios/${u.id}`)}
                                        className="cursor-pointer hover:bg-slate-800/40"
                                    >
                                        <td className="px-4 py-2">
                                            <p className="font-medium text-slate-200">{u.name}</p>
                                            <p className="text-xs text-slate-500">{u.email}</p>
                                        </td>
                                        <td className="px-4 py-2"><Badge tone={u.is_paid ? 'green' : 'slate'}>{u.plan_label}</Badge></td>
                                        <td className="px-4 py-2">
                                            {u.suspended
                                                ? <Badge tone="red">Suspendido</Badge>
                                                : <Badge tone={statusTone(u.plan_status)}>{enumLabel('plan_status', u.plan_status)}</Badge>}
                                        </td>
                                        <td className="px-4 py-2 text-slate-400">{enumLabel('role', u.role)}</td>
                                        <td className="px-4 py-2 text-xs text-slate-500">
                                            {u.last_active_at ? new Date(u.last_active_at).toLocaleDateString('es-ES') : '—'}
                                        </td>
                                    </tr>
                                ))}
                                {data.data.length === 0 && (
                                    <tr><td colSpan={5} className="px-4 py-6 text-center text-slate-500">Sin resultados.</td></tr>
                                )}
                            </tbody>
                        </table>
                    </div>

                    <div className="flex items-center justify-between text-sm text-slate-400">
                        <span>{data.meta.total} usuarios{isFetching ? ' · actualizando…' : ''}</span>
                        <div className="flex items-center gap-2">
                            <button disabled={page <= 1} onClick={() => setPage((p) => p - 1)} className={pagerCls}>← Anterior</button>
                            <span>{data.meta.current_page} / {data.meta.last_page}</span>
                            <button disabled={page >= data.meta.last_page} onClick={() => setPage((p) => p + 1)} className={pagerCls}>Siguiente →</button>
                        </div>
                    </div>
                </>
            )}
        </div>
    );
}

const selectCls = 'rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-slate-100';
const pagerCls = 'rounded-lg border border-slate-700 px-3 py-1.5 text-xs font-medium text-slate-300 hover:bg-slate-800 disabled:opacity-40';
