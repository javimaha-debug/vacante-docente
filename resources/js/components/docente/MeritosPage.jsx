import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import clsx from 'clsx';
import api from '../../lib/api';

const TIPO_LABELS = {
    formacion: 'Formación',
    publicacion: 'Publicación',
    cargo: 'Cargo',
    actividad_complementaria: 'Act. complementaria',
    otro: 'Otro',
};
const TIPO_OPTS = Object.keys(TIPO_LABELS);
const TIPO_COLORS = {
    formacion: 'bg-blue-50 text-blue-700',
    publicacion: 'bg-purple-50 text-purple-700',
    cargo: 'bg-amber-50 text-amber-700',
    actividad_complementaria: 'bg-emerald-50 text-emerald-700',
    otro: 'bg-slate-100 text-slate-600',
};

function MeritoModal({ initial, onClose }) {
    const qc = useQueryClient();
    const isEdit = Boolean(initial?.id);
    const [form, setForm] = useState({
        tipo: initial?.tipo ?? 'formacion',
        titulo: initial?.titulo ?? '',
        organismo: initial?.organismo ?? '',
        horas: initial?.horas ?? '',
        creditos_ects: initial?.creditos_ects ?? '',
        fecha_inicio: initial?.fecha_inicio ?? '',
        fecha_fin: initial?.fecha_fin ?? '',
    });
    const set = (k, v) => setForm((f) => ({ ...f, [k]: v }));

    const mutation = useMutation({
        mutationFn: (data) =>
            isEdit ? api.put(`/docente/meritos/${initial.id}`, data) : api.post('/docente/meritos', data),
        onSuccess: () => { qc.invalidateQueries({ queryKey: ['docente-meritos'] }); onClose(); },
    });

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" onClick={onClose}>
            <div className="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl" onClick={(e) => e.stopPropagation()}>
                <h3 className="mb-4 text-base font-bold text-slate-800">{isEdit ? 'Editar mérito' : 'Añadir mérito'}</h3>
                <div className="space-y-3">
                    <div>
                        <label className="label">Tipo</label>
                        <select className="input" value={form.tipo} onChange={(e) => set('tipo', e.target.value)}>
                            {TIPO_OPTS.map((t) => <option key={t} value={t}>{TIPO_LABELS[t]}</option>)}
                        </select>
                    </div>
                    <div>
                        <label className="label">Título / descripción</label>
                        <input className="input" value={form.titulo} onChange={(e) => set('titulo', e.target.value)} placeholder="Curso de formación en…" />
                    </div>
                    <div>
                        <label className="label">Organismo / entidad</label>
                        <input className="input" value={form.organismo} onChange={(e) => set('organismo', e.target.value)} placeholder="CEFIRE, Universidad…" />
                    </div>
                    <div className="grid grid-cols-2 gap-2">
                        {form.tipo === 'formacion' && (
                            <>
                                <div>
                                    <label className="label">Horas</label>
                                    <input type="number" className="input" value={form.horas} onChange={(e) => set('horas', e.target.value)} min={0} />
                                </div>
                                <div>
                                    <label className="label">Créditos ECTS</label>
                                    <input type="number" step="0.1" className="input" value={form.creditos_ects} onChange={(e) => set('creditos_ects', e.target.value)} min={0} />
                                </div>
                            </>
                        )}
                    </div>
                    <div className="grid grid-cols-2 gap-2">
                        <div>
                            <label className="label">Fecha inicio</label>
                            <input type="date" className="input" value={form.fecha_inicio} onChange={(e) => set('fecha_inicio', e.target.value)} />
                        </div>
                        <div>
                            <label className="label">Fecha fin</label>
                            <input type="date" className="input" value={form.fecha_fin} onChange={(e) => set('fecha_fin', e.target.value)} />
                        </div>
                    </div>
                </div>
                <div className="mt-4 flex justify-end gap-2">
                    <button onClick={onClose} className="btn-ghost">Cancelar</button>
                    <button onClick={() => mutation.mutate(form)} disabled={mutation.isPending || !form.titulo} className="btn-primary">
                        {mutation.isPending ? 'Guardando…' : 'Guardar'}
                    </button>
                </div>
            </div>
        </div>
    );
}

