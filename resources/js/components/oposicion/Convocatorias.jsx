import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import clsx from 'clsx';
import api from '../../lib/api';
import { useAuth } from '../../hooks/useAuth';
import { useEscapeKey } from '../../hooks/useEscapeKey';

const ESTADO = {
    rumor: { label: 'Rumor', chip: 'bg-slate-100 text-slate-600' },
    anunciada: { label: 'Anunciada', chip: 'bg-amber-50 text-amber-700' },
    convocada: { label: 'Convocada', chip: 'bg-blue-50 text-blue-700' },
    en_proceso: { label: 'En proceso', chip: 'bg-teal-50 text-teal-700' },
    resuelta: { label: 'Resuelta', chip: 'bg-brand-50 text-brand-700' },
};

const CUERPO_BADGE = 'bg-slate-100 text-slate-600';

const COMUNIDADES = [
    { value: '', label: 'Todas las comunidades' },
    { value: 'nacional', label: 'Nacional' },
    { value: 'valenciana', label: 'Comunitat Valenciana' },
];

const CUERPOS = [
    { value: '', label: 'Todos los cuerpos' },
    { value: 'maestros', label: 'Maestros' },
    { value: 'secundaria', label: 'Secundaria' },
    { value: 'fp', label: 'FP' },
    { value: 'otros', label: 'Otros' },
];

export default function Convocatorias() {
    const { user } = useAuth();
    const isAdmin = Boolean(user?.is_admin) || Boolean(user?.is_superadmin);
    const [filters, setFilters] = useState({ estado: '', comunidad: '', cuerpo: '' });
    const [editing, setEditing] = useState(null); // convocatoria object or 'new'
    const qc = useQueryClient();

    const { data, isLoading, isError, error } = useQuery({
        queryKey: ['convocatorias', filters],
        queryFn: async () => (await api.get('/convocatorias', {
            params: {
                estado: filters.estado || undefined,
                comunidad: filters.comunidad || undefined,
                cuerpo: filters.cuerpo || undefined,
            },
        })).data,
    });

    const convocatorias = data?.data ?? [];

    // Hero: an active convocatoria (convocada / en_proceso) — surfaced first.
    const activa = convocatorias.find((c) => ['convocada', 'en_proceso'].includes(c.estado));

    const refresh = () => qc.invalidateQueries({ queryKey: ['convocatorias'] });

    return (
        <div className="mx-auto max-w-4xl">
            <div className="mb-4 flex flex-wrap items-center justify-between gap-2">
                <div>
                    <h1 className="font-heading text-xl font-bold text-slate-800">Convocatorias</h1>
                    <p className="text-sm text-slate-500">Sigue el estado de las oposiciones que te interesan.</p>
                </div>
                {isAdmin && (
                    <button onClick={() => setEditing('new')} className="rounded-lg bg-brand-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-brand-700">
                        + Añadir convocatoria
                    </button>
                )}
            </div>

            {activa && <Hero convocatoria={activa} />}

            <div className="mb-5 flex flex-wrap gap-2">
                <select value={filters.estado} onChange={(e) => setFilters((f) => ({ ...f, estado: e.target.value }))} className={selectCls}>
                    <option value="">Todos los estados</option>
                    {Object.entries(ESTADO).map(([v, m]) => <option key={v} value={v}>{m.label}</option>)}
                </select>
                <select value={filters.comunidad} onChange={(e) => setFilters((f) => ({ ...f, comunidad: e.target.value }))} className={selectCls}>
                    {COMUNIDADES.map((c) => <option key={c.value} value={c.value}>{c.label}</option>)}
                </select>
                <select value={filters.cuerpo} onChange={(e) => setFilters((f) => ({ ...f, cuerpo: e.target.value }))} className={selectCls}>
                    {CUERPOS.map((c) => <option key={c.value} value={c.value}>{c.label}</option>)}
                </select>
            </div>

            {isError ? (
                <p className="rounded-lg bg-rose-50 px-3 py-2 text-sm text-rose-600">{error?.friendlyMessage ?? 'No se pudieron cargar las convocatorias.'}</p>
            ) : isLoading ? (
                <p className="text-sm text-slate-400">Cargando…</p>
            ) : convocatorias.length === 0 ? (
                <div className="rounded-2xl bg-white p-8 text-center shadow-sm ring-1 ring-slate-200">
                    <div className="text-3xl">📭</div>
                    <p className="mt-2 text-sm font-medium text-slate-600">No hay convocatorias para estos filtros.</p>
                    <p className="mt-1 text-sm text-slate-400">Te avisaremos cuando haya novedades.</p>
                </div>
            ) : (
                <ul className="space-y-3">
                    {convocatorias.map((c) => (
                        <ConvocatoriaCard key={c.id} convocatoria={c} isAdmin={isAdmin} onEdit={() => setEditing(c)} onChanged={refresh} />
                    ))}
                </ul>
            )}

            {editing && (
                <EditModal
                    convocatoria={editing === 'new' ? null : editing}
                    onClose={() => setEditing(null)}
                    onSaved={() => { refresh(); setEditing(null); }}
                />
            )}
        </div>
    );
}

