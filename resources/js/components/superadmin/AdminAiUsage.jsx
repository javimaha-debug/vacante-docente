import { useQuery } from '@tanstack/react-query';
import { LineChart, Line, XAxis, YAxis, Tooltip, ResponsiveContainer } from 'recharts';
import api from '../../lib/api';
import { SkeletonRows, ErrorState, KpiCard } from './ui';

export default function AdminAiUsage() {
    const { data, isLoading, isError, error, refetch } = useQuery({
        queryKey: ['admin', 'ai-usage'],
        queryFn: async () => (await api.get('/superadmin/ai-usage')).data,
    });

    if (isLoading) return <SkeletonRows rows={3} className="h-24" />;
    if (isError) return <ErrorState error={error} onRetry={refetch} />;

    const m = data.mensajes ?? {};
    const series = (data.serie_diaria ?? []).map((d) => ({ ...d, dia: d.date?.slice(5) }));

    return (
        <div className="space-y-5">
            <div>
                <h1 className="text-xl font-bold text-white">Uso de IA</h1>
                <p className="text-sm text-slate-400">Consumo y coste estimado del asistente.</p>
            </div>

            <div className="grid grid-cols-2 gap-3 md:grid-cols-4">
                <KpiCard label="Mensajes hoy" value={m.hoy ?? 0} />
                <KpiCard label="Mensajes (7d)" value={m.semana ?? 0} />
                <KpiCard label="Mensajes (30d)" value={m.mes ?? 0} />
                <KpiCard label="Coste est. (30d)" value={`$${(data.coste_estimado_usd ?? 0).toFixed(2)}`} accent="text-emerald-300" />
            </div>

            <div className="grid grid-cols-2 gap-3 md:grid-cols-3">
                <KpiCard label="Llamadas Voyage (30d)" value={data.voyage_calls_mes ?? 0} />
                <KpiCard label="Tokens input (30d)" value={(data.tokens_mes?.input ?? 0).toLocaleString('es-ES')} />
                <KpiCard label="Tokens output (30d)" value={(data.tokens_mes?.output ?? 0).toLocaleString('es-ES')} />
            </div>

            <div className="rounded-xl border border-slate-700/60 bg-slate-800/50 p-4">
                <h2 className="mb-2 text-sm font-semibold text-slate-200">Mensajes por día (30d)</h2>
                <ResponsiveContainer width="100%" height={220}>
                    <LineChart data={series}>
                        <XAxis dataKey="dia" tick={{ fontSize: 11, fill: '#94a3b8' }} />
                        <YAxis tick={{ fontSize: 11, fill: '#94a3b8' }} />
                        <Tooltip contentStyle={{ background: '#0f172a', border: '1px solid #334155', borderRadius: 8 }} />
                        <Line type="monotone" dataKey="messages" stroke="#38bdf8" strokeWidth={2} dot={false} name="Mensajes" />
                    </LineChart>
                </ResponsiveContainer>
            </div>

            <div className="rounded-xl border border-slate-700/60 bg-slate-800/50 p-4">
                <h2 className="mb-2 text-sm font-semibold text-slate-200">Top usuarios (30d)</h2>
                <table className="w-full text-sm">
                    <thead className="text-left text-xs text-slate-400">
                        <tr><th className="py-1">Usuario</th><th className="py-1">Mensajes</th><th className="py-1">Tokens</th></tr>
                    </thead>
                    <tbody>
                        {(data.top_usuarios ?? []).map((u) => (
                            <tr key={u.user_id} className="border-t border-slate-700/40">
                                <td className="py-1.5 text-slate-200">{u.name ?? u.email ?? `#${u.user_id}`}</td>
                                <td className="py-1.5 text-slate-300">{u.messages}</td>
                                <td className="py-1.5 text-slate-300">{u.tokens.toLocaleString('es-ES')}</td>
                            </tr>
                        ))}
                        {(data.top_usuarios ?? []).length === 0 && <tr><td colSpan={3} className="py-3 text-slate-500">Sin uso registrado.</td></tr>}
                    </tbody>
                </table>
            </div>
        </div>
    );
}
