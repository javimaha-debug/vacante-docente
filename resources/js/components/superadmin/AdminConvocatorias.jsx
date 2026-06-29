import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api from '../../lib/api';
import { SkeletonRows, ErrorState, Badge } from './ui';

const ESTADOS = ['rumor', 'anunciada', 'convocada', 'en_proceso', 'resuelta'];
const ESTADO_TONE = { rumor: 'slate', anunciada: 'amber', convocada: 'blue', en_proceso: 'blue', resuelta: 'green' };
const CUERPOS = ['', 'maestros', 'secundaria', 'fp', 'otros'];

const EMPTY = {
    titulo: '', comunidad_autonoma: 'valenciana', cuerpo: '', estado: 'rumor',
    fecha_estimada: '', fecha_oficial: '', url_oficial: '', boe_url: '', notas: '', source_document_id: '',
};

export default function AdminConvocatorias() {
    const qc = useQueryClient();
    const [editing, setEditing] = useState(null); // id being edited
    const [creating, setCreating] = useState(false);

    const { data, isLoading, isError, error, refetch } = useQuery({
        queryKey: ['admin', 'convocatorias'],
        queryFn: async () => (await api.get('/superadmin/convocatorias')).data,
    });

    const { data: docs } = useQuery({
        queryKey: ['admin', 'documents', 'for-convocatorias'],
        queryFn: async () => (await api.get('/superadmin/documents')).data,
    });

    const refresh = () => qc.invalidateQueries({ queryKey: ['admin', 'convocatorias'] });
    // /superadmin/documents is paginated → detected documents live under data.data.
    const noticias = docs?.data ?? [];

    return (
        <div className="space-y-4">
            <div className="flex items-center justify-between">
                <h2 className="text-base font-semibold text-slate-200">Convocatorias</h2>
                <button onClick={() => setCreating(true)} className="rounded-lg bg-sky-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-sky-500">
                    + Nueva convocatoria
                </button>
            </div>

            {isLoading ? (
                <SkeletonRows rows={6} />
            ) : isError ? (
                <ErrorState error={error} onRetry={refetch} />
            ) : (
                <div className="overflow-x-auto rounded-xl border border-slate-700/60">
                    <table className="min-w-full divide-y divide-slate-700/60 text-sm">
                        <thead className="bg-slate-800/60 text-left text-xs uppercase tracking-wide text-slate-400">
                            <tr>
                                <th className="px-4 py-2">Título</th>
                                <th className="px-4 py-2">Comunidad</th>
                                <th className="px-4 py-2">Estado</th>
                                <th className="px-4 py-2">Fecha</th>
                                <th className="px-4 py-2">Documento</th>
                                <th className="px-4 py-2"></th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-800">
                            {data.data.map((c) => (
                                <tr key={c.id} className="align-top hover:bg-slate-800/40">
                                    <td className="px-4 py-2">
                                        <p className="font-medium text-slate-200">{c.titulo}</p>
                                        {c.cuerpo && <p className="text-xs capitalize text-slate-500">{c.cuerpo}</p>}
                                    </td>
                                    <td className="px-4 py-2 capitalize text-slate-300">{c.comunidad_autonoma}</td>
                                    <td className="px-4 py-2"><Badge tone={ESTADO_TONE[c.estado]}>{c.estado}</Badge></td>
                                    <td className="px-4 py-2 text-xs text-slate-400">
                                        {c.fecha_oficial || c.fecha_estimada
                                            ? new Date(c.fecha_oficial || c.fecha_estimada).toLocaleDateString('es-ES')
                                            : '—'}
                                    </td>
                                    <td className="px-4 py-2 text-xs text-slate-500">{c.source_document?.titulo ?? '—'}</td>
                                    <td className="px-4 py-2 text-right">
                                        <button onClick={() => setEditing(c)} className="text-xs font-medium text-sky-400 hover:text-sky-300">Editar</button>
                                    </td>
                                </tr>
                            ))}
                            {data.data.length === 0 && (
                                <tr><td colSpan={6} className="px-4 py-6 text-center text-slate-500">Sin convocatorias.</td></tr>
                            )}
                        </tbody>
                    </table>
                </div>
            )}

            {(creating || editing) && (
                <ConvocatoriaForm
                    convocatoria={editing}
                    noticias={noticias}
                    onClose={() => { setCreating(false); setEditing(null); }}
                    onSaved={() => { refresh(); setCreating(false); setEditing(null); }}
                />
            )}
        </div>
    );
}

