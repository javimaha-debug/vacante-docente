import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import clsx from 'clsx';
import api from '../../lib/api';

const DIAS = ['lunes', 'martes', 'miercoles', 'jueves', 'viernes'];
const DIA_LABELS = { lunes: 'Lunes', martes: 'Martes', miercoles: 'Miércoles', jueves: 'Jueves', viernes: 'Viernes' };
const ESTADO_STYLES = {
    al_dia: 'bg-emerald-50 text-emerald-700',
    retraso_leve: 'bg-amber-50 text-amber-700',
    riesgo: 'bg-rose-50 text-rose-700',
};

function SesionModal({ sesion, onClose }) {
    const qc = useQueryClient();
    const [nota, setNota] = useState(sesion.contenido_real ?? '');

    const marcarMutation = useMutation({
        mutationFn: () => api.patch(`/docente/sesiones/${sesion.id}`, { impartida: true, contenido_real: nota }),
        onSuccess: () => { qc.invalidateQueries({ queryKey: ['docente-semana'] }); onClose(); },
    });

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40" onClick={onClose}>
            <div className="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl" onClick={(e) => e.stopPropagation()}>
                <h3 className="mb-1 text-base font-bold text-slate-800">{sesion.titulo_planificado}</h3>
                <p className="mb-3 text-xs text-slate-400">{sesion.grupo?.nombre} · {sesion.grupo?.asignatura?.nombre}</p>
                {sesion.impartida ? (
                    <span className="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-700">✓ Impartida</span>
                ) : (
                    <>
                        <label className="label">Nota rápida</label>
                        <textarea className="input min-h-[80px]" value={nota} onChange={(e) => setNota(e.target.value)} placeholder="¿Qué se vio realmente?…" rows={3} />
                        <div className="mt-3 flex justify-end gap-2">
                            <button onClick={onClose} className="btn-ghost">Cerrar</button>
                            <button onClick={() => marcarMutation.mutate()} disabled={marcarMutation.isPending} className="btn-primary">
                                {marcarMutation.isPending ? 'Guardando…' : 'Marcar como impartida'}
                            </button>
                        </div>
                    </>
                )}
                {sesion.impartida && sesion.contenido_real && (
                    <p className="mt-2 text-sm text-slate-600">{sesion.contenido_real}</p>
                )}
            </div>
        </div>
    );
}

function ProgresoGrupo({ grupoId }) {
    const { data } = useQuery({
        queryKey: ['docente-progreso', grupoId],
        queryFn: async () => (await api.get(`/docente/grupos/${grupoId}/progreso`)).data,
        staleTime: 60000,
    });

    if (!data) return null;

    return (
        <div className={clsx('rounded-xl p-3 ring-1', ESTADO_STYLES[data.estado] ?? 'bg-slate-50 text-slate-600', 'ring-current/20')}>
            <div className="flex items-center justify-between gap-2">
                <p className="text-sm font-semibold">{data.nombre}</p>
                <span className="text-xs">{data.porcentaje}%</span>
            </div>
            <div className="mt-1.5 h-1.5 overflow-hidden rounded-full bg-black/10">
                <div className="h-full rounded-full bg-current" style={{ width: `${data.porcentaje}%` }} />
            </div>
            {data.unidad_actual && <p className="mt-1 text-[11px] opacity-70">UD actual: {data.unidad_actual.titulo}</p>}
            {data.proyeccion_fin && <p className="text-[11px] opacity-70">Proyección: {data.proyeccion_fin}</p>}
        </div>
    );
}

function ConfigHorarioModal({ grupos, onClose }) {
    const qc = useQueryClient();
    const [form, setForm] = useState({ grupo_id: '', dia_semana: 'lunes', hora_inicio: '09:00', hora_fin: '10:00', aula: '' });
    const set = (k, v) => setForm((f) => ({ ...f, [k]: v }));

    const saveMutation = useMutation({
        mutationFn: (data) => api.post('/docente/horario', data),
        onSuccess: () => { qc.invalidateQueries({ queryKey: ['docente-horario'] }); onClose(); },
    });

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40" onClick={onClose}>
            <div className="w-full max-w-sm rounded-2xl bg-white p-6 shadow-xl" onClick={(e) => e.stopPropagation()}>
                <h3 className="mb-4 text-base font-bold">Añadir franja horaria</h3>
                <div className="space-y-3">
                    <div>
                        <label className="label">Grupo</label>
                        <select className="input" value={form.grupo_id} onChange={(e) => set('grupo_id', e.target.value)}>
                            <option value="">— Selecciona grupo —</option>
                            {grupos.map((g) => <option key={g.id} value={g.id}>{g.nombre} ({g.asignatura?.nombre})</option>)}
                        </select>
                    </div>
                    <div>
                        <label className="label">Día</label>
                        <select className="input" value={form.dia_semana} onChange={(e) => set('dia_semana', e.target.value)}>
                            {DIAS.map((d) => <option key={d} value={d}>{DIA_LABELS[d]}</option>)}
                        </select>
                    </div>
                    <div className="grid grid-cols-2 gap-2">
                        <div><label className="label">Inicio</label><input type="time" className="input" value={form.hora_inicio} onChange={(e) => set('hora_inicio', e.target.value)} /></div>
                        <div><label className="label">Fin</label><input type="time" className="input" value={form.hora_fin} onChange={(e) => set('hora_fin', e.target.value)} /></div>
                    </div>
                    <div><label className="label">Aula</label><input className="input" value={form.aula} onChange={(e) => set('aula', e.target.value)} placeholder="A1, Lab…" /></div>
                </div>
                <div className="mt-4 flex justify-end gap-2">
                    <button onClick={onClose} className="btn-ghost">Cancelar</button>
                    <button onClick={() => saveMutation.mutate(form)} disabled={saveMutation.isPending || !form.grupo_id} className="btn-primary">Guardar</button>
                </div>
            </div>
        </div>
    );
}

