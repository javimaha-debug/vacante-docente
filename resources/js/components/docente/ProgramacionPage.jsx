import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Link } from 'react-router-dom';
import clsx from 'clsx';
import api from '../../lib/api';

const ETAPA_LABELS = { infantil: 'Infantil', primaria: 'Primaria', eso: 'ESO', bachillerato: 'Bachillerato', fp: 'FP', otros: 'Otros' };
const TRIMESTRE_LABELS = { primero: '1er trim.', segundo: '2º trim.', tercero: '3er trim.' };
const TIPO_LABELS = { unidad_didactica: 'UD', situacion_aprendizaje: 'SA', proyecto: 'Proyecto' };
const STATUS_STYLES = { borrador: 'bg-amber-50 text-amber-700', activa: 'bg-emerald-50 text-emerald-700', archivada: 'bg-slate-100 text-slate-500' };

function AiDisclaimer() {
    return (
        <p className="rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-700 ring-1 ring-amber-200">
            Revisado y validado por el docente antes de su uso.
        </p>
    );
}

function ProgramacionForm({ asignaturas, onSave, onCancel, initial = {} }) {
    const [form, setForm] = useState({
        titulo: initial.titulo ?? '',
        asignatura_id: initial.asignatura_id ?? '',
        año_academico: initial.año_academico ?? '2026-2027',
        centro_nombre: initial.centro_nombre ?? '',
        centro_tipo: initial.centro_tipo ?? '',
        es_bilingue: initial.es_bilingue ?? false,
        objetivos_generales: initial.objetivos_generales ?? '',
        metodologia: initial.metodologia ?? '',
        atencion_diversidad: initial.atencion_diversidad ?? '',
        criterios_evaluacion: initial.criterios_evaluacion ?? '',
        instrumentos_evaluacion: initial.instrumentos_evaluacion ?? '',
        status: initial.status ?? 'borrador',
    });

    const set = (k, v) => setForm((f) => ({ ...f, [k]: v }));

    return (
        <div className="space-y-4">
            <div className="grid gap-3 sm:grid-cols-2">
                <div>
                    <label className="label">Título</label>
                    <input className="input" value={form.titulo} onChange={(e) => set('titulo', e.target.value)} placeholder="Programación Didáctica…" />
                </div>
                <div>
                    <label className="label">Asignatura</label>
                    <select className="input" value={form.asignatura_id} onChange={(e) => set('asignatura_id', e.target.value)}>
                        <option value="">— Selecciona —</option>
                        {asignaturas.map((a) => (
                            <option key={a.id} value={a.id}>{a.nombre} ({a.curso})</option>
                        ))}
                    </select>
                </div>
                <div>
                    <label className="label">Año académico</label>
                    <input className="input" value={form.año_academico} onChange={(e) => set('año_academico', e.target.value)} placeholder="2026-2027" />
                </div>
                <div>
                    <label className="label">Centro</label>
                    <input className="input" value={form.centro_nombre} onChange={(e) => set('centro_nombre', e.target.value)} placeholder="IES…" />
                </div>
                <div>
                    <label className="label">Tipo de centro</label>
                    <input className="input" value={form.centro_tipo} onChange={(e) => set('centro_tipo', e.target.value)} placeholder="IES, CEIP, CIPFP…" />
                </div>
                <div className="flex items-center gap-2 pt-5">
                    <input type="checkbox" id="bilingue" checked={form.es_bilingue} onChange={(e) => set('es_bilingue', e.target.checked)} className="h-4 w-4 rounded border-slate-300" />
                    <label htmlFor="bilingue" className="text-sm text-slate-700">Centro bilingüe</label>
                </div>
            </div>
            {['objetivos_generales', 'metodologia', 'atencion_diversidad', 'criterios_evaluacion', 'instrumentos_evaluacion'].map((field) => (
                <div key={field}>
                    <label className="label capitalize">{field.replace(/_/g, ' ')}</label>
                    <textarea
                        className="input min-h-[80px]"
                        value={form[field]}
                        onChange={(e) => set(field, e.target.value)}
                        rows={3}
                    />
                </div>
            ))}
            <div className="flex justify-end gap-2">
                <button onClick={onCancel} className="btn-ghost">Cancelar</button>
                <button onClick={() => onSave(form)} className="btn-primary">Guardar</button>
            </div>
        </div>
    );
}

