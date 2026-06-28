import { useState } from 'react';
import { useQuery, keepPreviousData } from '@tanstack/react-query';
import api from '../../lib/api';
import { SkeletonRows, ErrorState, Badge, statusTone } from './ui';

export default function AdminSuscripciones() {
    const [status, setStatus] = useState('');
    const [page, setPage] = useState(1);

    const { data, isLoading, isError, error, refetch } = useQuery({
        queryKey: ['admin', 'suscripciones', { status, page }],
        queryFn: async () => (await api.get('/superadmin/suscripciones', {
            params: { status: status || undefined, page },
        })).data,
        placeholderData: keepPreviousData,
    });

    return (
        <div className="space-y-4">
            <div className="flex items-center gap-2">
                <select value={status} onChange={(e) => { setStatus(e.target.value); setPage(1); }} className="rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-slate-100">
                    <option value="">Todos los estados</option>
                    <option value="active">Activas</option>
                    <option value="trialing">En prueba</option>
                    <option value="past_due">Pago pendiente</option>
                    <option value="canceled">Canceladas</option>
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
                                    <th className="px-4 py-2">Renueva</th>
                                    <th className="px-4 py-2">Stripe</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-800">
                                {data.data.map((s) => (
                                    <tr key={s.id} className="hover:bg-slate-800/40">
                                        <td className="px-4 py-2">
                                            <p className="font-medium text-slate-200">{s.usuario ?? '—'}</p>
                                            <p className="text-xs text-slate-500">{s.email}</p>
                                        </td>
                                        <td className="px-4 py-2 text-slate-300">{s.plan_codigo}</td>
                                        <td className="px-4 py-2"><Badge tone={statusTone(s.status)}>{s.status}</Badge></td>
                                        <td className="px-4 py-2 text-xs text-slate-500">
                                            {s.current_period_end ? new Date(s.current_period_end).toLocaleDateString('es-ES') : '—'}
                                            {s.cancel_at_period_end && <span className="ml-1 text-amber-400">(cancela)</span>}
                                        </td>
                                        <td className="px-4 py-2 text-xs text-slate-500">{s.stripe_subscription_id ?? '—'}</td>
                                    </tr>
                                ))}
                                {data.data.length === 0 && (
                                    <tr><td colSpan={5} className="px-4 py-6 text-center text-slate-500">Sin suscripciones.</td></tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                    <div className="flex items-center justify-between text-sm text-slate-400">
                        <span>{data.meta.total} suscripciones</span>
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

const pagerCls = 'rounded-lg border border-slate-700 px-3 py-1.5 text-xs font-medium text-slate-300 hover:bg-slate-800 disabled:opacity-40';
