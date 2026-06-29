import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import clsx from 'clsx';
import api from '../../lib/api';

const TABS = ['rubricas', 'situaciones', 'examenes', 'adaptador'];
const TAB_LABELS = { rubricas: 'Rúbricas', situaciones: 'Sit. Aprendizaje', examenes: 'Exámenes', adaptador: 'Adaptador' };

function AiDisclaimer() {
    return (
        <p className="rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-700 ring-1 ring-amber-200">
            Generado por IA. Revisado y validado por el docente antes de su uso.
        </p>
    );
}

// ---- Rúbricas ----------------------------------------------------------------

function RubricaModal({ asignaturas, onClose }) {
    const qc = useQueryClient();
    const [form, setForm] = useState({ titulo: '', asignatura_id: '', tipo_tarea: '', nivel: '', num_criterios: 4, descripcion: '' });
    const [generated, setGenerated] = useState(null);
    const set = (k, v) => setForm((f) => ({ ...f, [k]: v }));

    const genMutation = useMutation({
        mutationFn: () => api.post('/docente/rubricas/generar', form),
        onSuccess: (res) => setGenerated(res.data),
    });

    const saveMutation = useMutation({
        mutationFn: (data) => api.post('/docente/rubricas', data),
        onSuccess: () => { qc.invalidateQueries({ queryKey: ['docente-rubricas'] }); onClose(); },
    });

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" onClick={onClose}>
            <div className="w-full max-w-lg max-h-[90vh] overflow-y-auto rounded-2xl bg-white p-6 shadow-xl" onClick={(e) => e.stopPropagation()}>
                <h3 className="mb-4 text-base font-bold text-slate-800">Nueva rúbrica</h3>
                {!generated ? (
                    <div className="space-y-3">
                        <div>
                            <label className="label">Título</label>
                            <input className="input" value={form.titulo} onChange={(e) => set('titulo', e.target.value)} placeholder="Rúbrica de presentación oral…" />
                        </div>
                        <div>
                            <label className="label">Asignatura</label>
                            <select className="input" value={form.asignatura_id} onChange={(e) => set('asignatura_id', e.target.value)}>
                                <option value="">— Opcional —</option>
                                {asignaturas.map((a) => <option key={a.id} value={a.id}>{a.nombre}</option>)}
                            </select>
                        </div>
                        <div>
                            <label className="label">Tipo de tarea</label>
                            <input className="input" value={form.tipo_tarea} onChange={(e) => set('tipo_tarea', e.target.value)} placeholder="Presentación oral, proyecto escrito…" />
                        </div>
                        <div>
                            <label className="label">Nivel / etapa</label>
                            <input className="input" value={form.nivel} onChange={(e) => set('nivel', e.target.value)} placeholder="4º ESO, 1º Bachillerato…" />
                        </div>
                        <div>
                            <label className="label">Número de criterios</label>
                            <input type="number" className="input" min={2} max={10} value={form.num_criterios} onChange={(e) => set('num_criterios', parseInt(e.target.value))} />
                        </div>
                        <div className="flex justify-end gap-2 pt-2">
                            <button onClick={onClose} className="btn-ghost">Cancelar</button>
                            <button onClick={() => genMutation.mutate()} disabled={genMutation.isPending || !form.titulo} className="btn-primary">
                                {genMutation.isPending ? 'Generando…' : 'Generar con IA'}
                            </button>
                        </div>
                    </div>
                ) : (
                    <div className="space-y-4">
                        <AiDisclaimer />
                        <div>
                            <p className="mb-1 text-xs font-semibold uppercase text-slate-400">Título</p>
                            <p className="text-sm text-slate-800">{generated.titulo}</p>
                        </div>
                        <div>
                            <p className="mb-1 text-xs font-semibold uppercase text-slate-400">Criterios ({generated.criterios?.length})</p>
                            <div className="space-y-2">
                                {generated.criterios?.map((c, i) => (
                                    <div key={i} className="rounded-lg bg-slate-50 p-3">
                                        <p className="text-sm font-semibold text-slate-800">{c.nombre}</p>
                                        <p className="mt-1 text-xs text-slate-500">{c.descripcion}</p>
                                    </div>
                                ))}
                            </div>
                        </div>
                        <div className="flex justify-end gap-2">
                            <button onClick={() => setGenerated(null)} className="btn-ghost">Regenerar</button>
                            <button onClick={() => saveMutation.mutate({ ...form, criterios: generated.criterios, titulo: generated.titulo })} disabled={saveMutation.isPending} className="btn-primary">
                                {saveMutation.isPending ? 'Guardando…' : 'Guardar rúbrica'}
                            </button>
                        </div>
                    </div>
                )}
            </div>
        </div>
    );
}

