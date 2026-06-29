import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import clsx from 'clsx';
import api from '../../lib/api';
import { useAuth } from '../../hooks/useAuth';
import { useEscapeKey } from '../../hooks/useEscapeKey';
import { SectionTitle } from './shared';

const CATEGORIA_LABEL = {
    ley_organica: 'Ley orgánica',
    decreto: 'Decreto',
    orden: 'Orden',
    resolucion: 'Resolución',
    instrucciones: 'Instrucciones',
    otro: 'Otro',
};

const CATEGORIA_BADGE = {
    ley_organica: 'bg-brand-50 text-brand-700',
    decreto: 'bg-blue-50 text-blue-700',
    orden: 'bg-amber-50 text-amber-700',
    resolucion: 'bg-purple-50 text-purple-700',
    instrucciones: 'bg-slate-100 text-slate-600',
    otro: 'bg-slate-100 text-slate-600',
};

const COMUNIDADES = [
    { value: '', label: 'Todas las comunidades' },
    { value: 'nacional', label: 'Nacional' },
    { value: 'valenciana', label: 'Comunitat Valenciana' },
];

const FUENTE_BADGE = {
    boe: { label: 'BOE', cls: 'bg-blue-50 text-blue-700' },
    dogv: { label: 'DOGV', cls: 'bg-purple-50 text-purple-700' },
    manual: { label: 'Manual', cls: 'bg-slate-100 text-slate-500' },
};

const IDIOMAS = [
    { value: '', label: 'Todos los idiomas' },
    { value: 'castellano', label: 'Castellano' },
    { value: 'valenciano', label: 'Valenciano' },
];

// Logical sections shown as headers in the grid.
function sectionFor(doc) {
    if (doc.comunidad_autonoma === 'nacional') return 'Normativa básica nacional';
    if (doc.especialidad_code) return 'Por especialidad';
    return 'Normativa CV';
}

const SECTION_ORDER = ['Normativa básica nacional', 'Normativa CV', 'Por especialidad'];

export default function Normativa() {
    const { user } = useAuth();
    const isAdmin = Boolean(user?.is_admin) || Boolean(user?.is_superadmin);
    const [filters, setFilters] = useState({ categoria: '', comunidad: '', vigente: '', idioma: '' });
    const [showUpload, setShowUpload] = useState(false);
    const qc = useQueryClient();

    const { data, isLoading, isError, error } = useQuery({
        queryKey: ['normativa', filters],
        queryFn: async () => (await api.get('/normativa', {
            params: {
                categoria: filters.categoria || undefined,
                comunidad: filters.comunidad || undefined,
                vigente: filters.vigente === '' ? undefined : filters.vigente,
                idioma: filters.idioma || undefined,
            },
        })).data,
    });

    const docs = data?.data ?? [];
    const bySection = {};
    for (const d of docs) {
        const s = sectionFor(d);
        (bySection[s] ??= []).push(d);
    }

    const refresh = () => qc.invalidateQueries({ queryKey: ['normativa'] });
    const anyFilter = Boolean(filters.categoria || filters.comunidad || filters.vigente || filters.idioma);

    return (
        <div className="relative mx-auto max-w-5xl">
            <div className="mb-4">
                <h1 className="font-heading text-xl font-bold text-slate-800">Normativa</h1>
                <p className="text-sm text-slate-500">Leyes, decretos y órdenes clave para tu oposición.</p>
            </div>

            {/* Filter bar */}
            <div className="mb-5 flex flex-wrap gap-2">
                <select value={filters.categoria} onChange={(e) => setFilters((f) => ({ ...f, categoria: e.target.value }))} className={selectCls}>
                    <option value="">Todas las categorías</option>
                    {Object.entries(CATEGORIA_LABEL).map(([v, l]) => <option key={v} value={v}>{l}</option>)}
                </select>
                <select value={filters.comunidad} onChange={(e) => setFilters((f) => ({ ...f, comunidad: e.target.value }))} className={selectCls}>
                    {COMUNIDADES.map((c) => <option key={c.value} value={c.value}>{c.label}</option>)}
                </select>
                <select value={filters.vigente} onChange={(e) => setFilters((f) => ({ ...f, vigente: e.target.value }))} className={selectCls}>
                    <option value="">Vigentes y derogadas</option>
                    <option value="1">Solo vigentes</option>
                    <option value="0">Solo derogadas</option>
                </select>
                <select value={filters.idioma} onChange={(e) => setFilters((f) => ({ ...f, idioma: e.target.value }))} className={selectCls}>
                    {IDIOMAS.map((i) => <option key={i.value} value={i.value}>{i.label}</option>)}
                </select>
            </div>

            {isError ? (
                <p className="rounded-lg bg-rose-50 px-3 py-2 text-sm text-rose-600">{error?.friendlyMessage ?? 'No se pudo cargar la normativa.'}</p>
            ) : isLoading ? (
                <p className="text-sm text-slate-400">Cargando…</p>
            ) : docs.length === 0 ? (
                <div className="rounded-2xl bg-white p-8 text-center shadow-sm ring-1 ring-slate-200">
                    {anyFilter ? (
                        <>
                            <p className="text-sm font-medium text-slate-600">No hay documentos para estos filtros.</p>
                            <p className="mt-1 text-sm text-slate-400">Prueba a quitar algún filtro para ver más normativa.</p>
                        </>
                    ) : (
                        <>
                            <div className="text-3xl">📚</div>
                            <p className="mt-2 text-sm font-medium text-slate-600">El equipo está cargando la normativa.</p>
                            <p className="mt-1 text-sm text-slate-400">Vuelve pronto.</p>
                        </>
                    )}
                </div>
            ) : (
                <div className="space-y-8">
                    {SECTION_ORDER.filter((s) => bySection[s]?.length).map((section) => (
                        <div key={section}>
                            <SectionTitle>{section}</SectionTitle>
                            <div className="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2">
                                {bySection[section].map((d) => <NormativaCard key={d.id} doc={d} isAdmin={isAdmin} onChanged={refresh} />)}
                            </div>
                        </div>
                    ))}
                </div>
            )}

            {isAdmin && (
                <button
                    onClick={() => setShowUpload(true)}
                    className="fixed bottom-6 right-6 z-30 flex h-14 w-14 items-center justify-center rounded-full bg-brand-600 text-2xl text-white shadow-lg hover:bg-brand-700"
                    title="Añadir documento"
                >
                    +
                </button>
            )}

            {showUpload && <UploadModal onClose={() => setShowUpload(false)} onSaved={refresh} />}
        </div>
    );
}