function ConvocatoriaForm({ convocatoria, noticias, onClose, onSaved }) {
    const isNew = !convocatoria;
    const [form, setForm] = useState(() => ({
        ...EMPTY,
        ...(convocatoria
            ? {
                titulo: convocatoria.titulo ?? '',
                comunidad_autonoma: convocatoria.comunidad_autonoma ?? 'valenciana',
                cuerpo: convocatoria.cuerpo ?? '',
                estado: convocatoria.estado ?? 'rumor',
                fecha_estimada: convocatoria.fecha_estimada ?? '',
                fecha_oficial: convocatoria.fecha_oficial ?? '',
                url_oficial: convocatoria.url_oficial ?? '',
                boe_url: convocatoria.boe_url ?? '',
                notas: convocatoria.notas ?? '',
                source_document_id: convocatoria.source_document_id ?? '',
            }
            : {}),
    }));

    const save = useMutation({
        mutationFn: async () => {
            const payload = Object.fromEntries(Object.entries(form).map(([k, v]) => [k, v === '' ? null : v]));
            if (isNew) return (await api.post('/superadmin/convocatorias', payload)).data;
            return (await api.patch(`/superadmin/convocatorias/${convocatoria.id}`, payload)).data;
        },
        onSuccess: onSaved,
    });

    const remove = useMutation({
        mutationFn: async () => (await api.delete(`/superadmin/convocatorias/${convocatoria.id}`)).data,
        onSuccess: onSaved,
    });

    const set = (k) => (e) => setForm((f) => ({ ...f, [k]: e.target.value }));

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4" onClick={onClose}>
            <div className="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-xl border border-slate-700 bg-slate-900 p-5 text-slate-100" onClick={(e) => e.stopPropagation()}>
                <div className="flex items-center justify-between">
                    <h3 className="text-base font-semibold">{isNew ? 'Nueva convocatoria' : 'Editar convocatoria'}</h3>
                    <button onClick={onClose} className="text-slate-400 hover:text-slate-200">✕</button>
                </div>
                <div className="mt-4 space-y-3">
                    <Field label="Título"><input value={form.titulo} onChange={set('titulo')} className={inputCls} /></Field>
                    <Field label="Comunidad autónoma"><input value={form.comunidad_autonoma} onChange={set('comunidad_autonoma')} className={inputCls} /></Field>
                    <div className="grid grid-cols-2 gap-3">
                        <Field label="Cuerpo">
                            <select value={form.cuerpo} onChange={set('cuerpo')} className={inputCls}>
                                {CUERPOS.map((c) => <option key={c} value={c}>{c || '—'}</option>)}
                            </select>
                        </Field>
                        <Field label="Estado">
                            <select value={form.estado} onChange={set('estado')} className={inputCls}>
                                {ESTADOS.map((s) => <option key={s} value={s}>{s}</option>)}
                            </select>
                        </Field>
                    </div>
                    <div className="grid grid-cols-2 gap-3">
                        <Field label="Fecha estimada"><input type="date" value={form.fecha_estimada ?? ''} onChange={set('fecha_estimada')} className={inputCls} /></Field>
                        <Field label="Fecha oficial"><input type="date" value={form.fecha_oficial ?? ''} onChange={set('fecha_oficial')} className={inputCls} /></Field>
                    </div>
                    <Field label="URL oficial"><input value={form.url_oficial} onChange={set('url_oficial')} className={inputCls} /></Field>
                    <Field label="BOE / DOGV URL"><input value={form.boe_url} onChange={set('boe_url')} className={inputCls} /></Field>
                    <Field label="Documento detectado (origen)">
                        <select value={form.source_document_id ?? ''} onChange={set('source_document_id')} className={inputCls}>
                            <option value="">— Sin vincular —</option>
                            {noticias.map((n) => <option key={n.id} value={n.id}>{n.title}</option>)}
                        </select>
                    </Field>
                    <Field label="Notas"><textarea value={form.notas} onChange={set('notas')} rows={2} className={inputCls} /></Field>
                </div>
                <div className="mt-4 flex items-center justify-between">
                    {!isNew ? (
                        <button onClick={() => remove.mutate()} className="text-sm font-medium text-rose-400 hover:text-rose-300">Eliminar</button>
                    ) : <span />}
                    <div className="flex gap-2">
                        <button onClick={onClose} className="rounded-lg px-3 py-1.5 text-sm text-slate-300 hover:bg-slate-800">Cancelar</button>
                        <button
                            disabled={save.isPending || !form.titulo}
                            onClick={() => save.mutate()}
                            className="rounded-lg bg-sky-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-sky-500 disabled:opacity-60"
                        >
                            {save.isPending ? 'Guardando…' : 'Guardar'}
                        </button>
                    </div>
                </div>
                {save.isError && <p className="mt-2 text-xs text-rose-400">{save.error?.friendlyMessage ?? 'No se pudo guardar.'}</p>}
            </div>
        </div>
    );
}

function Field({ label, children }) {
    return (
        <label className="block">
            <span className="block text-xs font-medium uppercase tracking-wide text-slate-400">{label}</span>
            <div className="mt-1">{children}</div>
        </label>
    );
}

const inputCls = 'w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-slate-100 focus:border-sky-500 focus:ring-sky-500';