export default function HorarioPage() {
    const [selectedSesion, setSelectedSesion] = useState(null);
    const [configOpen, setConfigOpen] = useState(false);

    const { data: horario = [] } = useQuery({
        queryKey: ['docente-horario'],
        queryFn: async () => (await api.get('/docente/horario')).data.data ?? [],
    });

    const { data: semana } = useQuery({
        queryKey: ['docente-semana'],
        queryFn: async () => (await api.get('/docente/horario/semana')).data,
        staleTime: 60000,
    });

    const { data: asignaturas = [] } = useQuery({
        queryKey: ['docente-asignaturas'],
        queryFn: async () => (await api.get('/docente/asignaturas')).data.data ?? [],
    });

    // Flatten grupos from asignaturas
    const grupos = asignaturas.flatMap((a) => (a.grupos ?? []).map((g) => ({ ...g, asignatura: a })));
    const grupoIds = [...new Set(grupos.map((g) => g.id))];

    // Build grid: dia → [horario entries]
    const grid = DIAS.reduce((acc, d) => {
        acc[d] = horario.filter((h) => h.dia_semana === d).sort((a, b) => a.hora_inicio.localeCompare(b.hora_inicio));
        return acc;
    }, {});

    const sesionesHoy = (semana?.data ?? []).filter((s) => {
        const today = new Date().toISOString().slice(0, 10);
        return s.fecha === today;
    });

    return (
        <div className="space-y-6">
            <div className="flex items-center justify-between">
                <h1 className="text-lg font-bold text-slate-800">Mi horario</h1>
                <button onClick={() => setConfigOpen(true)} className="btn-ghost text-sm">+ Configurar horario</button>
            </div>

            {/* Weekly grid */}
            <div className="overflow-x-auto">
                <div className="grid min-w-[600px] grid-cols-5 gap-2">
                    {DIAS.map((dia) => (
                        <div key={dia}>
                            <p className="mb-1 text-center text-xs font-bold uppercase tracking-wide text-slate-500">{DIA_LABELS[dia]}</p>
                            <div className="space-y-1">
                                {grid[dia].length === 0 ? (
                                    <div className="rounded-lg bg-slate-50 px-2 py-3 text-center text-xs text-slate-300">—</div>
                                ) : (
                                    grid[dia].map((h) => (
                                        <div
                                            key={h.id}
                                            className="rounded-lg p-2 text-center text-xs font-medium shadow-sm"
                                            style={{ backgroundColor: h.grupo?.color ?? '#e0f2fe', color: '#0c4a6e' }}
                                        >
                                            <p className="font-semibold truncate">{h.grupo?.nombre}</p>
                                            <p className="opacity-70">{h.hora_inicio?.slice(0, 5)}–{h.hora_fin?.slice(0, 5)}</p>
                                            {h.aula && <p className="opacity-60">{h.aula}</p>}
                                        </div>
                                    ))
                                )}
                            </div>
                        </div>
                    ))}
                </div>
            </div>

            {/* Today's sessions */}
            {sesionesHoy.length > 0 && (
                <section>
                    <h2 className="mb-2 text-sm font-bold text-slate-700">Sesiones de hoy</h2>
                    <div className="space-y-2">
                        {sesionesHoy.map((s) => (
                            <button key={s.id} onClick={() => setSelectedSesion(s)} className="w-full rounded-xl bg-white p-3 text-left shadow-sm ring-1 ring-slate-200 hover:ring-brand-300 transition">
                                <div className="flex items-center gap-3">
                                    <div className="flex-1">
                                        <p className="text-sm font-medium text-slate-800">{s.titulo_planificado}</p>
                                        <p className="text-xs text-slate-400">{s.grupo?.nombre} · {s.grupo?.asignatura?.nombre}</p>
                                    </div>
                                    {s.impartida
                                        ? <span className="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-bold text-emerald-700">✓</span>
                                        : <span className="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-500">Pendiente</span>}
                                </div>
                            </button>
                        ))}
                    </div>
                </section>
            )}

            {/* Group progress */}
            {grupoIds.length > 0 && (
                <section>
                    <h2 className="mb-2 text-sm font-bold text-slate-700">Progreso por grupos</h2>
                    <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                        {grupos.slice(0, 9).map((g) => <ProgresoGrupo key={g.id} grupoId={g.id} />)}
                    </div>
                </section>
            )}

            {selectedSesion && <SesionModal sesion={selectedSesion} onClose={() => setSelectedSesion(null)} />}
            {configOpen && <ConfigHorarioModal grupos={grupos} onClose={() => setConfigOpen(false)} />}
        </div>
    );
}