function NormativaCard({ doc, isAdmin, onChanged }) {
    const remove = useMutation({
        mutationFn: async () => (await api.delete(`/superadmin/normativa/${doc.id}`)).data,
        onSuccess: onChanged,
    });
    const toggle = useMutation({
        mutationFn: async () => (await api.patch(`/superadmin/normativa/${doc.id}`, { vigente: !doc.vigente })).data,
        onSuccess: onChanged,
    });

    const link = doc.pdf_url || doc.url_oficial;

    return (
        <div className="flex flex-col rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
            <div className="flex items-start justify-between gap-2">
                <span className={clsx('rounded-full px-2 py-0.5 text-xs font-bold', CATEGORIA_BADGE[doc.categoria])}>
                    {CATEGORIA_LABEL[doc.categoria]}
                </span>
                {doc.vigente ? (
                    <span className="rounded-full bg-brand-50 px-2 py-0.5 text-xs font-semibold text-brand-700">Vigente</span>
                ) : (
                    <span className="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-500">Derogada</span>
                )}
            </div>

            <p className="mt-2 text-sm font-semibold text-slate-800">{doc.titulo}</p>
            {doc.descripcion && <p className="mt-1 text-sm text-slate-500">{doc.descripcion}</p>}

            <div className="mt-2 flex flex-wrap items-center gap-2 text-xs text-slate-400">
                <span className="rounded-full bg-slate-100 px-2 py-0.5 font-medium capitalize text-slate-500">{doc.comunidad_autonoma}</span>
                {(() => {
                    const f = FUENTE_BADGE[doc.fuente] ?? FUENTE_BADGE.manual;
                    return <span className={clsx('rounded-full px-2 py-0.5 font-semibold', f.cls)}>{f.label}</span>;
                })()}
                {doc.idioma && (
                    <span className="rounded-full bg-slate-100 px-2 py-0.5 font-medium capitalize text-slate-500">{doc.idioma}</span>
                )}
                {doc.fecha_publicacion && <span>{new Date(doc.fecha_publicacion).toLocaleDateString('es-ES')}</span>}
                {doc.actualizado && <span>· Actualizado {new Date(doc.actualizado).toLocaleDateString('es-ES')}</span>}
            </div>

            <div className="mt-3 flex items-center gap-2">
                {link ? (
                    <a
                        href={link} target="_blank" rel="noopener noreferrer"
                        className="rounded-lg bg-brand-50 px-3 py-1.5 text-sm font-semibold text-brand-700 hover:bg-brand-100"
                    >
                        Ver documento ↗
                    </a>
                ) : (
                    <span className="text-xs text-slate-400">Sin enlace disponible</span>
                )}
                {isAdmin && (
                    <div className="ml-auto flex gap-2 text-xs">
                        <button onClick={() => toggle.mutate()} className="font-medium text-slate-500 hover:text-slate-700">
                            {doc.vigente ? 'Marcar derogada' : 'Marcar vigente'}
                        </button>
                        <button onClick={() => remove.mutate()} className="font-medium text-rose-500 hover:text-rose-700">Eliminar</button>
                    </div>
                )}
            </div>
        </div>
    );
}