function Hero({ convocatoria }) {
    const fecha = convocatoria.fecha_oficial || convocatoria.fecha_estimada;
    return (
        <div className="mb-5 rounded-2xl bg-gradient-to-br from-brand-600 to-brand-700 p-5 text-white shadow-brand">
            <p className="text-sm font-semibold">🎓 Hay una convocatoria activa para tu especialidad</p>
            <p className="mt-1 font-heading text-lg font-bold">{convocatoria.titulo}</p>
            <div className="mt-2 flex flex-wrap items-center gap-3 text-sm text-white/80">
                {fecha && <span>📅 {new Date(fecha).toLocaleDateString('es-ES')}</span>}
                <span className="rounded-full bg-white/20 px-2 py-0.5 text-xs font-semibold">{ESTADO[convocatoria.estado]?.label}</span>
            </div>
            {convocatoria.url_oficial && (
                <a href={convocatoria.url_oficial} target="_blank" rel="noopener noreferrer" className="mt-3 inline-block rounded-lg bg-white px-3 py-1.5 text-sm font-semibold text-brand-700 hover:bg-brand-50">
                    Ver convocatoria oficial ↗
                </a>
            )}
        </div>
    );
}

function ConvocatoriaCard({ convocatoria: c, isAdmin, onEdit, onChanged }) {
    const estado = ESTADO[c.estado] ?? ESTADO.rumor;
    const fecha = c.fecha_oficial || c.fecha_estimada;
    const fechaLabel = c.fecha_oficial ? 'Fecha oficial' : c.fecha_estimada ? 'Fecha estimada' : null;

    const remove = useMutation({
        mutationFn: async () => (await api.delete(`/superadmin/convocatorias/${c.id}`)).data,
        onSuccess: onChanged,
    });

    return (
        <li className="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
            <div className="flex items-start justify-between gap-2">
                <div className="flex flex-wrap items-center gap-2">
                    <span className="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium capitalize text-slate-500">{c.comunidad_autonoma}</span>
                    {c.cuerpo && <span className={clsx('rounded-full px-2 py-0.5 text-xs font-medium capitalize', CUERPO_BADGE)}>{c.cuerpo}</span>}
                </div>
                <span className={clsx('rounded-full px-2 py-0.5 text-xs font-bold', estado.chip)}>{estado.label}</span>
            </div>

            <p className="mt-2 text-sm font-semibold text-slate-800">{c.titulo}</p>
            {fecha && <p className="mt-1 text-xs text-slate-500">{fechaLabel}: {new Date(fecha).toLocaleDateString('es-ES')}</p>}
            {c.notas && <p className="mt-2 text-sm text-slate-600">{c.notas}</p>}

            <div className="mt-3 flex items-center gap-3">
                {c.url_oficial && (
                    <a href={c.url_oficial} target="_blank" rel="noopener noreferrer" className="text-sm font-semibold text-brand-700 hover:text-brand-800">
                        Ver convocatoria oficial ↗
                    </a>
                )}
                {c.boe_url && (
                    <a href={c.boe_url} target="_blank" rel="noopener noreferrer" className="text-sm font-medium text-slate-500 hover:text-slate-700">
                        BOE ↗
                    </a>
                )}
                {isAdmin && (
                    <div className="ml-auto flex gap-2 text-xs">
                        <button onClick={onEdit} className="font-medium text-slate-500 hover:text-slate-700">Editar</button>
                        <button onClick={() => remove.mutate()} className="font-medium text-rose-500 hover:text-rose-700">Eliminar</button>
                    </div>
                )}
            </div>
        </li>
    );
}