function RubricasTab({ asignaturas }) {
    const qc = useQueryClient();
    const [modal, setModal] = useState(false);
    const { data: rubricas = [], isLoading } = useQuery({
        queryKey: ['docente-rubricas'],
        queryFn: async () => (await api.get('/docente/rubricas')).data.data ?? [],
    });

    const compartirMutation = useMutation({
        mutationFn: (id) => api.post(`/docente/rubricas/${id}/compartir`),
        onSuccess: () => qc.invalidateQueries({ queryKey: ['docente-rubricas'] }),
    });
    const deleteMutation = useMutation({
        mutationFn: (id) => api.delete(`/docente/rubricas/${id}`),
        onSuccess: () => qc.invalidateQueries({ queryKey: ['docente-rubricas'] }),
    });

    return (
        <div>
            <div className="mb-3 flex justify-end">
                <button onClick={() => setModal(true)} className="btn-primary text-sm">+ Nueva rúbrica con IA</button>
            </div>
            {isLoading ? (
                <div className="space-y-2">{[1, 2].map((i) => <div key={i} className="h-14 animate-pulse rounded-xl bg-slate-200" />)}</div>
            ) : rubricas.length === 0 ? (
                <div className="rounded-2xl bg-white p-8 text-center shadow-sm ring-1 ring-slate-200">
                    <p className="text-sm text-slate-400">Sin rúbricas. Genera la primera con IA.</p>
                </div>
            ) : (
                <div className="space-y-2">
                    {rubricas.map((r) => (
                        <div key={r.id} className="flex items-center gap-3 rounded-xl bg-white p-3 shadow-sm ring-1 ring-slate-200">
                            <div className="flex-1 min-w-0">
                                <p className="truncate text-sm font-medium text-slate-800">{r.titulo}</p>
                                <p className="text-xs text-slate-400">{r.tipo_tarea ?? ''}{r.asignatura ? ` · ${r.asignatura.nombre}` : ''}</p>
                            </div>
                            <div className="flex shrink-0 items-center gap-1">
                                {r.es_publica && <span className="rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-semibold text-emerald-700">Compartida</span>}
                                <button onClick={() => compartirMutation.mutate(r.id)} disabled={r.es_publica || compartirMutation.isPending} className="btn-ghost text-xs">Compartir</button>
                                <button onClick={() => { if (confirm('¿Eliminar?')) deleteMutation.mutate(r.id); }} className="btn-ghost text-xs text-rose-500">Eliminar</button>
                            </div>
                        </div>
                    ))}
                </div>
            )}
            {modal && <RubricaModal asignaturas={asignaturas} onClose={() => setModal(false)} />}
        </div>
    );
}

// ---- Situaciones de Aprendizaje ---------------------------------------------

