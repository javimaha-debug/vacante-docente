import { useQuery } from '@tanstack/react-query';
import {
    LineChart, Line, AreaChart, Area, BarChart, Bar, XAxis, YAxis, Tooltip, ResponsiveContainer, CartesianGrid,
} from 'recharts';
import api from '../../lib/api';
import { KpiCard, SkeletonRows, ErrorState, Badge } from './ui';

const PLAN_LABELS = {
    free: 'Gratis', interino: 'Interino', opositor: 'Opositor',
    docente_pro: 'Docente Pro', todo_en_uno: 'Todo en Uno',
};

const chartTheme = {
    grid: '#334155',
    axis: '#94a3b8',
    line: '#38bdf8',
    area: '#0ea5e9',
};

export default function AdminDashboard() {
    const { data, isLoading, isError, error, refetch } = useQuery({
        queryKey: ['admin', 'dashboard'],
        queryFn: async () => (await api.get('/superadmin/dashboard')).data,
    });

    if (isLoading) {
        return (
            <div className="space-y-4">
                <div className="grid grid-cols-2 gap-3 md:grid-cols-4">
                    {Array.from({ length: 4 }).map((_, i) => <div key={i} className="h-24 animate-pulse rounded-xl bg-slate-800/50" />)}
                </div>
                <SkeletonRows rows={3} className="h-40" />
            </div>
        );
    }
    if (isError) return <ErrorState error={error} onRetry={refetch} />;

    const k = data.kpis;
    const serie = (data.serie ?? []).map((d) => ({ ...d, fecha: d.fecha?.slice(5) }));
    const porPlan = Object.entries(data.por_plan ?? {}).map(([plan, total]) => ({
        plan: PLAN_LABELS[plan] ?? plan, total,
    }));

    return (
        <div className="space-y-6">
            <div className="grid grid-cols-2 gap-3 md:grid-cols-4">
                <KpiCard label="Usuarios" value={k.usuarios_total} sub={`+${k.nuevos_7d} esta semana`} />
                <KpiCard label="De pago" value={k.usuarios_de_pago} sub={`${k.conversion}% conversión`} accent="text-emerald-400" />
                <KpiCard label="MRR" value={`${k.mrr} €`} sub={`ARR ${k.arr} €`} accent="text-sky-400" />
                <KpiCard label="Activos 7d" value={k.usuarios_activos_7d} sub={`${k.usuarios_suspendidos} suspendidos`} accent="text-amber-400" />
            </div>

            <div className="grid gap-4 lg:grid-cols-2">
                <ChartCard title="Usuarios totales (30 días)">
                    <ResponsiveContainer width="100%" height={240}>
                        <AreaChart data={serie}>
                            <CartesianGrid strokeDasharray="3 3" stroke={chartTheme.grid} />
                            <XAxis dataKey="fecha" stroke={chartTheme.axis} fontSize={11} />
                            <YAxis stroke={chartTheme.axis} fontSize={11} allowDecimals={false} />
                            <Tooltip contentStyle={tooltipStyle} />
                            <Area type="monotone" dataKey="usuarios_total" stroke={chartTheme.line} fill={chartTheme.area} fillOpacity={0.25} />
                        </AreaChart>
                    </ResponsiveContainer>
                </ChartCard>

                <ChartCard title="MRR (30 días)">
                    <ResponsiveContainer width="100%" height={240}>
                        <LineChart data={serie}>
                            <CartesianGrid strokeDasharray="3 3" stroke={chartTheme.grid} />
                            <XAxis dataKey="fecha" stroke={chartTheme.axis} fontSize={11} />
                            <YAxis stroke={chartTheme.axis} fontSize={11} />
                            <Tooltip contentStyle={tooltipStyle} />
                            <Line type="monotone" dataKey="mrr" stroke="#34d399" strokeWidth={2} dot={false} />
                        </LineChart>
                    </ResponsiveContainer>
                </ChartCard>

                <ChartCard title="Usuarios por plan">
                    <ResponsiveContainer width="100%" height={240}>
                        <BarChart data={porPlan}>
                            <CartesianGrid strokeDasharray="3 3" stroke={chartTheme.grid} />
                            <XAxis dataKey="plan" stroke={chartTheme.axis} fontSize={11} />
                            <YAxis stroke={chartTheme.axis} fontSize={11} allowDecimals={false} />
                            <Tooltip contentStyle={tooltipStyle} />
                            <Bar dataKey="total" fill="#818cf8" radius={[4, 4, 0, 0]} />
                        </BarChart>
                    </ResponsiveContainer>
                </ChartCard>

                <ChartCard title="Últimos registros">
                    <ul className="divide-y divide-slate-700/60 text-sm">
                        {(data.ultimos_registros ?? []).map((u) => (
                            <li key={u.id} className="flex items-center justify-between py-2">
                                <div className="min-w-0">
                                    <p className="truncate font-medium text-slate-200">{u.name}</p>
                                    <p className="truncate text-xs text-slate-500">{u.email}</p>
                                </div>
                                <Badge>{PLAN_LABELS[u.plan] ?? u.plan}</Badge>
                            </li>
                        ))}
                        {(data.ultimos_registros ?? []).length === 0 && (
                            <li className="py-3 text-center text-xs text-slate-500">Sin registros recientes.</li>
                        )}
                    </ul>
                </ChartCard>
            </div>
        </div>
    );
}

const tooltipStyle = { backgroundColor: '#1e293b', border: '1px solid #334155', borderRadius: 8, color: '#e2e8f0', fontSize: 12 };

function ChartCard({ title, children }) {
    return (
        <div className="rounded-xl border border-slate-700/60 bg-slate-800/50 p-4">
            <h3 className="mb-3 text-sm font-semibold text-slate-300">{title}</h3>
            {children}
        </div>
    );
}