function EditModal({ convocatoria, onClose, onSaved }) {
    useEscapeKey(onClose);
    const isNew = !convocatoria;
    const [form, setForm] = useState({
        titulo: convocatoria?.titulo ?? '',
        comunidad_autonoma: convocatoria?.comunidad_autonoma ?? 'valenciana',
        cuerpo: convocatoria?.cuerpo ?? '',
        estado: convocatoria?.estado ?? 'rumor',
        fecha_estimada: convocatoria?.fecha_estimada ?? '',
        fecha_oficial: convocatoria?.fecha_oficial ?? '',
        url_oficial: convocatoria?.url_oficial ?? '',
        boe_url: convocatoria?.boe_url ?? '',
        notas: convocatoria?.notas ?? '',
    });

    const save = useMutation({
        mutationFn: async () => {
            const payload = Object.fromEntries(Object.entries(form).map(([k, v]) => [k, v === '' ? null : v]));
            if (isNew) return (await api.post('/superadmin/convocatorias', payload)).data;
            return (await api.patch(`/superadmin/convocatorias/${convocatoria.id}`, payload)).data;
        },
        onSuccess: onSaved,
    });

    const set = (k) => (e) => setForm((f) => ({ ...f, [k]: e.target.value }));

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4" onClick={onClose}>
            <div className="max-h-[90vh] w-full max-w-md overflow-y-auto rounded-2xl bg-white p-5 shadow-xl" onClick={(e) => e.stopPropagation()}>
                <div className="flex items-center justify-between">
                    <h3 className="font-heading text-base font-bold text-slate-800">{isNew ? 'Nueva convocatoria' : 'Editar convocatoria'}</h3>
                    <button onClick={onClose} className="text-slate-400 hover:text-slate-600">✕</button>
                </div>
                <div className="mt-4 space-y-3">
                    <Field label="Título"><input value={form.titulo} onChange={set('titulo')} className={inputCls} /></Field>
                    <Field label="Comunidad autónoma"><input value={form.comunidad_autonoma} onChange={set('comunidad_autonoma')} className={inputCls} /></Field>
                    <Field label="Cuerpo">
                        <select value={form.cuerpo} onChange={set('cuerpo')} className={inputCls}>
                            {CUERPOS.map((c) => <option key={c.value} value={c.value}>{c.label}</option>)}
                        </select>
                    </Field>
                    <Field label="Estado">
                        <select value={form.estado} onChange={set('estado')} className={inputCls}>
                            {Object.entries(ESTADO).map(([v, m]) => <option key={v} value={v}>{m.label}</option>)}
                        </select>
                    </Field>
                    <div className="grid grid-cols-2 gap-3">
                        <Field label="Fecha estimada"><input type="date" value={form.fecha_estimada ?? ''} onChange={set('fecha_estimada')} className={inputCls} /></Field>
                        <Field label="Fecha oficial"><input type="date" value={form.fecha_oficial ?? ''} onChange={set('fecha_oficial')} className={inputCls} /></Field>
                    </div>
                    <Field label="URL oficial"><input value={form.url_oficial} onChange={set('url_oficial')} placeholder="https://…" className={inputCls} /></Field>
                    <Field label="BOE / DOGV URL"><input value={form.boe_url} onChange={set('boe_url')} placeholder="https://…" className={inputCls} /></Field>
                    <Field label="Notas"><textarea value={form.notas} onChange={set('notas')} rows={2} className={inputCls} /></Field>
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