function SituacionModal({ asignaturas, onClose }) {
    const qc = useQueryClient();
    const [form, setForm] = useState({ titulo: '', asignatura_id: '', etapa: '', curso: '', descripcion: '', contexto: '' });
    const [generated, setGenerated] = useState(null);
    const set = (k, v) => setForm((f) => ({ ...f, [k]: v }));

    const genMutation = useMutation({
        mutationFn: () => api.post('/docente/situaciones/generar', form),
        onSuccess: (res) => setGenerated(res.data),
    });
    const saveMutation = useMutation({
        mutationFn: (data) => api.post('/docente/situaciones', data),
        onSuccess: () => { qc.invalidateQueries({ queryKey: ['docente-situaciones'] }); onClose(); },
    });

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" onClick={onClose}>
            <div className="w-full max-w-lg max-h-[90vh] overflow-y-auto rounded-2xl bg-white p-6 shadow-xl" onClick={(e) => e.stopPropagation()}>
                <h3 className="mb-4 text-base font-bold text-slate-800">Nueva situación de aprendizaje</h3>
                {!generated ? (
                    <div className="space-y-3">
                        <div><label className="label">Título</label><input className="input" value={form.titulo} onChange={(e) => set('titulo', e.target.value)} placeholder="El cambio climático…" /></div>
                        <div>
                            <label className="label">Asignatura</label>
                            <select className="input" value={form.asignatura_id} onChange={(e) => set('asignatura_id', e.target.value)}>
                                <option value="">— Opcional —</option>
                                {asignaturas.map((a) => <option key={a.id} value={a.id}>{a.nombre}</option>)}
                            </select>
                        </div>
                        <div className="grid grid-cols-2 gap-2">
                            <div><label className="label">Etapa</label><input className="input" value={form.etapa} onChange={(e) => set('etapa', e.target.value)} placeholder="ESO, Bachillerato…" /></div>
                            <div><label className="label">Curso</label><input className="input" value={form.curso} onChange={(e) => set('curso', e.target.value)} placeholder="3º ESO" /></div>
                        </div>
                        <div><label className="label">Contexto / motivación</label><textarea className="input min-h-[60px]" value={form.contexto} onChange={(e) => set('contexto', e.target.value)} rows={2} /></div>
                        <div className="flex justify-end gap-2 pt-2">
                            <button onClick={onClose} className="btn-ghost">Cancelar</button>
                            <button onClick={() => genMutation.mutate()} disabled={genMutation.isPending || !form.titulo} className="btn-primary">
                                {genMutation.isPending ? 'Generando…' : 'Generar con IA'}
                            </button>
                        </div>
                    </div>
                ) : (
                    <div className="space-y-4">
                        <AiDisclaimer />
                        <div><p className="mb-1 text-xs font-semibold uppercase text-slate-400">Título</p><p className="text-sm text-slate-800">{generated.titulo}</p></div>
                        {generated.descripcion && <div><p className="mb-1 text-xs font-semibold uppercase text-slate-400">Descripción</p><p className="text-sm text-slate-700">{generated.descripcion}</p></div>}
                        {generated.actividades?.length > 0 && (
                            <div>
                                <p className="mb-1 text-xs font-semibold uppercase text-slate-400">Actividades ({generated.actividades.length})</p>
                                <div className="space-y-1">
                                    {generated.actividades.slice(0, 3).map((a, i) => (
                                        <div key={i} className="rounded bg-slate-50 p-2 text-xs text-slate-700">{a.titulo ?? a}</div>
                                    ))}
                                    {generated.actividades.length > 3 && <p className="text-xs text-slate-400">+{generated.actividades.length - 3} más…</p>}
                                </div>
                            </div>
                        )}
                        <div className="flex justify-end gap-2">
                            <button onClick={() => setGenerated(null)} className="btn-ghost">Regenerar</button>
                            <button onClick={() => saveMutation.mutate({ ...form, ...generated })} disabled={saveMutation.isPending} className="btn-primary">
                                {saveMutation.isPending ? 'Guardando…' : 'Guardar'}
                            </button>
                        </div>
                    </div>
                )}
            </div>
        </div>
    );
}

function SituacionesTab({ asignaturas }) {
    const qc = useQueryClient();
    const [modal, setModal] = useState(false);
    const { data: situaciones = [], isLoading } = useQuery({
        queryKey: ['docente-situaciones'],
        queryFn: async () => (await api.get('/docente/situaciones')).data.data ?? [],
    });

    const compartirMutation = useMutation({
        mutationFn: (id) => api.post(`/docente/situaciones/${id}/compartir`),
        onSuccess: () => qc.invalidateQueries({ queryKey: ['docente-situaciones'] }),
    });
    const deleteMutation = useMutation({
        mutationFn: (id) => api.delete(`/docente/situaciones/${id}`),
        onSuccess: () => qc.invalidateQueries({ queryKey: ['docente-situaciones'] }),
    });

    return (
        <div>
            <div className="mb-3 flex justify-end">
                <button onClick={() => setModal(true)} className="btn-primary text-sm">+ Nueva SA con IA</button>
            </div>
            {isLoading ? (
                <div className="space-y-2">{[1, 2].map((i) => <div key={i} className="h-14 animate-pulse rounded-xl bg-slate-200" />)}</div>
            ) : situaciones.length === 0 ? (
                <div className="rounded-2xl bg-white p-8 text-center shadow-sm ring-1 ring-slate-200">
                    <p className="text-sm text-slate-400">Sin situaciones de aprendizaje. Crea la primera.</p>
                </div>
            ) : (
                <div className="space-y-2">
                    {situaciones.map((s) => (
                        <div key={s.id} className="flex items-center gap-3 rounded-xl bg-white p-3 shadow-sm ring-1 ring-slate-200">
                            <div className="flex-1 min-w-0">
                                <p className="truncate text-sm font-medium text-slate-800">{s.titulo}</p>
                                <p className="text-xs text-slate-400">{s.curso ?? ''}{s.asignatura ? ` · ${s.asignatura.nombre}` : ''}</p>
                            </div>
                            <div className="flex shrink-0 items-center gap-1">
                                {s.es_publica && <span className="rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-semibold text-emerald-700">Compartida</span>}
                                <button onClick={() => compartirMutation.mutate(s.id)} disabled={s.es_publica || compartirMutation.isPending} className="btn-ghost text-xs">Compartir</button>
                                <button onClick={() => { if (confirm('¿Eliminar?')) deleteMutation.mutate(s.id); }} className="btn-ghost text-xs text-rose-500">Eliminar</button>
                            </div>
                        </div>
                    ))}
                </div>
            )}
            {modal && <SituacionModal asignaturas={asignaturas} onClose={() => setModal(false)} />}
        </div>
    );
}

