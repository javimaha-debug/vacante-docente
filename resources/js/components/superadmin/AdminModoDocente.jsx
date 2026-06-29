import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api from '../../lib/api';

const TIPO_LABELS = {
    rubrica: 'Rúbrica',
    situacion_aprendizaje: 'Sit. Aprendizaje',
    actividad: 'Actividad',
    examen: 'Examen',
};

function StatCard({ label, value, sub }) {
    return (
        <div className="rounded-xl bg-slate-800 p-4">
            <p className="text-xs font-semibold uppercase tracking-wide text-slate-400">{label}</p>
            <p className="mt-1 text-3xl font-bold text-white">{value ?? '—'}</p>
            {sub && <p className="mt-0.5 text-xs text-slate-400">{sub}</p>}
        </div>
    );
}

function StatsTab({ stats }) {
    if (!stats) return <p className="text-sm text-slate-400">Cargando estadísticas…</p>;
    return (
        <div className="space-y-6">
            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <StatCard label="Usuarios Modo Docente" value={stats.usuarios_docente} />
                <StatCard label="Programaciones" value={stats.programaciones?.total} sub={`${stats.programaciones?.activas ?? 0} activas · ${stats.programaciones?.archivadas ?? 0} archivadas`} />
                <StatCard label="Recursos banco" value={stats.banco?.total_compartidos} sub={`${stats.banco?.pendientes_moderacion ?? 0} pendientes moderación`} />
                <StatCard label="Contenidos IA" value={(stats.recursos?.rubricas ?? 0) + (stats.recursos?.situaciones ?? 0) + (stats.recursos?.examenes ?? 0)} sub={`${stats.recursos?.rubricas ?? 0} rúb · ${stats.recursos?.situaciones ?? 0} SA · ${stats.recursos?.examenes ?? 0} exam`} />
            </div>
            <div className="grid gap-3 sm:grid-cols-3">
                <div className="rounded-xl bg-slate-800 p-4">
                    <p className="mb-2 text-xs font-semibold uppercase text-slate-400">Programaciones</p>
                    <div className="space-y-1">
                        <div className="flex justify-between text-sm"><span className="text-slate-300">Borrador</span><span className="font-semibold text-white">{(stats.programaciones?.total ?? 0) - (stats.programaciones?.activas ?? 0) - (stats.programaciones?.archivadas ?? 0)}</span></div>
                        <div className="flex justify-between text-sm"><span className="text-emerald-400">Activas</span><span className="font-semibold text-white">{stats.programaciones?.activas ?? 0}</span></div>
                        <div className="flex justify-between text-sm"><span className="text-slate-400">Archivadas</span><span className="font-semibold text-white">{stats.programaciones?.archivadas ?? 0}</span></div>
                    </div>
                </div>
                <div className="rounded-xl bg-slate-800 p-4">
                    <p className="mb-2 text-xs font-semibold uppercase text-slate-400">Recursos IA</p>
                    <div className="space-y-1">
                        <div className="flex justify-between text-sm"><span className="text-slate-300">Rúbricas</span><span className="font-semibold text-white">{stats.recursos?.rubricas ?? 0}</span></div>
                        <div className="flex justify-between text-sm"><span className="text-slate-300">Situaciones</span><span className="font-semibold text-white">{stats.recursos?.situaciones ?? 0}</span></div>
                        <div className="flex justify-between text-sm"><span className="text-slate-300">Exámenes</span><span className="font-semibold text-white">{stats.recursos?.examenes ?? 0}</span></div>
                    </div>
                </div>
                <div className="rounded-xl bg-slate-800 p-4">
                    <p className="mb-2 text-xs font-semibold uppercase text-slate-400">Banco compartido</p>
                    <div className="space-y-1">
                        <div className="flex justify-between text-sm"><span className="text-amber-400">Pendientes</span><span className="font-semibold text-white">{stats.banco?.pendientes_moderacion ?? 0}</span></div>
                        <div className="flex justify-between text-sm"><span className="text-emerald-400">Moderados</span><span className="font-semibold text-white">{stats.banco?.moderados ?? 0}</span></div>
                        <div className="flex justify-between text-sm"><span className="text-slate-300">Total</span><span className="font-semibold text-white">{stats.banco?.total_compartidos ?? 0}</span></div>
                    </div>
                </div>
            </div>
        </div>
    );
}