function BaremoPanel() {
    const [result, setResult] = useState(null);
    const [loading, setLoading] = useState(false);

    const calcular = async () => {
        setLoading(true);
        try {
            const res = await api.post('/docente/meritos/baremo');
            setResult(res.data);
        } finally {
            setLoading(false);
        }
    };

    const exportar = () => {
        if (!result) return;
        const lines = [
            'BAREMO DOCENTE',
            '==============',
            '',
            `Puntuación total: ${result.total}`,
            '',
            'Desglose:',
            ...Object.entries(result.desglose ?? {}).map(([k, v]) => `  ${TIPO_LABELS[k] ?? k}: ${v}`),
            '',
            result.disclaimer ?? '',
        ];
        const blob = new Blob([lines.join('\n')], { type: 'text/plain' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a'); a.href = url; a.download = 'baremo.txt'; a.click();
        URL.revokeObjectURL(url);
    };

    return (
        <div className="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200 space-y-4">
            <div className="flex items-center justify-between">
                <h2 className="text-sm font-bold text-slate-700">Cálculo de baremo</h2>
                <div className="flex gap-2">
                    {result && (
                        <button onClick={exportar} className="btn-ghost text-xs">Exportar</button>
                    )}
                    <button onClick={calcular} disabled={loading} className="btn-primary text-sm">
                        {loading ? 'Calculando…' : 'Calcular baremo'}
                    </button>
                </div>
            </div>

            {result && (
                <div className="space-y-3">
                    {result.disclaimer && (
                        <p className="rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-700 ring-1 ring-amber-200">
                            {result.disclaimer}
                        </p>
                    )}
                    <div className="rounded-xl bg-brand-50 px-4 py-3 text-center">
                        <p className="text-xs font-semibold uppercase tracking-wide text-brand-600">Puntuación total</p>
                        <p className="text-3xl font-bold text-brand-700">{result.total}</p>
                    </div>
                    {result.desglose && (
                        <div className="space-y-2">
                            {Object.entries(result.desglose).map(([tipo, pts]) => (
                                <div key={tipo} className="flex items-center justify-between rounded-lg bg-slate-50 px-3 py-2">
                                    <span className={clsx('rounded-full px-2 py-0.5 text-xs font-semibold', TIPO_COLORS[tipo] ?? TIPO_COLORS.otro)}>
                                        {TIPO_LABELS[tipo] ?? tipo}
                                    </span>
                                    <span className="text-sm font-bold text-slate-700">{pts} pts</span>
                                </div>
                            ))}
                        </div>
                    )}
                    {result.observaciones && (
                        <p className="text-xs text-slate-500 italic">{result.observaciones}</p>
                    )}
                </div>
            )}
        </div>
    );
}

export default function MeritosPage() {
    const qc = useQueryClient();
    const [modal, setModal] = useState(false);
    const [editing, setEditing] = useState(null);
    const [filterTipo, setFilterTipo] = useState('');

    const { data: meritos = [], isLoading } = useQuery({
        queryKey: ['docente-meritos'],
        queryFn: async () => (await api.get('/docente/meritos')).data.data ?? [],
    });

    const deleteMutation = useMutation({
        mutationFn: (id) => api.delete(`/docente/meritos/${id}`),
        onSuccess: () => qc.invalidateQueries({ queryKey: ['docente-meritos'] }),
    });

    const filtrados = filterTipo ? meritos.filter((m) => m.tipo === filterTipo) : meritos;

    const totalPuntos = meritos.reduce((acc, m) => acc + (parseFloat(m.puntos_calculados ?? 0)), 0).toFixed(3);

    return (
        <div className="space-y-5">
            <div className="flex items-center justify-between">
                <h1 className="text-lg font-bold text-slate-800">Mis méritos</h1>
                <button onClick={() => setModal(true)} className="btn-primary text-sm">+ Añadir mérito</button>
            </div>

            <BaremoPanel />

            <div>
                <div className="mb-3 flex flex-wrap items-center gap-2">
                    <div className="flex gap-1">
                        <button onClick={() => setFilterTipo('')} className={clsx('rounded-lg px-3 py-1.5 text-xs font-medium', !filterTipo ? 'bg-brand-600 text-white' : 'bg-white text-slate-600 ring-1 ring-slate-200 hover:bg-slate-50')}>
                            Todos ({meritos.length})
                        </button>
                        {TIPO_OPTS.map((t) => {
                            const count = meritos.filter((m) => m.tipo === t).length;
                            if (count === 0) return null;
                            return (
                                <button key={t} onClick={() => setFilterTipo(t)} className={clsx('rounded-lg px-3 py-1.5 text-xs font-medium', filterTipo === t ? 'bg-brand-600 text-white' : 'bg-white text-slate-600 ring-1 ring-slate-200 hover:bg-slate-50')}>
                                    {TIPO_LABELS[t]} ({count})
                                </button>
                            );
                        })}
                    </div>
                    {meritos.length > 0 && (
                        <p className="ml-auto text-xs text-slate-400">Total calculado: <span className="font-semibold text-slate-700">{totalPuntos} pts</span></p>
                    )}
                </div>

                {isLoading ? (
                    <div className="space-y-2">{[1, 2, 3].map((i) => <div key={i} className="h-16 animate-pulse rounded-xl bg-slate-200" />)}</div>
                ) : filtrados.length === 0 ? (
                    <div className="rounded-2xl bg-white p-8 text-center shadow-sm ring-1 ring-slate-200">
                        <p className="text-sm text-slate-400">
                            {meritos.length === 0
                                ? 'Sin méritos registrados. Añade tus formaciones, publicaciones y cargos.'
                                : 'Sin méritos de ese tipo.'}
                        </p>
                    </div>
                ) : (
                    <div className="space-y-2">
                        {filtrados.map((m) => (
                            <div key={m.id} className="flex items-center gap-3 rounded-xl bg-white p-3 shadow-sm ring-1 ring-slate-200">
                                <span className={clsx('shrink-0 rounded-full px-2 py-0.5 text-[10px] font-semibold', TIPO_COLORS[m.tipo] ?? TIPO_COLORS.otro)}>
                                    {TIPO_LABELS[m.tipo] ?? m.tipo}
                                </span>
                                <div className="flex-1 min-w-0">
                                    <p className="truncate text-sm font-medium text-slate-800">{m.titulo}</p>
                                    <p className="text-xs text-slate-400">
                                        {m.organismo ?? ''}
                                        {m.horas ? ` · ${m.horas}h` : ''}
                                        {m.fecha_inicio ? ` · ${m.fecha_inicio}` : ''}
                                    </p>
                                </div>
                                {m.puntos_calculados != null && (
                                    <span className="shrink-0 text-sm font-bold text-brand-700">{m.puntos_calculados} pts</span>
                                )}
                                <div className="shrink-0 flex gap-1">
                                    <button onClick={() => setEditing(m)} className="btn-ghost text-xs">Editar</button>
                                    <button onClick={() => { if (confirm('¿Eliminar mérito?')) deleteMutation.mutate(m.id); }} className="btn-ghost text-xs text-rose-500">Eliminar</button>
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </div>

            {modal && <MeritoModal onClose={() => setModal(false)} />}
            {editing && <MeritoModal initial={editing} onClose={() => setEditing(null)} />}
        </div>
    );
}