// ---- Exámenes ---------------------------------------------------------------

function ExamenModal({ asignaturas, onClose }) {
    const qc = useQueryClient();
    const [form, setForm] = useState({ titulo: '', asignatura_id: '', tipo: 'test', nivel: '', num_preguntas: 10, instrucciones: '' });
    const [generated, setGenerated] = useState(null);
    const set = (k, v) => setForm((f) => ({ ...f, [k]: v }));

    const genMutation = useMutation({
        mutationFn: () => api.post('/docente/examenes/generar', form),
        onSuccess: (res) => setGenerated(res.data),
    });
    const saveMutation = useMutation({
        mutationFn: (data) => api.post('/docente/examenes', data),
        onSuccess: () => { qc.invalidateQueries({ queryKey: ['docente-examenes'] }); onClose(); },
    });

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" onClick={onClose}>
            <div className="w-full max-w-lg max-h-[90vh] overflow-y-auto rounded-2xl bg-white p-6 shadow-xl" onClick={(e) => e.stopPropagation()}>
                <h3 className="mb-4 text-base font-bold text-slate-800">Nuevo examen</h3>
                {!generated ? (
                    <div className="space-y-3">
                        <div><label className="label">Título / tema</label><input className="input" value={form.titulo} onChange={(e) => set('titulo', e.target.value)} placeholder="Examen Unidad 3…" /></div>
                        <div>
                            <label className="label">Asignatura</label>
                            <select className="input" value={form.asignatura_id} onChange={(e) => set('asignatura_id', e.target.value)}>
                                <option value="">— Opcional —</option>
                                {asignaturas.map((a) => <option key={a.id} value={a.id}>{a.nombre}</option>)}
                            </select>
                        </div>
                        <div className="grid grid-cols-2 gap-2">
                            <div>
                                <label className="label">Tipo</label>
                                <select className="input" value={form.tipo} onChange={(e) => set('tipo', e.target.value)}>
                                    <option value="test">Test</option>
                                    <option value="desarrollo">Desarrollo</option>
                                    <option value="mixto">Mixto</option>
                                    <option value="oral">Oral</option>
                                </select>
                            </div>
                            <div><label className="label">Nº preguntas</label><input type="number" className="input" min={1} max={50} value={form.num_preguntas} onChange={(e) => set('num_preguntas', parseInt(e.target.value))} /></div>
                        </div>
                        <div><label className="label">Nivel</label><input className="input" value={form.nivel} onChange={(e) => set('nivel', e.target.value)} placeholder="2º ESO" /></div>
                        <div className="flex justify-end gap-2 pt-2">
                            <button onClick={onClose} className="btn-ghost">Cancelar</button>
                            <button onClick={() => genMutation.mutate()} disabled={genMutation.isPending || !form.titulo} className="btn-primary">
                                {genMutation.isPending ? 'Generando…' : 'Generar con IA'}
                            </button>
                        </div>
                    </div>
                ) : (
                    <div className="space-y-4">
                        <AiDisclaimer />
                        <div><p className="mb-1 text-xs font-semibold uppercase text-slate-400">Título</p><p className="text-sm font-medium text-slate-800">{generated.titulo}</p></div>
                        <div>
                            <p className="mb-1 text-xs font-semibold uppercase text-slate-400">Preguntas ({generated.preguntas?.length})</p>
                            <div className="space-y-2">
                                {generated.preguntas?.slice(0, 3).map((p, i) => (
                                    <div key={i} className="rounded-lg bg-slate-50 p-2">
                                        <p className="text-xs font-semibold text-slate-800">{i + 1}. {p.enunciado ?? p}</p>
                                    </div>
                                ))}
                                {generated.preguntas?.length > 3 && <p className="text-xs text-slate-400">+{generated.preguntas.length - 3} más…</p>}
                            </div>
                        </div>
                        <div className="flex justify-end gap-2">
                            <button onClick={() => setGenerated(null)} className="btn-ghost">Regenerar</button>
                            <button onClick={() => saveMutation.mutate({ ...form, preguntas: generated.preguntas, titulo: generated.titulo })} disabled={saveMutation.isPending} className="btn-primary">
                                {saveMutation.isPending ? 'Guardando…' : 'Guardar examen'}
                            </button>
                        </div>
                    </div>
                )}
            </div>
        </div>
    );
}

