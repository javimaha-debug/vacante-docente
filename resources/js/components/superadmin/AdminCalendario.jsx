import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api from '../../lib/api';
import { SkeletonRows, ErrorState, Badge } from './ui';
import { EVENT_TYPES, typeMeta, buildMonthGrid, monthLabel, WEEKDAYS_ES, ymd } from '../../lib/calendar';

const AFFECTS = ['interinos', 'funcionarios', 'opositores', 'todos'];
const VISIBILITIES = ['superadmin_only', 'users_only', 'public'];

const today = new Date();

export default function AdminCalendario() {
    const [view, setView] = useState('calendario'); // calendario | sugeridos
    const [cursor, setCursor] = useState({ year: today.getFullYear(), month: today.getMonth() });
    const [filters, setFilters] = useState({ visibility: '', affects: '', event_type: '' });
    const [editing, setEditing] = useState(null); // event object or {event_date} for new

    const qc = useQueryClient();
    const { data, isLoading, isError, error, refetch } = useQuery({
        queryKey: ['admin', 'calendar', filters],
        queryFn: async () => (await api.get('/superadmin/calendar', { params: cleanParams(filters) })).data,
    });
    const { data: suggested } = useQuery({
        queryKey: ['admin', 'calendar', 'suggested'],
        queryFn: async () => (await api.get('/superadmin/calendar', { params: { suggested: 1 } })).data,
    });

    const invalidate = () => qc.invalidateQueries({ queryKey: ['admin', 'calendar'] });

    const save = useMutation({
        mutationFn: (ev) => ev.id ? api.patch(`/superadmin/calendar/${ev.id}`, ev) : api.post('/superadmin/calendar', ev),
        onSuccess: () => { invalidate(); setEditing(null); },
    });
    const remove = useMutation({
        mutationFn: (id) => api.delete(`/superadmin/calendar/${id}`),
        onSuccess: () => { invalidate(); setEditing(null); },
    });
    const confirm = useMutation({
        mutationFn: (id) => api.post(`/superadmin/calendar/${id}/confirm`),
        onSuccess: () => { invalidate(); setEditing(null); },
    });

    const events = data?.data ?? [];
    const suggestedEvents = suggested?.data ?? [];
    const byDay = groupByDay(events);

    const weeks = buildMonthGrid(cursor.year, cursor.month);
    const shift = (delta) => setCursor((c) => {
        const d = new Date(c.year, c.month + delta, 1);
        return { year: d.getFullYear(), month: d.getMonth() };
    });

    return (
        <div className="space-y-5">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 className="text-xl font-bold text-white">Calendario académico</h1>
                    <p className="text-sm text-slate-400">Hitos del proceso. Los estimados solo se ven aquí hasta confirmarlos.</p>
                </div>
                <div className="flex gap-1">
                    {['calendario', 'sugeridos'].map((v) => (
                        <button
                            key={v}
                            onClick={() => setView(v)}
                            className={`rounded-lg px-3 py-1.5 text-sm font-medium ${view === v ? 'bg-sky-600 text-white' : 'text-slate-400 hover:bg-slate-800'}`}
                        >
                            {v === 'calendario' ? 'Calendario' : `Sugeridos por monitor${suggestedEvents.length ? ` (${suggestedEvents.length})` : ''}`}
                        </button>
                    ))}
                </div>
            </div>

            {view === 'sugeridos' ? (
                <SuggestedList events={suggestedEvents} onEdit={setEditing} onConfirm={(id) => confirm.mutate(id)} />
            ) : (
                <>
                    <Filters filters={filters} setFilters={setFilters} />

                    <div className="flex items-center justify-between">
                        <button onClick={() => shift(-1)} className="rounded-lg px-3 py-1.5 text-sm text-slate-300 hover:bg-slate-800">← Anterior</button>
                        <h2 className="text-sm font-semibold text-white">{monthLabel(cursor.year, cursor.month)}</h2>
                        <button onClick={() => shift(1)} className="rounded-lg px-3 py-1.5 text-sm text-slate-300 hover:bg-slate-800">Siguiente →</button>
                    </div>

                    {isLoading ? <SkeletonRows rows={5} className="h-16" /> : isError ? <ErrorState error={error} onRetry={refetch} /> : (
                        <div className="overflow-hidden rounded-xl border border-slate-700/60">
                            <div className="grid grid-cols-7 border-b border-slate-800 bg-slate-800/60 text-center text-xs font-semibold text-slate-400">
                                {WEEKDAYS_ES.map((d) => <div key={d} className="py-2">{d}</div>)}
                            </div>
                            {weeks.map((week, wi) => (
                                <div key={wi} className="grid grid-cols-7">
                                    {week.map((cell) => (
                                        <button
                                            key={cell.date}
                                            onClick={() => setEditing({ event_date: cell.date, event_type: 'otro', affects: 'interinos', visibility: 'public' })}
                                            className={`min-h-[84px] border-b border-r border-slate-800 p-1.5 text-left align-top transition hover:bg-slate-800/40 ${cell.inMonth ? '' : 'opacity-40'}`}
                                        >
                                            <span className={`text-xs ${cell.date === ymd(today) ? 'font-bold text-sky-400' : 'text-slate-500'}`}>{cell.day}</span>
                                            <div className="mt-1 space-y-0.5">
                                                {(byDay[cell.date] ?? []).slice(0, 3).map((ev) => (
                                                    <span
                                                        key={ev.id}
                                                        role="button"
                                                        tabIndex={0}
                                                        onClick={(e) => { e.stopPropagation(); setEditing(ev); }}
                                                        className={`flex items-center gap-1 truncate rounded px-1 py-0.5 text-[10px] font-medium ${typeMeta(ev.event_type).darkChip}`}
                                                    >
                                                        <span className={`h-1.5 w-1.5 shrink-0 rounded-full ${typeMeta(ev.event_type).dot}`} />
                                                        <span className="truncate">{ev.title}</span>
                                                    </span>
                                                ))}
                                                {(byDay[cell.date] ?? []).length > 3 && (
                                                    <span className="text-[10px] text-slate-500">+{byDay[cell.date].length - 3} más</span>
                                                )}
                                            </div>
                                        </button>
                                    ))}
                                </div>
                            ))}
                        </div>
                    )}

                    <Legend />
                </>
            )}

            {editing && (
                <EventModal
                    event={editing}
                    onClose={() => setEditing(null)}
                    onSave={(ev) => save.mutate(ev)}
                    onDelete={editing.id ? () => remove.mutate(editing.id) : null}
                    onConfirm={editing.id && !editing.is_confirmed ? () => confirm.mutate(editing.id) : null}
                    saving={save.isPending}
                />
            )}
        </div>
    );
}

