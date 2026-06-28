import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import api from '../../lib/api';
import { KpiCard, SkeletonRows, ErrorState, Badge } from './ui';

const ESTADO_TONE = { ok: 'green', error: 'red', sin_proceso: 'amber', pendiente: 'slate' };
const ESTADO_LABEL = { ok: 'Importado', error: 'Error', sin_proceso: 'Sin proceso', pendiente: 'Pendiente' };

function fechaCorta(iso) {
    if (!iso) return '—';
    try { return new Date(iso).toLocaleString('es-ES', { dateStyle: 'short', timeStyle: 'short' }); } catch { return iso; }
}

export default function AdminImportaciones() {
    const qc = useQueryClient();
    const { data, isLoading, isError, error, refetch } = useQuery({
        queryKey: ['admin', 'importaciones'],
        queryFn: async () => (await api.get('/superadmin/importaciones/health')).data,
    });

    const runMonitor = useMutation({
        mutationFn: () => api.post('/superadmin/importaciones/run-monitor'),
        onSuccess: ({ data }) => qc.setQueryData(['admin', 'importaciones'], data.health),
    });

    if (isLoading) return <SkeletonRows rows={6} className="h-16" />;
    if (isError) return <ErrorState error={error} onRetry={refetch} />;

    const r = data.resumen;
    const staleMs = r.ultima_deteccion ? Date.now() - new Date(r.ultima_deteccion).getTime() : null;
    const stale = staleMs == null || staleMs > 1000 * 60 * 60 * 36; // >36h sin detectar

    return (
        <div className="space-y-6">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 className="text-lg font-bold text-slate-100">Importaciones GVA</h2>
                    <p className="text-sm text-slate-400">
                        Última detección: <span className={stale ? 'text-amber-400' : 'text-emerald-400'}>{fechaCorta(r.ultima_deteccion)}</span>
                        {stale && <span className="ml-2 text-amber-400">⚠ el monitor no detecta nada hace tiempo</span>}
                    </p>
                </div>
                <button
                    onClick={() => runMonitor.mutate()}
                    disabled={runMonitor.isPending}
                    className="rounded-lg bg-sky-600 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-700 disabled:opacity-50"
                >
                    {runMonitor.isPending ? 'Ejecutando monitor…' : '▶ Ejecutar monitor ahora'}
                </button>
            </div>

            {runMonitor.isError && <ErrorState error={runMonitor.error} />}
            {runMonitor.isSuccess && (
                <p className="rounded-lg bg-emerald-500/10 px-3 py-2 text-sm text-emerald-300">Monitor ejecutado correctamente.</p>
            )}

            <div className="grid grid-cols-2 gap-3 md:grid-cols-5">
                <KpiCard label="Detectadas" value={r.total} />
                <KpiCard label="Importadas" value={r.importadas} accent="text-emerald-400" />
                <KpiCard label="Pendientes" value={r.pendientes} accent="text-sky-400" />
                <KpiCard label="Sin proceso" value={r.sin_proceso} accent="text-amber-400" />
                <KpiCard label="Errores" value={r.errores} accent={r.errores > 0 ? 'text-rose-400' : 'text-slate-300'} />
            </div>

            {/* Recent imports */}
            <section>
                <h3 className="mb-2 text-sm font-semibold text-slate-300">Últimas importaciones</h3>
                {data.importaciones.length === 0 ? (
                    <p className="text-sm text-slate-500">Aún no se ha importado ningún listado.</p>
                ) : (
                    <div className="overflow-x-auto rounded-xl border border-slate-700/60">
                        <table className="min-w-full divide-y divide-slate-700/60 text-sm">
                            <thead className="bg-slate-800/60 text-left text-xs uppercase tracking-wide text-slate-400">
                                <tr><th className="px-4 py-2">Tipo</th><th className="px-4 py-2">Proceso</th><th className="px-4 py-2">Cambios</th><th className="px-4 py-2">Fecha</th></tr>
                            </thead>
                            <tbody className="divide-y divide-slate-800">
                                {data.importaciones.map((i, idx) => (
                                    <tr key={idx} className="hover:bg-slate-800/40">
                                        <td className="px-4 py-2"><Badge tone="blue">{i.tipo}</Badge></td>
                                        <td className="px-4 py-2 text-slate-300">{i.proceso ?? '—'}</td>
                                        <td className="px-4 py-2 text-xs text-slate-400">
                                            {i.tipo === 'continua'
                                                ? `${i.total} filas`
                                                : `${i.total ?? 0} tot · +${i.nuevas ?? 0} / ~${i.modificadas ?? 0} / -${i.eliminadas ?? 0}`}
                                        </td>
                                        <td className="px-4 py-2 text-xs text-slate-500">{fechaCorta(i.fecha)}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </section>

            {/* Detected notices */}
            <section>
                <h3 className="mb-2 text-sm font-semibold text-slate-300">Listados detectados por el monitor</h3>
                {data.noticias.length === 0 ? (
                    <p className="text-sm text-slate-500">El monitor aún no ha detectado listados. Pulsa «Ejecutar monitor ahora».</p>
                ) : (
                    <ul className="space-y-2 text-sm">
                        {data.noticias.map((n) => (
                            <li key={n.id} className="rounded-lg border border-slate-700/60 bg-slate-800/40 p-3">
                                <div className="flex items-center justify-between gap-2">
                                    <span className="min-w-0 truncate font-medium text-slate-200" title={n.titulo}>{n.titulo}</span>
                                    <Badge tone={ESTADO_TONE[n.estado] ?? 'slate'}>{ESTADO_LABEL[n.estado] ?? n.estado}</Badge>
                                </div>
                                {n.resumen && <p className="mt-1 text-xs text-slate-400">{n.resumen}</p>}
                                <div className="mt-1 flex items-center justify-between text-[11px] text-slate-500">
                                    <a href={n.url} target="_blank" rel="noreferrer" className="truncate text-sky-400 hover:underline">{n.url}</a>
                                    <span className="ml-2 shrink-0">{fechaCorta(n.detectado_en)}</span>
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
            </section>
        </div>
    );
}