function ExamenesTab({ asignaturas }) {
    const qc = useQueryClient();
    const [modal, setModal] = useState(false);
    const { data: examenes = [], isLoading } = useQuery({
        queryKey: ['docente-examenes'],
        queryFn: async () => (await api.get('/docente/examenes')).data.data ?? [],
    });

    const deleteMutation = useMutation({
        mutationFn: (id) => api.delete(`/docente/examenes/${id}`),
        onSuccess: () => qc.invalidateQueries({ queryKey: ['docente-examenes'] }),
    });

    const handleExport = async (id) => {
        const res = await api.get(`/docente/examenes/${id}/export`);
        const blob = new Blob([JSON.stringify(res.data, null, 2)], { type: 'application/json' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a'); a.href = url; a.download = `examen-${id}.json`; a.click();
        URL.revokeObjectURL(url);
    };

    return (
        <div>
            <div className="mb-3 flex justify-end">
                <button onClick={() => setModal(true)} className="btn-primary text-sm">+ Nuevo examen con IA</button>
            </div>
            {isLoading ? (
                <div className="space-y-2">{[1, 2].map((i) => <div key={i} className="h-14 animate-pulse rounded-xl bg-slate-200" />)}</div>
            ) : examenes.length === 0 ? (
                <div className="rounded-2xl bg-white p-8 text-center shadow-sm ring-1 ring-slate-200">
                    <p className="text-sm text-slate-400">Sin exámenes. Genera el primero con IA.</p>
                </div>
            ) : (
                <div className="space-y-2">
                    {examenes.map((e) => (
                        <div key={e.id} className="flex items-center gap-3 rounded-xl bg-white p-3 shadow-sm ring-1 ring-slate-200">
                            <div className="flex-1 min-w-0">
                                <p className="truncate text-sm font-medium text-slate-800">{e.titulo}</p>
                                <p className="text-xs text-slate-400">{e.tipo} · {e.preguntas?.length ?? 0} preguntas{e.asignatura ? ` · ${e.asignatura.nombre}` : ''}</p>
                            </div>
                            <div className="flex shrink-0 items-center gap-1">
                                <button onClick={() => handleExport(e.id)} className="btn-ghost text-xs">Exportar</button>
                                <button onClick={() => { if (confirm('¿Eliminar?')) deleteMutation.mutate(e.id); }} className="btn-ghost text-xs text-rose-500">Eliminar</button>
                            </div>
                        </div>
                    ))}
                </div>
            )}
            {modal && <ExamenModal asignaturas={asignaturas} onClose={() => setModal(false)} />}
        </div>
    );
}

// ---- Adaptador de textos -----------------------------------------------------

const TIPO_OPTS = [
    { value: 'simplificar', label: 'Simplificar' },
    { value: 'ampliar', label: 'Ampliar' },
    { value: 'ACIS', label: 'ACIS (adaptación curricular)' },
];

function AdaptadorTab() {
    const [form, setForm] = useState({ texto: '', nivel_original: '', nivel_destino: '', tipo_adaptacion: 'simplificar' });
    const [result, setResult] = useState(null);
    const [error, setError] = useState(null);
    const set = (k, v) => setForm((f) => ({ ...f, [k]: v }));

    const mutation = useMutation({
        mutationFn: () => api.post('/docente/adaptar-texto', form),
        onSuccess: (res) => { setResult(res.data); setError(null); },
        onError: (err) => setError(err.response?.data?.error ?? 'Error al adaptar el texto.'),
    });

    const wordCount = form.texto.trim().split(/\s+/).filter(Boolean).length;

    return (
        <div className="space-y-4">
            <div className="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200 space-y-4">
                <div>
                    <label className="label">Texto original</label>
                    <textarea
                        className="input min-h-[120px]"
                        value={form.texto}
                        onChange={(e) => set('texto', e.target.value)}
                        placeholder="Pega aquí el texto que quieres adaptar…"
                        rows={5}
                    />
                    <p className={clsx('mt-1 text-right text-xs', wordCount > 5000 ? 'text-rose-500' : 'text-slate-400')}>
                        {wordCount} / 5000 palabras
                    </p>
                </div>
                <div className="grid gap-3 sm:grid-cols-3">
                    <div>
                        <label className="label">Nivel original</label>
                        <input className="input" value={form.nivel_original} onChange={(e) => set('nivel_original', e.target.value)} placeholder="4º ESO" />
                    </div>
                    <div>
                        <label className="label">Nivel destino</label>
                        <input className="input" value={form.nivel_destino} onChange={(e) => set('nivel_destino', e.target.value)} placeholder="2º ESO" />
                    </div>
                    <div>
                        <label className="label">Tipo</label>
                        <select className="input" value={form.tipo_adaptacion} onChange={(e) => set('tipo_adaptacion', e.target.value)}>
                            {TIPO_OPTS.map((o) => <option key={o.value} value={o.value}>{o.label}</option>)}
                        </select>
                    </div>
                </div>
                {error && <p className="rounded-lg bg-rose-50 px-3 py-2 text-sm text-rose-700">{error}</p>}
                <div className="flex justify-end">
                    <button
                        onClick={() => mutation.mutate()}
                        disabled={mutation.isPending || !form.texto || !form.nivel_original || !form.nivel_destino || wordCount > 5000}
                        className="btn-primary"
                    >
                        {mutation.isPending ? 'Adaptando…' : 'Adaptar texto'}
                    </button>
                </div>
            </div>

            {result && (
                <div className="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200 space-y-3">
                    <AiDisclaimer />
                    <div>
                        <p className="mb-1 text-xs font-semibold uppercase text-slate-400">Texto adaptado</p>
                        <p className="whitespace-pre-wrap text-sm text-slate-800">{result.texto_adaptado}</p>
                    </div>
                    {result.cambios_realizados?.length > 0 && (
                        <div>
                            <p className="mb-1 text-xs font-semibold uppercase text-slate-400">Cambios realizados</p>
                            <ul className="list-disc pl-4 space-y-0.5">
                                {result.cambios_realizados.map((c, i) => <li key={i} className="text-xs text-slate-600">{c}</li>)}
                            </ul>
                        </div>
                    )}
                    <button
                        onClick={() => {
                            navigator.clipboard.writeText(result.texto_adaptado);
                        }}
                        className="btn-ghost text-sm"
                    >
                        Copiar al portapapeles
                    </button>
                </div>
            )}
        </div>
    );
}

// ---- Main -------------------------------------------------------------------

export default function RecursosPage() {
    const [tab, setTab] = useState('rubricas');

    const { data: asignaturas = [] } = useQuery({
        queryKey: ['docente-asignaturas'],
        queryFn: async () => (await api.get('/docente/asignaturas')).data.data ?? [],
    });

    return (
        <div className="space-y-4">
            <h1 className="text-lg font-bold text-slate-800">Mis recursos</h1>

            <div className="flex gap-1 border-b border-slate-200">
                {TABS.map((t) => (
                    <button
                        key={t}
                        onClick={() => setTab(t)}
                        className={clsx(
                            'px-4 py-2 text-sm font-medium',
                            tab === t ? 'border-b-2 border-brand-600 text-brand-700' : 'text-slate-500 hover:text-slate-700'
                        )}
                    >
                        {TAB_LABELS[t]}
                    </button>
                ))}
            </div>

            <div>
                {tab === 'rubricas' && <RubricasTab asignaturas={asignaturas} />}
                {tab === 'situaciones' && <SituacionesTab asignaturas={asignaturas} />}
                {tab === 'examenes' && <ExamenesTab asignaturas={asignaturas} />}
                {tab === 'adaptador' && <AdaptadorTab />}
            </div>
        </div>
    );
}