function ModerationTab() {
    const qc = useQueryClient();
    const { data: pendientes = [], isLoading } = useQuery({
        queryKey: ['admin-docente-banco-pendiente'],
        queryFn: async () => (await api.get('/superadmin/docente/banco-pendiente')).data.data ?? [],
    });

    const moderarMutation = useMutation({
        mutationFn: ({ id, aprobar }) => api.patch(`/superadmin/docente/banco/${id}/moderar`, { aprobar }),
        onSuccess: () => qc.invalidateQueries({ queryKey: ['admin-docente-banco-pendiente'] }),
    });

    if (isLoading) return <p className="text-sm text-slate-400">Cargando…</p>;

    if (pendientes.length === 0) {
        return (
            <div className="rounded-xl bg-slate-800 p-8 text-center">
                <p className="text-slate-400">No hay recursos pendientes de moderación.</p>
            </div>
        );
    }

    return (
        <div className="space-y-3">
            <p className="text-sm text-slate-400">{pendientes.length} recurso{pendientes.length !== 1 ? 's' : ''} pendiente{pendientes.length !== 1 ? 's' : ''}</p>
            {pendientes.map((r) => (
                <div key={r.id} className="flex items-center gap-4 rounded-xl bg-slate-800 p-4">
                    <div className="flex-1 min-w-0">
                        <div className="flex items-center gap-2 mb-0.5">
                            <span className="rounded-full bg-slate-700 px-2 py-0.5 text-[10px] font-semibold text-slate-300">
                                {TIPO_LABELS[r.tipo] ?? r.tipo}
                            </span>
                            <span className="text-xs text-slate-400">ID recurso: {r.recurso_id}</span>
                        </div>
                        <p className="text-sm text-slate-200">Por: <span className="font-medium">{r.autor ?? 'Anónimo'}</span></p>
                        <p className="text-xs text-slate-400">Enviado: {r.created_at ? new Date(r.created_at).toLocaleDateString('es-ES') : '—'}</p>
                    </div>
                    <div className="flex shrink-0 gap-2">
                        <button
                            onClick={() => moderarMutation.mutate({ id: r.id, aprobar: false })}
                            disabled={moderarMutation.isPending}
                            className="rounded-lg bg-rose-900/50 px-3 py-1.5 text-xs font-semibold text-rose-300 hover:bg-rose-900 transition"
                        >
                            Rechazar
                        </button>
                        <button
                            onClick={() => moderarMutation.mutate({ id: r.id, aprobar: true })}
                            disabled={moderarMutation.isPending}
                            className="rounded-lg bg-emerald-900/50 px-3 py-1.5 text-xs font-semibold text-emerald-300 hover:bg-emerald-900 transition"
                        >
                            Aprobar
                        </button>
                    </div>
                </div>
            ))}
        </div>
    );
}

export default function AdminModoDocente() {
    const [tab, setTab] = useState('stats');

    const { data: stats } = useQuery({
        queryKey: ['admin-docente-stats'],
        queryFn: async () => (await api.get('/superadmin/docente/stats')).data,
        refetchInterval: 60000,
    });

    const tabs = [
        { key: 'stats', label: 'Estadísticas' },
        { key: 'moderacion', label: 'Moderación banco', badge: stats?.banco?.pendientes_moderacion },
    ];

    return (
        <div className="space-y-6">
            <h1 className="text-xl font-bold text-white">Modo Docente</h1>

            <div className="flex gap-1 border-b border-slate-700">
                {tabs.map((t) => (
                    <button
                        key={t.key}
                        onClick={() => setTab(t.key)}
                        className={`flex items-center gap-1.5 px-4 py-2 text-sm font-medium transition ${tab === t.key ? 'border-b-2 border-sky-400 text-white' : 'text-slate-400 hover:text-slate-200'}`}
                    >
                        {t.label}
                        {t.badge > 0 && (
                            <span className="rounded-full bg-amber-500 px-1.5 py-0.5 text-[10px] font-bold text-white">{t.badge}</span>
                        )}
                    </button>
                ))}
            </div>

            {tab === 'stats' && <StatsTab stats={stats} />}
            {tab === 'moderacion' && <ModerationTab />}
        </div>
    );
}
