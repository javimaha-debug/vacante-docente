import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api from '../../lib/api';
import { SkeletonRows, ErrorState, Badge } from './ui';

export default function AdminSistema() {
    const qc = useQueryClient();

    const status = useQuery({
        queryKey: ['admin', 'sistema', 'status'],
        queryFn: async () => (await api.get('/superadmin/sistema/status')).data,
    });
    const logs = useQuery({
        queryKey: ['admin', 'sistema', 'logs'],
        queryFn: async () => (await api.get('/superadmin/sistema/logs')).data,
    });
    const failed = useQuery({
        queryKey: ['admin', 'sistema', 'failed'],
        queryFn: async () => (await api.get('/superadmin/sistema/failed-jobs')).data,
    });
    const cacheClear = useMutation({
        mutationFn: () => api.post('/superadmin/sistema/cache-clear'),
        onSuccess: () => qc.invalidateQueries({ queryKey: ['admin', 'sistema'] }),
    });

    return (
        <div className="space-y-6">
            {/* Status */}
            <section>
                <h3 className="mb-2 text-sm font-semibold text-slate-300">Estado</h3>
                {status.isLoading ? <SkeletonRows rows={2} />
                    : status.isError ? <ErrorState error={status.error} onRetry={status.refetch} />
                    : (
                        <div className="grid grid-cols-2 gap-3 md:grid-cols-4">
                            <Stat label="Entorno" value={status.data.app.env} />
                            <Stat label="PHP" value={status.data.app.php} />
                            <Stat label="Base de datos" value={status.data.database.ok ? 'OK' : 'Error'} tone={status.data.database.ok ? 'green' : 'red'} />
                            <Stat label="Jobs fallidos" value={status.data.queue.failed_jobs} tone={status.data.queue.failed_jobs > 0 ? 'amber' : 'green'} />
                        </div>
                    )}
            </section>

            <button
                onClick={() => cacheClear.mutate()}
                disabled={cacheClear.isPending}
                className="rounded-lg bg-sky-600 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-700 disabled:opacity-40"
            >
                {cacheClear.isPending ? 'Limpiando…' : 'Limpiar caché'}
            </button>
            {cacheClear.isSuccess && <p className="text-xs text-emerald-400">Caché limpiada.</p>}

            {/* Failed jobs */}
            <section>
                <h3 className="mb-2 text-sm font-semibold text-slate-300">Jobs fallidos</h3>
                {failed.isLoading ? <SkeletonRows rows={3} />
                    : failed.isError ? <ErrorState error={failed.error} onRetry={failed.refetch} />
                    : failed.data.data.length === 0 ? <p className="text-sm text-slate-500">No hay jobs fallidos.</p>
                    : (
                        <ul className="space-y-2 text-sm">
                            {failed.data.data.map((j) => (
                                <li key={j.id} className="rounded-lg bg-slate-900/50 p-3">
                                    <div className="flex items-center justify-between">
                                        <span className="font-medium text-slate-200">{j.job}</span>
                                        <span className="text-xs text-slate-500">{j.failed_at}</span>
                                    </div>
                                    <p className="mt-1 truncate text-xs text-rose-300/80">{j.exception}</p>
                                </li>
                            ))}
                        </ul>
                    )}
            </section>

            {/* Logs */}
            <section>
                <h3 className="mb-2 text-sm font-semibold text-slate-300">Logs recientes</h3>
                {logs.isLoading ? <SkeletonRows rows={4} />
                    : logs.isError ? <ErrorState error={logs.error} onRetry={logs.refetch} />
                    : (
                        <pre className="scroll-thin max-h-96 overflow-auto rounded-lg bg-black/40 p-3 text-xs text-slate-400">
                            {(logs.data.lines ?? []).join('\n') || 'Sin entradas.'}
                        </pre>
                    )}
            </section>
        </div>
    );
}

function Stat({ label, value, tone }) {
    return (
        <div className="rounded-xl border border-slate-700/60 bg-slate-800/50 p-3">
            <p className="text-xs uppercase tracking-wide text-slate-500">{label}</p>
            {tone ? <div className="mt-1"><Badge tone={tone}>{value}</Badge></div> : <p className="mt-1 font-semibold text-slate-200">{value}</p>}
        </div>
    );
}
