import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import clsx from 'clsx';
import api from '../../lib/api';
import { useAuth } from '../../hooks/useAuth';
import { useEscapeKey } from '../../hooks/useEscapeKey';

// Ordered estado pipeline (drives the timeline stepper).
const ESTADO_FLOW = ['rumor', 'anunciada', 'convocada', 'en_proceso', 'resuelta'];

const ESTADO = {
    rumor: { label: 'Rumor', chip: 'bg-slate-100 text-slate-600', dot: 'bg-slate-400' },
    anunciada: { label: 'Anunciada', chip: 'bg-amber-50 text-amber-700', dot: 'bg-amber-500' },
    convocada: { label: 'Convocada', chip: 'bg-blue-50 text-blue-700', dot: 'bg-blue-500' },
    en_proceso: { label: 'En proceso', chip: 'bg-teal-50 text-teal-700', dot: 'bg-teal-500' },
    resuelta: { label: 'Resuelta', chip: 'bg-brand-50 text-brand-700', dot: 'bg-brand-600' },
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

function daysUntil(dateStr) {
    if (!dateStr) return null;
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const d = new Date(dateStr);
    return Math.round((d - today) / 86400000);
}

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

    // The cuerpos the user is preparing — used to surface a matching banner.
    const { data: esp } = useQuery({
        queryKey: ['oposicion', 'especialidades'],
        queryFn: async () => (await api.get('/oposicion/especialidades')).data,
    });
    const userCuerpos = new Set((esp?.data ?? []).map((e) => e.cuerpo));

    const convocatorias = data?.data ?? [];
    const anyFilter = Boolean(filters.estado || filters.comunidad || filters.cuerpo);

    // Prominent banner: an active call (convocada / en_proceso) for the user's cuerpo.
    const matching = convocatorias.find(
        (c) => ['convocada', 'en_proceso'].includes(c.estado) && (userCuerpos.size === 0 || userCuerpos.has(c.cuerpo))
    );

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

            {matching && <MatchBanner convocatoria={matching} onChanged={refresh} />}

            <div className="mb-5 flex flex-wrap gap-2">
                <select value={filters.estado} onChange={(e) => setFilters((f) => ({ ...f, estado: e.target.value }))} className={selectCls}>
                    <option value="">Todos los estados</option>
                    {ESTADO_FLOW.map((v) => <option key={v} value={v}>{ESTADO[v].label}</option>)}
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
                    <p className="mt-2 text-sm font-medium text-slate-600">
                        {anyFilter ? 'No hay convocatorias para estos filtros.' : 'No hay convocatorias activas en tu comunidad.'}
                    </p>
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

// Horizontal stepper showing where the convocatoria is in the estado pipeline.
function Timeline({ estado }) {
    const currentIdx = ESTADO_FLOW.indexOf(estado);
    return (
        <ol className="mt-3 flex items-center gap-1">
            {ESTADO_FLOW.map((e, i) => {
                const done = i <= currentIdx;
                return (
                    <li key={e} className="flex flex-1 flex-col items-center gap-1">
                        <div className="flex w-full items-center">
                            <span className={clsx('h-1 flex-1 rounded-full', i === 0 ? 'bg-transparent' : done ? 'bg-brand-500' : 'bg-slate-200')} />
                            <span className={clsx('h-2.5 w-2.5 shrink-0 rounded-full', done ? ESTADO[e].dot : 'bg-slate-200')} />
                            <span className={clsx('h-1 flex-1 rounded-full', i === ESTADO_FLOW.length - 1 ? 'bg-transparent' : i < currentIdx ? 'bg-brand-500' : 'bg-slate-200')} />
                        </div>
                        <span className={clsx('text-[10px] font-medium', i === currentIdx ? 'text-slate-700' : 'text-slate-400')}>{ESTADO[e].label}</span>
                    </li>
                );
            })}
        </ol>
    );
}

function AlertButton({ convocatoria, onChanged }) {
    const active = Boolean(convocatoria.alert_active);
    const toggle = useMutation({
        mutationFn: async () => (await api.post(`/convocatorias/${convocatoria.id}/alert/toggle`)).data,
        onSuccess: onChanged,
    });

    return (
        <button
            onClick={() => toggle.mutate()}
            disabled={toggle.isPending}
            className={clsx(
                'rounded-lg px-3 py-1.5 text-sm font-semibold transition disabled:opacity-60',
                active ? 'bg-amber-100 text-amber-800 hover:bg-amber-200' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'
            )}
        >
            {active ? '🔔 Alerta activada' : 'Activar alerta'}
        </button>
    );
}

function MatchBanner({ convocatoria, onChanged }) {
    const fecha = convocatoria.fecha_oficial || convocatoria.fecha_estimada;
    const dias = daysUntil(fecha);
    return (
        <div className="mb-5 rounded-2xl bg-gradient-to-br from-brand-600 to-brand-700 p-5 text-white shadow-brand">
            <p className="text-sm font-semibold">🎓 Hay una convocatoria activa para tu especialidad</p>
            <p className="mt-1 font-heading text-lg font-bold">{convocatoria.titulo}</p>
            <div className="mt-2 flex flex-wrap items-center gap-3 text-sm text-white/85">
                <span className="rounded-full bg-white/20 px-2 py-0.5 text-xs font-semibold">{ESTADO[convocatoria.estado]?.label}</span>
                {fecha && <span>📅 {new Date(fecha).toLocaleDateString('es-ES')}</span>}
                {dias != null && dias >= 0 && (
                    <span className="rounded-full bg-amber-400 px-2 py-0.5 text-xs font-bold text-amber-950">
                        {dias === 0 ? '¡Hoy!' : `Faltan ${dias} días`}
                    </span>
                )}
            </div>
            <div className="mt-3 flex flex-wrap items-center gap-2">
                {convocatoria.url_oficial && (
                    <a href={convocatoria.url_oficial} target="_blank" rel="noopener noreferrer" className="inline-block rounded-lg bg-white px-3 py-1.5 text-sm font-semibold text-brand-700 hover:bg-brand-50">
                        Ver convocatoria oficial ↗
                    </a>
                )}
                <AlertButton convocatoria={convocatoria} onChanged={onChanged} />
            </div>
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

            <Timeline estado={c.estado} />

            {fecha && <p className="mt-3 text-xs text-slate-500">{fechaLabel}: {new Date(fecha).toLocaleDateString('es-ES')}</p>}
            {c.notas && <p className="mt-2 text-sm text-slate-600">{c.notas}</p>}

            <div className="mt-3 flex flex-wrap items-center gap-3">
                <AlertButton convocatoria={c} onChanged={onChanged} />
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
                            {ESTADO_FLOW.map((v) => <option key={v} value={v}>{ESTADO[v].label}</option>)}
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