function UnidadRow({ unidad }) {
    const [open, setOpen] = useState(false);
    return (
        <div className="border-b border-slate-100 last:border-0">
            <button onClick={() => setOpen((v) => !v)} className="flex w-full items-center gap-3 py-2 text-left">
                <span className="w-6 text-center text-xs font-bold text-slate-400">{unidad.numero}</span>
                <span className="flex-1 text-sm font-medium text-slate-800">{unidad.titulo}</span>
                <span className="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] text-slate-500">{TIPO_LABELS[unidad.tipo] ?? unidad.tipo}</span>
                <span className="text-[10px] text-slate-400">{TRIMESTRE_LABELS[unidad.trimestre] ?? ''}</span>
                <span className="text-[10px] text-slate-400">{unidad.num_sesiones_previstas} ses.</span>
                <span className="ml-1 text-slate-400">{open ? '▲' : '▾'}</span>
            </button>
            {open && (
                <div className="pb-3 pl-9 text-sm text-slate-600">
                    {unidad.descripcion && <p className="mb-2">{unidad.descripcion}</p>}
                    {unidad.competencias?.length > 0 && (
                        <div className="flex flex-wrap gap-1">
                            {unidad.competencias.map((c, i) => (
                                <span key={i} className="rounded-full bg-brand-50 px-2 py-0.5 text-[10px] font-semibold text-brand-700">{c}</span>
                            ))}
                        </div>
                    )}
                </div>
            )}
        </div>
    );
}

