import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import clsx from 'clsx';
import { Navigate } from 'react-router-dom';
import api from '../../lib/api';
import { useAuth } from '../../hooks/useAuth';

const ESTADO_STYLES = {
    ok: 'bg-emerald-100 text-emerald-700',
    sin_proceso: 'bg-amber-100 text-amber-700',
    error: 'bg-rose-100 text-rose-700',
};
const ESTADO_LABEL = {
    ok: 'Importado',
    sin_proceso: 'Requiere proceso',
    error: 'Error',
};

function formatDate(iso) {
    if (!iso) return '—';
    try {
        return new Date(iso).toLocaleString('es-ES', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' });
    } catch {
        return '—';
    }
}

function ReimportRow({ noticia, procesos }) {
    const queryClient = useQueryClient();
    const [procesoId, setProcesoId] = useState('');
    const [kind, setKind] = useState('participantes');

    const reimport = useMutation({
        mutationFn: async (payload) => (await api.post(`/admin/gva-importaciones/${noticia.id}/reimportar`, payload)).data,
        onSuccess: () => queryClient.invalidateQueries({ queryKey: ['admin-importaciones'] }),
    });

    const estado = noticia.import_estado;

    return (
        <div className="rounded-xl border border-slate-200 bg-white p-3 shadow-sm">
            <div className="flex items-start justify-between gap-3">
                <div className="min-w-0">
                    <a href={noticia.url} target="_blank" rel="noreferrer" className="block truncate text-sm font-semibold text-brand-700 hover:underline" title={noticia.titulo}>
                        {noticia.titulo}
                    </a>
                    <p className="mt-0.5 text-[11px] text-slate-400">
                        {noticia.proceso ? noticia.proceso.nombre : 'Sin proceso asociado'} · importado {formatDate(noticia.importado_en)}
                    </p>
                    {noticia.import_resumen && <p className="mt-1 text-xs text-slate-600">{noticia.import_resumen}</p>}
                </div>
                <span className={clsx('shrink-0 rounded-full px-2 py-0.5 text-[11px] font-bold', ESTADO_STYLES[estado] ?? 'bg-slate-100 text-slate-500')}>
                    {ESTADO_LABEL[estado] ?? 'Pendiente'}
                </span>
            </div>

            <div className="mt-3 flex flex-wrap items-center gap-2 border-t border-slate-100 pt-3">
                {estado !== 'ok' && (
                    <>
                        <select value={kind} onChange={(e) => setKind(e.target.value)} className="rounded-lg border border-slate-200 px-2 py-1.5 text-xs">
                            <option value="participantes">Participantes</option>
                            <option value="vacantes">Vacantes</option>
                        </select>
                        <select value={procesoId} onChange={(e) => setProcesoId(e.target.value)} className="min-w-[12rem] flex-1 rounded-lg border border-slate-200 px-2 py-1.5 text-xs">
                            <option value="">Elegir proceso…</option>
                            {procesos.map((p) => (
                                <option key={p.id} value={p.id}>{p.nombre}</option>
                            ))}
                        </select>
                        <button
                            type="button"
                            disabled={!procesoId || reimport.isPending}
                            onClick={() => reimport.mutate({ proceso_id: Number(procesoId), kind })}
                            className="rounded-lg bg-brand-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-brand-700 disabled:opacity-50"
                        >
                            {reimport.isPending ? 'Importando…' : 'Importar en este proceso'}
                        </button>
                    </>
                )}
                <button
                    type="button"
                    disabled={reimport.isPending}
                    onClick={() => reimport.mutate({})}
                    className="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-600 hover:bg-slate-100 disabled:opacity-50"
                >
                    Reintentar automático
                </button>
                {reimport.isError && <span className="text-xs text-rose-600">{reimport.error?.friendlyMessage ?? 'Error al importar.'}</span>}
                {reimport.isSuccess && <span className="text-xs text-emerald-600">✓ {reimport.data?.import_estado}</span>}
            </div>
        </div>
    );
}

export default function AdminImportaciones() {
    const { user } = useAuth();

    const { data, isLoading, isError } = useQuery({
        queryKey: ['admin-importaciones'],
        queryFn: async () => (await api.get('/admin/gva-importaciones')).data,
    });

    const { data: procesosData } = useQuery({
        queryKey: ['procesos-admin'],
        queryFn: async () => (await api.get('/procesos')).data,
    });

    if (user && !user.is_admin && user.id !== 1) {
        return <Navigate to="/dashboard" replace />;
    }

    const items = data?.data ?? [];
    const procesos = procesosData?.data ?? [];

    return (
        <div className="mx-auto max-w-3xl space-y-4">
            <div>
                <h1 className="text-lg font-bold text-slate-800">Importaciones automáticas (GVA)</h1>
                <p className="mt-1 text-sm text-slate-500">
                    Listados detectados por el monitor diario. Los importados automáticamente aparecen como «Importado»;
                    los que no se pudieron asociar a un proceso puedes importarlos a mano.
                </p>
            </div>

            {isLoading ? (
                <p className="text-sm text-slate-400">Cargando…</p>
            ) : isError ? (
                <p className="text-sm text-rose-600">No se pudo cargar el listado.</p>
            ) : items.length === 0 ? (
                <div className="rounded-2xl bg-white p-8 text-center text-sm text-slate-400 shadow-sm ring-1 ring-slate-200">
                    Aún no se han detectado listados en PDF.
                </div>
            ) : (
                <div className="space-y-2">
                    {items.map((n) => (
                        <ReimportRow key={n.id} noticia={n} procesos={procesos} />
                    ))}
                </div>
            )}
        </div>
    );
}
