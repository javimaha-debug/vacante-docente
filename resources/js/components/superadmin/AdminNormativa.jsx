import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api from '../../lib/api';
import { SkeletonRows, ErrorState, Badge } from './ui';

const CATEGORIAS = ['ley_organica', 'decreto', 'orden', 'resolucion', 'instrucciones', 'otro'];

const EMPTY = {
    titulo: '', descripcion: '', categoria: 'ley_organica',
    comunidad_autonoma: 'valenciana', especialidad_code: '', cuerpo: '',
    url_oficial: '', fecha_publicacion: '', vigente: true,
};

export default function AdminNormativa() {
    const qc = useQueryClient();
    const [editing, setEditing] = useState(null);
    const [creating, setCreating] = useState(false);

    const { data, isLoading, isError, error, refetch } = useQuery({
        queryKey: ['admin', 'normativa'],
        queryFn: async () => (await api.get('/superadmin/normativa')).data,
    });

    const refresh = () => qc.invalidateQueries({ queryKey: ['admin', 'normativa'] });

    const toggle = useMutation({
        mutationFn: async (doc) => (await api.patch(`/superadmin/normativa/${doc.id}`, { vigente: !doc.vigente })).data,
        onSuccess: refresh,
    });

    return (
        <div className="space-y-4">
            <div className="flex items-center justify-between">
                <h2 className="text-base font-semibold text-slate-200">Normativa</h2>
                <button onClick={() => setCreating(true)} className="rounded-lg bg-sky-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-sky-500">
                    + Subir documento
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
                                <th className="px-4 py-2">Categoría</th>
                                <th className="px-4 py-2">Comunidad</th>
                                <th className="px-4 py-2">Vigente</th>
                                <th className="px-4 py-2"></th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-800">
                            {data.data.map((d) => (
                                <tr key={d.id} className="hover:bg-slate-800/40">
                                    <td className="px-4 py-2">
                                        <p className="font-medium text-slate-200">{d.titulo}</p>
                                        {(d.pdf_url || d.url_oficial) && (
                                            <a href={d.pdf_url || d.url_oficial} target="_blank" rel="noopener noreferrer" className="text-xs text-sky-400 hover:text-sky-300">Ver ↗</a>
                                        )}
                                    </td>
                                    <td className="px-4 py-2"><Badge tone="slate">{d.categoria}</Badge></td>
                                    <td className="px-4 py-2 capitalize text-slate-300">{d.comunidad_autonoma}</td>
                                    <td className="px-4 py-2">
                                        <button
                                            onClick={() => toggle.mutate(d)}
                                            className={`relative h-5 w-9 rounded-full transition ${d.vigente ? 'bg-emerald-500' : 'bg-slate-600'}`}
                                            title={d.vigente ? 'Vigente' : 'Derogada'}
                                        >
                                            <span className={`absolute top-0.5 h-4 w-4 rounded-full bg-white transition ${d.vigente ? 'left-4' : 'left-0.5'}`} />
                                        </button>
                                    </td>
                                    <td className="px-4 py-2 text-right">
                                        <button onClick={() => setEditing(d)} className="text-xs font-medium text-sky-400 hover:text-sky-300">Editar</button>
                                    </td>
                                </tr>
                            ))}
                            {data.data.length === 0 && (
                                <tr><td colSpan={5} className="px-4 py-6 text-center text-slate-500">Sin documentos.</td></tr>
                            )}
                        </tbody>
                    </table>
                </div>
            )}

            {(creating || editing) && (
                <NormativaForm
                    doc={editing}
                    onClose={() => { setCreating(false); setEditing(null); }}
                    onSaved={() => { refresh(); setCreating(false); setEditing(null); }}
                />
            )}
        </div>
    );
}