function UploadModal({ onClose, onSaved }) {
    useEscapeKey(onClose);
    const [form, setForm] = useState({
        titulo: '', descripcion: '', categoria: 'ley_organica',
        comunidad_autonoma: 'valenciana', url_oficial: '', fecha_publicacion: '',
    });
    const [pdf, setPdf] = useState(null);

    const save = useMutation({
        mutationFn: async () => {
            const fd = new FormData();
            Object.entries(form).forEach(([k, v]) => { if (v) fd.append(k, v); });
            if (pdf) fd.append('pdf', pdf);
            return (await api.post('/superadmin/normativa', fd, { headers: { 'Content-Type': 'multipart/form-data' } })).data;
        },
        onSuccess: () => { onSaved(); onClose(); },
    });

    const set = (k) => (e) => setForm((f) => ({ ...f, [k]: e.target.value }));

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4" onClick={onClose}>
            <div className="max-h-[90vh] w-full max-w-md overflow-y-auto rounded-2xl bg-white p-5 shadow-xl" onClick={(e) => e.stopPropagation()}>
                <div className="flex items-center justify-between">
                    <h3 className="font-heading text-base font-bold text-slate-800">Añadir documento</h3>
                    <button onClick={onClose} className="text-slate-400 hover:text-slate-600">✕</button>
                </div>

                <div className="mt-4 space-y-3">
                    <Field label="Título"><input value={form.titulo} onChange={set('titulo')} className={inputCls} /></Field>
                    <Field label="Descripción"><textarea value={form.descripcion} onChange={set('descripcion')} rows={2} className={inputCls} /></Field>
                    <Field label="Categoría">
                        <select value={form.categoria} onChange={set('categoria')} className={inputCls}>
                            {Object.entries(CATEGORIA_LABEL).map(([v, l]) => <option key={v} value={v}>{l}</option>)}
                        </select>
                    </Field>
                    <Field label="Comunidad autónoma"><input value={form.comunidad_autonoma} onChange={set('comunidad_autonoma')} className={inputCls} /></Field>
                    <Field label="URL oficial"><input value={form.url_oficial} onChange={set('url_oficial')} placeholder="https://…" className={inputCls} /></Field>
                    <Field label="Fecha de publicación"><input type="date" value={form.fecha_publicacion} onChange={set('fecha_publicacion')} className={inputCls} /></Field>
                    <Field label="PDF (opcional)"><input type="file" accept="application/pdf" onChange={(e) => setPdf(e.target.files?.[0] ?? null)} className="text-sm" /></Field>
                </div>

                <div className="mt-4 flex justify-end gap-2">
                    <button onClick={onClose} className="rounded-lg px-3 py-1.5 text-sm text-slate-600 hover:bg-slate-100">Cancelar</button>
                    <button
                        disabled={save.isPending || !form.titulo}
                        onClick={() => save.mutate()}
                        className="rounded-lg bg-brand-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-brand-700 disabled:opacity-60"
                    >
                        {save.isPending ? 'Guardando…' : 'Guardar'}
                    </button>
                </div>
                {save.isError && <p className="mt-2 text-xs text-rose-600">{save.error?.friendlyMessage ?? 'No se pudo guardar.'}</p>}
            </div>
        </div>
    );
}

function Field({ label, children }) {
    return (
        <label className="block">
            <span className="block text-sm font-medium text-slate-600">{label}</span>
            <div className="mt-1">{children}</div>
        </label>
    );
}

const selectCls = 'rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 focus:border-brand-400 focus:ring-brand-400';
const inputCls = 'w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-400 focus:ring-brand-400';