function Filters({ filters, setFilters }) {
    const set = (k, v) => setFilters((f) => ({ ...f, [k]: v }));
    return (
        <div className="flex flex-wrap gap-2">
            <select value={filters.visibility} onChange={(e) => set('visibility', e.target.value)} className={selCls}>
                <option value="">Visibilidad: todas</option>
                {VISIBILITIES.map((v) => <option key={v} value={v}>{v}</option>)}
            </select>
            <select value={filters.affects} onChange={(e) => set('affects', e.target.value)} className={selCls}>
                <option value="">Afecta: todos</option>
                {AFFECTS.map((v) => <option key={v} value={v}>{v}</option>)}
            </select>
            <select value={filters.event_type} onChange={(e) => set('event_type', e.target.value)} className={selCls}>
                <option value="">Tipo: todos</option>
                {EVENT_TYPES.map((t) => <option key={t.key} value={t.key}>{t.label}</option>)}
            </select>
        </div>
    );
}

function SuggestedList({ events, onEdit, onConfirm }) {
    if (events.length === 0) return <p className="rounded-xl border border-slate-800 bg-slate-800/30 p-6 text-center text-sm text-slate-400">El monitor no ha sugerido eventos.</p>;
    return (
        <div className="space-y-2">
            {events.map((ev) => (
                <div key={ev.id} className="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-slate-700/60 bg-slate-800/50 p-3">
                    <div className="min-w-0">
                        <p className="flex items-center gap-2 text-sm font-medium text-slate-100">
                            <span className={`h-2 w-2 rounded-full ${typeMeta(ev.event_type).dot}`} />
                            {ev.title}
                        </p>
                        <p className="text-xs text-slate-500">{ev.event_date} · {typeMeta(ev.event_type).label} · estimado</p>
                    </div>
                    <div className="flex gap-2">
                        <button onClick={() => onEdit(ev)} className="rounded-lg px-3 py-1.5 text-xs font-medium text-slate-300 hover:bg-slate-800">Editar</button>
                        <button onClick={() => onConfirm(ev.id)} className="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700">Confirmar</button>
                    </div>
                </div>
            ))}
        </div>
    );
}