function NormativaForm({ doc, onClose, onSaved }) {
    const isNew = !doc;
    const [form, setForm] = useState(() => ({
        ...EMPTY,
        ...(doc
            ? {
                titulo: doc.titulo ?? '', descripcion: doc.descripcion ?? '', categoria: doc.categoria ?? 'ley_organica',
                comunidad_autonoma: doc.comunidad_autonoma ?? 'valenciana', especialidad_code: doc.especialidad_code ?? '',
                cuerpo: doc.cuerpo ?? '', url_oficial: doc.url_oficial ?? '', fecha_publicacion: doc.fecha_publicacion ?? '',
                vigente: doc.vigente,
            }
            : {}),
    }));
    const [pdf, setPdf] = useState(null);

    const save = useMutation({
        mutationFn: async () => {
            const fd = new FormData();
            Object.entries(form).forEach(([k, v]) => {
                if (k === 'vigente') fd.append(k, v ? '1' : '0');
                else if (v !== '' && v != null) fd.append(k, v);
            });
            if (pdf) fd.append('pdf', pdf);
            const url = isNew ? '/superadmin/normativa' : `/superadmin/normativa/${doc.id}`;
            // PATCH can't carry multipart reliably; POST + _method=PATCH for updates.
            if (!isNew) fd.append('_method', 'PATCH');
            return (await api.post(url, fd, { headers: { 'Content-Type': 'multipart/form-data' } })).data;
        },
        onSuccess: onSaved,
    });

    const remove = useMutation({
        mutationFn: async () => (await api.delete(`/superadmin/normativa/${doc.id}`)).data,
        onSuccess: onSaved,
    });

    const set = (k) => (e) => setForm((f) => ({ ...f, [k]: e.target.value }));

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4" onClick={onClose}>
            <div className="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-xl border border-slate-700 bg-slate-900 p-5 text-slate-100" onClick={(e) => e.stopPropagation()}>
                <div className="flex items-center justify-between">
                    <h3 className="text-base font-semibold">{isNew ? 'Subir documento' : 'Editar documento'}</h3>
                    <button onClick={onClose} className="text-slate-400 hover:text-slate-200">✕</button>
                </div>
                <div className="mt-4 space-y-3">
                    <Field label="Título"><input value={form.titulo} onChange={set('titulo')} className={inputCls} /></Field>
                    <Field label="Descripción"><textarea value={form.descripcion} onChange={set('descripcion')} rows={2} className={inputCls} /></Field>
                    <div className="grid grid-cols-2 gap-3">
                        <Field label="Categoría">
                            <select value={form.categoria} onChange={set('categoria')} className={inputCls}>
                                {CATEGORIAS.map((c) => <option key={c} value={c}>{c}</option>)}
                            </select>
                        </Field>
                        <Field label="Comunidad"><input value={form.comunidad_autonoma} onChange={set('comunidad_autonoma')} className={inputCls} /></Field>
                    </div>
                    <div className="grid grid-cols-2 gap-3">
                        <Field label="Especialidad (código)"><input value={form.especialidad_code} onChange={set('especialidad_code')} placeholder="todas" className={inputCls} /></Field>
                        <Field label="Cuerpo"><input value={form.cuerpo} onChange={set('cuerpo')} placeholder="todos" className={inputCls} /></Field>
                    </div>
                    <Field label="URL oficial"><input value={form.url_oficial} onChange={set('url_oficial')} className={inputCls} /></Field>
                    <Field label="Fecha de publicación"><input type="date" value={form.fecha_publicacion ?? ''} onChange={set('fecha_publicacion')} className={inputCls} /></Field>
                    <Field label="PDF (opcional)"><input type="file" accept="application/pdf" onChange={(e) => setPdf(e.target.files?.[0] ?? null)} className="text-sm text-slate-300" /></Field>
                    <label className="flex items-center gap-2 text-sm text-slate-300">
                        <input type="checkbox" checked={form.vigente} onChange={(e) => setForm((f) => ({ ...f, vigente: e.target.checked }))} className="rounded text-sky-600" />
                        Vigente
                    </label>
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
