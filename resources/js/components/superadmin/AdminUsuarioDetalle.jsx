import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { useParams, useNavigate } from 'react-router-dom';
import api from '../../lib/api';
import { SkeletonRows, ErrorState, Badge, statusTone } from './ui';
import { startImpersonation } from '../../lib/impersonation';

const PLANES = ['free', 'interino', 'opositor', 'docente_pro', 'todo_en_uno'];

export default function AdminUsuarioDetalle() {
    const { id } = useParams();
    const navigate = useNavigate();
    const qc = useQueryClient();
    const [nota, setNota] = useState('');
    const [plan, setPlan] = useState('');

    const { data, isLoading, isError, error, refetch } = useQuery({
        queryKey: ['admin', 'usuario', id],
        queryFn: async () => (await api.get(`/superadmin/usuarios/${id}`)).data,
    });

    const invalidate = () => {
        qc.invalidateQueries({ queryKey: ['admin', 'usuario', id] });
        qc.invalidateQueries({ queryKey: ['admin', 'usuarios'] });
    };

    const cambiarPlan = useMutation({
        mutationFn: (nuevoPlan) => api.put(`/superadmin/usuarios/${id}/plan`, { plan: nuevoPlan }),
        onSuccess: () => { setPlan(''); invalidate(); },
    });
    const addNota = useMutation({
        mutationFn: (texto) => api.post(`/superadmin/usuarios/${id}/notas`, { nota: texto }),
        onSuccess: () => { setNota(''); invalidate(); },
    });
    const suspender = useMutation({
        mutationFn: (flag) => api.post(`/superadmin/usuarios/${id}/suspender`, { suspender: flag }),
        onSuccess: invalidate,
    });
    const impersonate = useMutation({
        mutationFn: () => api.post(`/superadmin/usuarios/${id}/impersonate`),
        onSuccess: ({ data }) => startImpersonation(data.token),
    });

    if (isLoading) return <SkeletonRows rows={6} className="h-12" />;
    if (isError) return <ErrorState error={error} onRetry={refetch} />;

    const u = data.usuario;

    return (
        <div className="space-y-6">
            <button onClick={() => navigate('/superadmin/usuarios')} className="text-sm text-slate-400 hover:text-slate-200">← Usuarios</button>

            <div className="rounded-xl border border-slate-700/60 bg-slate-800/50 p-5">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 className="text-lg font-bold text-slate-100">{u.name}</h2>
                        <p className="text-sm text-slate-400">{u.email}</p>
                        <div className="mt-2 flex flex-wrap gap-2">
                            <Badge tone={u.is_paid ? 'green' : 'slate'}>{u.plan_label}</Badge>
                            <Badge tone={statusTone(u.plan_status)}>{u.plan_status}</Badge>
                            <Badge tone="blue">{u.role}</Badge>
                            {u.suspended && <Badge tone="red">Suspendido</Badge>}
                            {u.modo_activo && <Badge>{u.modo_activo}</Badge>}
                        </div>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <button
                            onClick={() => impersonate.mutate()}
                            disabled={impersonate.isPending || u.role === 'superadmin'}
                            className="rounded-lg bg-sky-600 px-3 py-2 text-sm font-semibold text-white hover:bg-sky-700 disabled:opacity-40"
                        >
                            Suplantar
                        </button>
                        <button
                            onClick={() => suspender.mutate(!u.suspended)}
                            disabled={suspender.isPending || u.role === 'superadmin'}
                            className={u.suspended
                                ? 'rounded-lg bg-emerald-600 px-3 py-2 text-sm font-semibold text-white hover:bg-emerald-700 disabled:opacity-40'
                                : 'rounded-lg bg-rose-600 px-3 py-2 text-sm font-semibold text-white hover:bg-rose-700 disabled:opacity-40'}
                        >
                            {u.suspended ? 'Reactivar' : 'Suspender'}
                        </button>
                    </div>
                </div>

                <dl className="mt-4 grid grid-cols-2 gap-3 text-sm sm:grid-cols-3">
                    <Field label="Nombre GVA" value={u.nombre_gva} />
                    <Field label="CCAA" value={u.ccaa} />
                    <Field label="Colectivo" value={u.colectivo} />
                    <Field label="Onboarding" value={u.onboarding_completed ? 'Completado' : 'Pendiente'} />
                    <Field label="Última actividad" value={u.last_active_at ? new Date(u.last_active_at).toLocaleString('es-ES') : '—'} />
                    <Field label="Registro" value={u.created_at ? new Date(u.created_at).toLocaleDateString('es-ES') : '—'} />
                </dl>
            </div>

            {/* Change plan */}
            <div className="rounded-xl border border-slate-700/60 bg-slate-800/50 p-5">
                <h3 className="text-sm font-semibold text-slate-300">Cambiar plan</h3>
                <div className="mt-2 flex flex-wrap items-center gap-2">
                    <select value={plan} onChange={(e) => setPlan(e.target.value)} className="rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-slate-100">
                        <option value="">Selecciona un plan…</option>
                        {PLANES.map((p) => <option key={p} value={p}>{p}</option>)}
                    </select>
                    <button
                        onClick={() => plan && cambiarPlan.mutate(plan)}
                        disabled={!plan || cambiarPlan.isPending}
                        className="rounded-lg bg-slate-700 px-3 py-2 text-sm font-semibold text-slate-100 hover:bg-slate-600 disabled:opacity-40"
                    >
                        Aplicar
                    </button>
                </div>
            </div>

            {/* Subscriptions */}
            <div className="rounded-xl border border-slate-700/60 bg-slate-800/50 p-5">
                <h3 className="text-sm font-semibold text-slate-300">Suscripciones</h3>
                <ul className="mt-2 space-y-2 text-sm">
                    {data.suscripciones.length === 0 && <li className="text-slate-500">Sin suscripciones registradas.</li>}
                    {data.suscripciones.map((s) => (
                        <li key={s.id} className="flex items-center justify-between rounded-lg bg-slate-900/50 px-3 py-2">
                            <span className="text-slate-300">{s.plan_codigo} · <Badge tone={statusTone(s.status)}>{s.status}</Badge></span>
                            <span className="text-xs text-slate-500">{s.stripe_subscription_id ?? '—'}</span>
                        </li>
                    ))}
                </ul>
            </div>

            {/* Notes */}
            <div className="rounded-xl border border-slate-700/60 bg-slate-800/50 p-5">
                <h3 className="text-sm font-semibold text-slate-300">Notas internas</h3>
                <div className="mt-2 flex gap-2">
                    <input
                        value={nota}
                        onChange={(e) => setNota(e.target.value)}
                        placeholder="Añadir nota…"
                        className="min-w-0 flex-1 rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-slate-100"
                    />
                    <button
                        onClick={() => nota.trim() && addNota.mutate(nota.trim())}
                        disabled={!nota.trim() || addNota.isPending}
                        className="rounded-lg bg-slate-700 px-3 py-2 text-sm font-semibold text-slate-100 hover:bg-slate-600 disabled:opacity-40"
                    >
                        Guardar
                    </button>
                </div>
                <ul className="mt-3 space-y-2 text-sm">
                    {data.notas.map((n) => (
                        <li key={n.id} className="rounded-lg bg-slate-900/50 px-3 py-2">
                            <div className="flex items-center justify-between">
                                <Badge>{n.tipo}</Badge>
                                <span className="text-xs text-slate-500">{n.admin ?? 'sistema'} · {new Date(n.created_at).toLocaleString('es-ES')}</span>
                            </div>
                            <p className="mt-1 text-slate-300">{n.nota}</p>
                        </li>
                    ))}
                    {data.notas.length === 0 && <li className="text-slate-500">Sin notas.</li>}
                </ul>
            </div>
        </div>
    );
}

function Field({ label, value }) {
    return (
        <div>
            <dt className="text-xs uppercase tracking-wide text-slate-500">{label}</dt>
            <dd className="text-slate-300">{value || '—'}</dd>
        </div>
    );
}