function EventModal({ event, onClose, onSave, onDelete, onConfirm, saving }) {
    const [form, setForm] = useState({
        id: event.id,
        title: event.title ?? '',
        description: event.description ?? '',
        event_type: event.event_type ?? 'otro',
        event_date: event.event_date ?? '',
        time: event.time ?? '',
        affects: event.affects ?? 'interinos',
        visibility: event.visibility ?? 'public',
        is_estimated: event.is_estimated ?? false,
        is_confirmed: event.is_confirmed ?? false,
    });
    const set = (k, v) => setForm((f) => ({ ...f, [k]: v }));
    const canSave = form.title.trim() && form.event_date;

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4" onClick={onClose}>
            <div className="w-full max-w-md rounded-xl border border-slate-700 bg-slate-900 p-5" onClick={(e) => e.stopPropagation()}>
                <h3 className="font-semibold text-white">{event.id ? 'Editar evento' : 'Nuevo evento'}</h3>
                <div className="mt-3 space-y-3">
                    <Labeled label="Título"><input value={form.title} onChange={(e) => set('title', e.target.value)} className={selCls + ' w-full'} /></Labeled>
                    <div className="grid grid-cols-2 gap-2">
                        <Labeled label="Fecha"><input type="date" value={form.event_date} onChange={(e) => set('event_date', e.target.value)} className={selCls + ' w-full'} /></Labeled>
                        <Labeled label="Hora"><input value={form.time} onChange={(e) => set('time', e.target.value)} placeholder="09:00" className={selCls + ' w-full'} /></Labeled>
                    </div>
                    <Labeled label="Tipo">
                        <select value={form.event_type} onChange={(e) => set('event_type', e.target.value)} className={selCls + ' w-full'}>
                            {EVENT_TYPES.map((t) => <option key={t.key} value={t.key}>{t.label}</option>)}
                        </select>
                    </Labeled>
                    <div className="grid grid-cols-2 gap-2">
                        <Labeled label="Afecta">
                            <select value={form.affects} onChange={(e) => set('affects', e.target.value)} className={selCls + ' w-full'}>
                                {AFFECTS.map((v) => <option key={v} value={v}>{v}</option>)}
                            </select>
                        </Labeled>
                        <Labeled label="Visibilidad">
                            <select value={form.visibility} onChange={(e) => set('visibility', e.target.value)} className={selCls + ' w-full'}>
                                {VISIBILITIES.map((v) => <option key={v} value={v}>{v}</option>)}
                            </select>
                        </Labeled>
                    </div>
                    <Labeled label="Descripción"><textarea value={form.description} onChange={(e) => set('description', e.target.value)} rows={2} className={selCls + ' w-full'} /></Labeled>
                    <label className="flex items-center gap-2 text-sm text-slate-300">
                        <input type="checkbox" checked={form.is_estimated} onChange={(e) => set('is_estimated', e.target.checked)} /> Estimado
                    </label>
                </div>
                <div className="mt-4 flex items-center justify-between">
                    <div className="flex gap-2">
                        {onDelete && <button onClick={onDelete} className="rounded-lg px-3 py-1.5 text-sm text-rose-400 hover:bg-rose-500/10">Eliminar</button>}
                        {onConfirm && <button onClick={onConfirm} className="rounded-lg bg-emerald-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-emerald-700">Confirmar</button>}
                    </div>
                    <div className="flex gap-2">
                        <button onClick={onClose} className="rounded-lg px-3 py-1.5 text-sm text-slate-400 hover:bg-slate-800">Cancelar</button>
                        <button onClick={() => onSave(form)} disabled={!canSave || saving} className="rounded-lg bg-sky-600 px-4 py-1.5 text-sm font-semibold text-white hover:bg-sky-700 disabled:opacity-50">
                            {saving ? 'Guardando…' : 'Guardar'}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    );
}

function Legend() {
    return (
        <div className="flex flex-wrap gap-3 text-xs text-slate-400">
            {EVENT_TYPES.map((t) => (
                <span key={t.key} className="flex items-center gap-1.5">
                    <span className={`h-2.5 w-2.5 rounded-full ${typeMeta(t.key).dot}`} />
                    {t.label}
                </span>
            ))}
        </div>
    );
}

function Labeled({ label, children }) {
    return (
        <label className="block">
            <span className="mb-1 block text-xs font-medium uppercase tracking-wide text-slate-400">{label}</span>
            {children}
        </label>
    );
}

const selCls = 'rounded-lg border border-slate-700 bg-slate-800 px-3 py-1.5 text-sm text-slate-100 focus:border-sky-500 focus:outline-none';

function groupByDay(events) {
    const map = {};
    for (const ev of events) {
        (map[ev.event_date] ??= []).push(ev);
    }
    return map;
}

function cleanParams(filters) {
    return Object.fromEntries(Object.entries(filters).filter(([, v]) => v));
}
