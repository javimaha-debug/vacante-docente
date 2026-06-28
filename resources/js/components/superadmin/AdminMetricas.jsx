import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import {
    LineChart, Line, XAxis, YAxis, Tooltip, ResponsiveContainer, CartesianGrid, Legend,
} from 'recharts';
import api from '../../lib/api';
import { getToken } from '../../lib/auth-token';
import { SkeletonRows, ErrorState } from './ui';

const tooltipStyle = { backgroundColor: '#1e293b', border: '1px solid #334155', borderRadius: 8, color: '#e2e8f0', fontSize: 12 };

export default function AdminMetricas() {
    const [dias, setDias] = useState(90);

    const { data, isLoading, isError, error, refetch } = useQuery({
        queryKey: ['admin', 'metricas', dias],
        queryFn: async () => (await api.get('/superadmin/metricas', { params: { dias } })).data,
    });

    // CSV export streams from the server; open it with the bearer token via fetch
    // then trigger a download (the endpoint is auth-protected).
    const exportCsv = async () => {
        const res = await fetch('/api/v1/superadmin/metricas/export', {
            headers: { Authorization: `Bearer ${getToken()}`, Accept: 'text/csv' },
        });
        const blob = await res.blob();
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'metricas.csv';
        a.click();
        URL.revokeObjectURL(url);
    };

    const serie = (data?.data ?? []).map((d) => ({ ...d, fecha: d.fecha?.slice(5) }));

    return (
        <div className="space-y-4">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <select value={dias} onChange={(e) => setDias(Number(e.target.value))} className="rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-slate-100">
                    <option value={30}>Últimos 30 días</option>
                    <option value={90}>Últimos 90 días</option>
                    <option value={365}>Último año</option>
                </select>
                <button onClick={exportCsv} className="rounded-lg bg-slate-700 px-3 py-2 text-sm font-semibold text-slate-100 hover:bg-slate-600">
                    Exportar CSV
                </button>
            </div>

            {isLoading ? (
                <SkeletonRows rows={3} className="h-48" />
            ) : isError ? (
                <ErrorState error={error} onRetry={refetch} />
            ) : serie.length === 0 ? (
                <p className="rounded-xl border border-slate-700/60 bg-slate-800/50 p-8 text-center text-sm text-slate-500">
                    Aún no hay métricas calculadas. Se generan a diario a las 02:00 (o con <code>php artisan metricas:calcular</code>).
                </p>
            ) : (
                <div className="space-y-4">
                    <Card title="Usuarios">
                        <ResponsiveContainer width="100%" height={260}>
                            <LineChart data={serie}>
                                <CartesianGrid strokeDasharray="3 3" stroke="#334155" />
                                <XAxis dataKey="fecha" stroke="#94a3b8" fontSize={11} />
                                <YAxis stroke="#94a3b8" fontSize={11} allowDecimals={false} />
                                <Tooltip contentStyle={tooltipStyle} />
                                <Legend wrapperStyle={{ fontSize: 12 }} />
                                <Line type="monotone" dataKey="usuarios_total" name="Totales" stroke="#38bdf8" strokeWidth={2} dot={false} />
                                <Line type="monotone" dataKey="usuarios_de_pago" name="De pago" stroke="#34d399" strokeWidth={2} dot={false} />
                                <Line type="monotone" dataKey="usuarios_activos_7d" name="Activos 7d" stroke="#fbbf24" strokeWidth={2} dot={false} />
                            </LineChart>
                        </ResponsiveContainer>
                    </Card>
                    <Card title="Ingresos (MRR / ARR)">
                        <ResponsiveContainer width="100%" height={260}>
                            <LineChart data={serie}>
                                <CartesianGrid strokeDasharray="3 3" stroke="#334155" />
                                <XAxis dataKey="fecha" stroke="#94a3b8" fontSize={11} />
                                <YAxis stroke="#94a3b8" fontSize={11} />
                                <Tooltip contentStyle={tooltipStyle} />
                                <Legend wrapperStyle={{ fontSize: 12 }} />
                                <Line type="monotone" dataKey="mrr" name="MRR €" stroke="#34d399" strokeWidth={2} dot={false} />
                                <Line type="monotone" dataKey="churn_count" name="Churn" stroke="#f87171" strokeWidth={2} dot={false} />
                            </LineChart>
                        </ResponsiveContainer>
                    </Card>
                </div>
            )}
        </div>
    );
}

function Card({ title, children }) {
    return (
        <div className="rounded-xl border border-slate-700/60 bg-slate-800/50 p-4">
            <h3 className="mb-3 text-sm font-semibold text-slate-300">{title}</h3>
            {children}
        </div>
    );
}