function ProgramacionDetail({ programacion, onBack }) {
    const [tab, setTab] = useState('programacion');
    const [adapting, setAdapting] = useState(false);
    const [newCentro, setNewCentro] = useState({ nombre: '', tipo: 'IES', es_bilingue: false });
    const [sugerencias, setSugerencias] = useState(null);
    const qc = useQueryClient();

    const { data: detalle, isLoading } = useQuery({
        queryKey: ['docente-programacion', programacion.id],
        queryFn: async () => (await api.get(`/docente/programaciones/${programacion.id}`)).data,
    });

    const adaptarMutation = useMutation({
        mutationFn: (params) => api.post(`/docente/programaciones/${programacion.id}/adaptar`, params),
        onSuccess: (res) => setSugerencias(res.data.sugerencias),
    });

    if (isLoading) return <p className="text-sm text-slate-400 p-4">Cargando…</p>;

    const tabs = ['programacion', 'unidades', 'sesiones'];

    return (
        <div>
            <button onClick={onBack} className="mb-4 text-sm text-brand-600 hover:underline">← Volver a programaciones</button>
            <div className="mb-4 flex items-center gap-3">
                <h2 className="text-lg font-bold text-slate-800">{detalle.titulo}</h2>
                <span className={clsx('rounded-full px-2 py-0.5 text-xs font-bold', STATUS_STYLES[detalle.status] ?? STATUS_STYLES.borrador)}>
                    {detalle.status}
                </span>
            </div>
            <div className="mb-4 flex gap-1 border-b border-slate-200">
                {tabs.map((t) => (
                    <button key={t} onClick={() => setTab(t)} className={clsx('px-4 py-2 text-sm font-medium capitalize', tab === t ? 'border-b-2 border-brand-600 text-brand-700' : 'text-slate-500 hover:text-slate-700')}>
                        {t === 'programacion' ? 'Programación' : t === 'unidades' ? 'Unidades' : 'Sesiones'}
                    </button>
                ))}
            </div>

            {tab === 'programacion' && (
                <div className="space-y-4">
                    {['objetivos_generales', 'metodologia', 'atencion_diversidad', 'criterios_evaluacion', 'instrumentos_evaluacion'].map((field) => detalle[field] && (
                        <div key={field}>
                            <p className="mb-1 text-xs font-semibold uppercase tracking-wide text-slate-400">{field.replace(/_/g, ' ')}</p>
                            <p className="text-sm text-slate-700 whitespace-pre-wrap">{detalle[field]}</p>
                        </div>
                    ))}
                    <div className="flex gap-2">
                        <button onClick={() => setAdapting(!adapting)} className="btn-ghost text-sm">Adaptar a nuevo centro</button>
                    </div>
                    {adapting && (
                        <div className="rounded-xl bg-slate-50 p-4 space-y-3">
                            <p className="text-sm font-semibold text-slate-700">Nuevo centro</p>
                            <div className="grid gap-2 sm:grid-cols-2">
                                <input className="input" placeholder="Nombre del centro" value={newCentro.nombre} onChange={(e) => setNewCentro((c) => ({ ...c, nombre: e.target.value }))} />
                                <input className="input" placeholder="Tipo (IES, CEIP…)" value={newCentro.tipo} onChange={(e) => setNewCentro((c) => ({ ...c, tipo: e.target.value }))} />
                            </div>
                            <label className="flex items-center gap-2 text-sm"><input type="checkbox" checked={newCentro.es_bilingue} onChange={(e) => setNewCentro((c) => ({ ...c, es_bilingue: e.target.checked }))} /> Bilingüe</label>
                            <button onClick={() => adaptarMutation.mutate(newCentro)} disabled={adaptarMutation.isPending} className="btn-primary text-sm">
                                {adaptarMutation.isPending ? 'Generando…' : 'Sugerir cambios con IA'}
                            </button>
                            {sugerencias?.cambios_sugeridos && (
                                <div className="space-y-2">
                                    <AiDisclaimer />
                                    {sugerencias.cambios_sugeridos.map((s, i) => (
                                        <div key={i} className="rounded-lg bg-white p-3 ring-1 ring-slate-200">
                                            <p className="text-xs font-semibold uppercase text-brand-700">{s.seccion}</p>
                                            <p className="text-xs text-slate-500">Actual: {s.cambio_actual}</p>
                                            <p className="text-sm text-slate-800">→ {s.cambio_sugerido}</p>
                                            <p className="text-xs text-slate-400 italic">{s.motivo}</p>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </div>
                    )}
                </div>
            )}

            {tab === 'unidades' && (
                <div className="rounded-xl bg-white ring-1 ring-slate-200">
                    {detalle.unidades?.length === 0 ? (
                        <p className="p-4 text-sm text-slate-400">Sin unidades didácticas. Añade la primera.</p>
                    ) : (
                        detalle.unidades?.map((ud) => <UnidadRow key={ud.id} unidad={ud} />)
                    )}
                </div>
            )}

            {tab === 'sesiones' && (
                <p className="text-sm text-slate-400">Las sesiones planificadas para esta asignatura aparecen en <Link to="/dashboard/docente/horario" className="text-brand-600 hover:underline">Mi horario</Link>.</p>
            )}
        </div>
    );
}

export default function ProgramacionPage() {
    const qc = useQueryClient();
    const [view, setView] = useState('list'); // list | new | detail
    const [selected, setSelected] = useState(null);

    const { data: asignaturas = [] } = useQuery({
        queryKey: ['docente-asignaturas'],
        queryFn: async () => (await api.get('/docente/asignaturas')).data.data ?? [],
    });

    const { data: programaciones = [], isLoading } = useQuery({
        queryKey: ['docente-programaciones'],
        queryFn: async () => (await api.get('/docente/programaciones')).data.data ?? [],
    });

    const saveMutation = useMutation({
        mutationFn: (form) => api.post('/docente/programaciones', form),
        onSuccess: () => { qc.invalidateQueries({ queryKey: ['docente-programaciones'] }); setView('list'); },
    });

    if (view === 'detail' && selected) {
        return (
            <div className="space-y-4">
                <ProgramacionDetail programacion={selected} onBack={() => { setSelected(null); setView('list'); }} />
            </div>
        );
    }

    if (view === 'new') {
        return (
            <div className="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
                <h2 className="mb-4 text-base font-bold text-slate-800">Nueva programación</h2>
                <ProgramacionForm
                    asignaturas={asignaturas}
                    onSave={(form) => saveMutation.mutate(form)}
                    onCancel={() => setView('list')}
                />
            </div>
        );
    }

    // Onboarding si no hay asignaturas
    if (!isLoading && asignaturas.length === 0) {
        return (
            <div className="flex flex-col items-center gap-6 py-16 text-center">
                <span className="text-5xl">🧑‍🏫</span>
                <div>
                    <h2 className="text-xl font-bold text-slate-800">Configura tu aula</h2>
                    <p className="mt-1 text-sm text-slate-500">Empieza añadiendo las asignaturas que impartes este curso.</p>
                </div>
                <Link to="/dashboard/docente/horario" className="btn-primary">Ir a Mi horario → configurar</Link>
            </div>
        );
    }

    return (
        <div className="space-y-4">
            <div className="flex items-center justify-between">
                <h1 className="text-lg font-bold text-slate-800">Mis programaciones</h1>
                <button onClick={() => setView('new')} className="btn-primary text-sm">+ Nueva programación</button>
            </div>

            {isLoading ? (
                <div className="space-y-2">
                    {[1, 2].map((i) => <div key={i} className="h-16 animate-pulse rounded-xl bg-slate-200" />)}
                </div>
            ) : programaciones.length === 0 ? (
                <div className="rounded-2xl bg-white p-8 text-center shadow-sm ring-1 ring-slate-200">
                    <p className="text-sm text-slate-400">Aún no tienes programaciones. Crea la primera.</p>
                </div>
            ) : (
                <div className="space-y-2">
                    {programaciones.map((p) => (
                        <button
                            key={p.id}
                            onClick={() => { setSelected(p); setView('detail'); }}
                            className="w-full rounded-xl bg-white p-4 text-left shadow-sm ring-1 ring-slate-200 hover:ring-brand-300 transition"
                        >
                            <div className="flex items-center gap-3">
                                <div className="flex-1">
                                    <p className="font-semibold text-slate-800">{p.titulo}</p>
                                    <p className="text-xs text-slate-400">{p.asignatura?.nombre ?? ''} · {p.año_academico}</p>
                                </div>
                                <span className={clsx('rounded-full px-2 py-0.5 text-xs font-bold', STATUS_STYLES[p.status] ?? STATUS_STYLES.borrador)}>
                                    {p.status}
                                </span>
                                <span className="text-slate-400">→</span>
                            </div>
                        </button>
                    ))}
                </div>
            )}
        </div>
    );
}
