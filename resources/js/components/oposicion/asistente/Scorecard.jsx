import { BarChart, Bar, XAxis, YAxis, Cell, ResponsiveContainer, Tooltip } from 'recharts';
import { useScores } from './useAsistente';

function barColor(score) {
    if (score == null) return '#cbd5e1';
    if (score < 40) return '#f43f5e';
    if (score < 70) return '#f59e0b';
    if (score < 90) return '#14b8a6';
    return '#10b981';
}

export default function Scorecard() {
    const { data, isLoading } = useScores();
    if (isLoading) return <p className="text-sm text-slate-400">Cargando puntuaciones…</p>;

    const scores = data?.data ?? [];
    const resumen = data?.resumen ?? {};
    if (scores.length === 0) {
        return <p className="rounded-xl bg-white p-8 text-center text-sm text-slate-400 ring-1 ring-slate-200">Aún no hay temas puntuados. Haz flashcards o un simulacro para empezar a medir tu nivel.</p>;
    }

    const chartData = scores.map((s) => ({ name: `T${s.numero}`, score: s.score ?? 0, titulo: s.titulo }));
    const flojos = scores.filter((s) => s.reliable && (s.score ?? 0) < 40);

    return (
        <div className="space-y-4">
            <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <Kpi label="Temas" value={resumen.total ?? scores.length} />
                <Kpi label="Dominados" value={resumen.dominados ?? 0} tone="text-emerald-600" />
                <Kpi label="En progreso" value={resumen.en_progreso ?? 0} tone="text-amber-600" />
                <Kpi label="Flojos (<40)" value={resumen.flojos ?? 0} tone="text-rose-600" />
            </div>

            {flojos.length > 0 && (
                <div className="rounded-xl bg-rose-50 px-4 py-2 text-sm text-rose-700">
                    ⚠️ {flojos.length} tema(s) por debajo del 40 %. Prioriza: {flojos.slice(0, 3).map((s) => `T${s.numero}`).join(', ')}.
                </div>
            )}

            <div className="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
                <h3 className="mb-2 text-sm font-bold text-slate-700">Dominio por tema</h3>
                <ResponsiveContainer width="100%" height={220}>
                    <BarChart data={chartData}>
                        <XAxis dataKey="name" tick={{ fontSize: 11 }} />
                        <YAxis domain={[0, 100]} tick={{ fontSize: 11 }} />
                        <Tooltip formatter={(v, _n, p) => [`${v}%`, p.payload.titulo]} />
                        <Bar dataKey="score" radius={[4, 4, 0, 0]}>
                            {chartData.map((d, i) => <Cell key={i} fill={barColor(d.score)} />)}
                        </Bar>
                    </BarChart>
                </ResponsiveContainer>
            </div>

            <div className="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
                {scores.map((s) => (
                    <div key={s.tema_id} className="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 px-4 py-2.5 last:border-0">
                        <div className="min-w-0">
                            <p className="truncate text-sm font-medium text-slate-700">T{s.numero} · {s.titulo}</p>
                            <p className="text-xs text-slate-400">{s.recommendation}</p>
                        </div>
                        <span className="text-lg font-bold tabular-nums" style={{ color: barColor(s.score) }}>{s.score ?? '—'}{s.score != null ? '%' : ''}</span>
                    </div>
                ))}
            </div>
        </div>
    );
}

function Kpi({ label, value, tone = 'text-slate-800' }) {
    return (
        <div className="rounded-xl bg-white p-3 text-center shadow-sm ring-1 ring-slate-200">
            <p className={`text-2xl font-bold ${tone}`}>{value}</p>
            <p className="text-xs text-slate-400">{label}</p>
        </div>
    );
}
