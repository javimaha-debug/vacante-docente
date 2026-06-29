import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import api from '../../lib/api';
import { typeMeta, buildMonthGrid, monthLabel, WEEKDAYS_ES, ymd, EVENT_TYPES } from '../../lib/calendar';

const AFFECTS = [
    { key: '', label: 'Todos' },
    { key: 'interinos', label: 'Interinos' },
    { key: 'funcionarios', label: 'Funcionarios' },
    { key: 'opositores', label: 'Opositores' },
];

const today = new Date();

function fecha(d) {
    if (!d) return '';
    try { return new Date(d + 'T00:00:00').toLocaleDateString('es-ES', { day: 'numeric', month: 'long' }); }
    catch { return d; }
}

export default function CalendarioPage() {
    const [cursor, setCursor] = useState({ year: today.getFullYear(), month: today.getMonth() });
    const [affects, setAffects] = useState('');

    const { data, isLoading, isError, error, refetch } = useQuery({
        queryKey: ['calendar', affects],
        queryFn: async () => (await api.get('/calendar', { params: affects ? { affects } : {} })).data,
    });

    const events = data?.data ?? [];
    const byDay = {};
    for (const ev of events) (byDay[ev.event_date] ??= []).push(ev);

    const weeks = buildMonthGrid(cursor.year, cursor.month);
    const shift = (delta) => setCursor((c) => {
        const d = new Date(c.year, c.month + delta, 1);
        return { year: d.getFullYear(), month: d.getMonth() };
    });

    return (
        <div className="space-y-5">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 className="text-lg font-bold text-slate-800">Calendario académico</h1>
                    <p className="text-sm text-slate-500">Fechas clave del proceso de adjudicación.</p>
                </div>
                <select
                    value={affects}
                    onChange={(e) => setAffects(e.target.value)}
                    className="rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-700 focus:border-brand-400 focus:outline-none"
                >
                    {AFFECTS.map((a) => <option key={a.key} value={a.key}>{a.label}</option>)}
                </select>
            </div>

            <div className="flex items-center justify-between">
                <button onClick={() => shift(-1)} className="rounded-lg px-3 py-1.5 text-sm text-slate-600 hover:bg-slate-100">← Anterior</button>
                <h2 className="text-sm font-semibold text-slate-800">{monthLabel(cursor.year, cursor.month)}</h2>
                <button onClick={() => shift(1)} className="rounded-lg px-3 py-1.5 text-sm text-slate-600 hover:bg-slate-100">Siguiente →</button>
            </div>

            {isLoading ? (
                <p className="text-sm text-slate-400">Cargando…</p>
            ) : isError ? (
                <div>
                    <p className="rounded-lg bg-rose-50 px-3 py-2 text-sm text-rose-600">{error?.friendlyMessage ?? 'No se pudo cargar.'}</p>
                    <button onClick={() => refetch()} className="mt-2 text-sm font-semibold text-brand-600">Reintentar</button>
                </div>
            ) : (
                <div className="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
                    <div className="grid grid-cols-7 border-b border-slate-100 bg-slate-50 text-center text-xs font-semibold text-slate-400">
                        {WEEKDAYS_ES.map((d) => <div key={d} className="py-2">{d}</div>)}
                    </div>
                    {weeks.map((week, wi) => (
                        <div key={wi} className="grid grid-cols-7">
                            {week.map((cell) => (
                                <div key={cell.date} className={`min-h-[78px] border-b border-r border-slate-100 p-1.5 align-top ${cell.inMonth ? '' : 'bg-slate-50/60 opacity-50'}`}>
                                    <span className={`text-xs ${cell.date === ymd(today) ? 'font-bold text-brand-600' : 'text-slate-400'}`}>{cell.day}</span>
                                    <div className="mt-1 space-y-0.5">
                                        {(byDay[cell.date] ?? []).map((ev) => (
                                            <span key={ev.id} className={`flex items-center gap-1 truncate rounded px-1 py-0.5 text-[10px] font-medium ${typeMeta(ev.event_type).chip}`} title={ev.title}>
                                                <span className={`h-1.5 w-1.5 shrink-0 rounded-full ${typeMeta(ev.event_type).dot}`} />
                                                <span className="truncate">{ev.title}</span>
                                            </span>
                                        ))}
                                    </div>
                                </div>
                            ))}
                        </div>
                    ))}
                </div>
            )}

            {/* Upcoming list (clearer on mobile than a dense grid). */}
            {(data?.upcoming ?? []).length > 0 && (
                <div className="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
                    <h2 className="font-semibold text-slate-800">Próximas fechas</h2>
                    <ul className="mt-2 divide-y divide-slate-100">
                        {data.upcoming.map((ev) => (
                            <li key={ev.id} className="flex items-center gap-3 py-2 text-sm">
                                <span className={`h-2.5 w-2.5 shrink-0 rounded-full ${typeMeta(ev.event_type).dot}`} />
                                <span className="flex-1 text-slate-700">{ev.title}</span>
                                {ev.is_estimated && <span className="rounded-full bg-amber-100 px-1.5 py-0.5 text-[10px] font-semibold text-amber-700">Estimado</span>}
                                <span className="font-medium text-slate-500">{fecha(ev.event_date)}</span>
                            </li>
                        ))}
                    </ul>
                </div>
            )}

            <div className="flex flex-wrap gap-3 text-xs text-slate-500">
                {EVENT_TYPES.map((t) => (
                    <span key={t.key} className="flex items-center gap-1.5">
                        <span className={`h-2.5 w-2.5 rounded-full ${typeMeta(t.key).dot}`} />
                        {t.label}
                    </span>
                ))}
            </div>
        </div>
    );
}
