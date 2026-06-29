import { useState } from 'react';
import { useQuery, useMutation } from '@tanstack/react-query';
import { Link } from 'react-router-dom';
import clsx from 'clsx';
import api from '../../lib/api';
import { useAuth } from '../../hooks/useAuth';
import { useFeatures } from '../../hooks/useFeatures';
import UpgradePrompt from '../shared/UpgradePrompt';

const TABS = [
    { key: '', label: 'Todos' },
    { key: 'coche', label: 'Compartir coche' },
    { key: 'alojamiento', label: 'Alojamiento' },
    { key: 'centro', label: 'Sobre un centro' },
    { key: 'general', label: 'General' },
];

const BADGES = {
    coche: 'bg-blue-100 text-blue-700',
    alojamiento: 'bg-purple-100 text-purple-700',
    centro: 'bg-amber-100 text-amber-700',
    general: 'bg-slate-100 text-slate-600',
};

function ContactModal({ anuncio, onClose }) {
    const [mensaje, setMensaje] = useState('');
    const [sent, setSent] = useState(false);
    const mut = useMutation({
        mutationFn: async () => (await api.post(`/tablon/${anuncio.id}/contactar`, { mensaje })).data,
        onSuccess: () => setSent(true),
    });

    return (
        <div className="fixed inset-0 z-40 flex items-center justify-center bg-slate-900/40 p-4" onClick={onClose}>
            <div className="w-full max-w-md rounded-2xl bg-white p-5 shadow-xl" onClick={(e) => e.stopPropagation()}>
                <h3 className="text-sm font-bold text-slate-800">Contactar: {anuncio.titulo}</h3>
                {sent ? (
                    <div className="mt-3">
                        <p className="text-sm text-green-600">Mensaje enviado. Permaneces anónimo hasta que decidas compartir tu información.</p>
                        <button onClick={onClose} className="mt-3 rounded-lg bg-slate-100 px-3 py-1.5 text-sm">Cerrar</button>
                    </div>
                ) : (
                    <>
                        <textarea
                            value={mensaje}
                            onChange={(e) => setMensaje(e.target.value)}
                            rows={4}
                            placeholder="Escribe tu mensaje…"
                            className="mt-2 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-400 focus:ring-brand-400"
                        />
                        <div className="mt-3 flex justify-end gap-2">
                            <button onClick={onClose} className="rounded-lg px-3 py-1.5 text-sm text-slate-600 hover:bg-slate-100">Cancelar</button>
                            <button
                                disabled={mut.isPending || mensaje.trim() === ''}
                                onClick={() => mut.mutate()}
                                className="rounded-lg bg-brand-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-brand-700 disabled:opacity-60"
                            >
                                {mut.isPending ? 'Enviando…' : 'Enviar'}
                            </button>
                        </div>
                    </>
                )}
            </div>
        </div>
    );
}

export default function TablonList() {
    const { user } = useAuth();
    const { can } = useFeatures();
    const canPublish = can('tablon_completo');
    const [categoria, setCategoria] = useState('');
    const [contactAnuncio, setContactAnuncio] = useState(null);

    const { data, isLoading, isError, error } = useQuery({
        queryKey: ['tablon', categoria],
        queryFn: async () => {
            const params = {};
            if (categoria) params.categoria = categoria;
            return (await api.get('/tablon', { params })).data;
        },
    });

    const anuncios = data?.data ?? [];

    return (
        <div>
            <div className="mb-4 flex flex-wrap items-center justify-between gap-2">
                <h1 className="font-heading text-xl font-bold text-slate-800">Tablón de anuncios</h1>
                <div className="flex gap-2">
                    <Link to="/dashboard/tablon/mis-anuncios" className="rounded-lg px-3 py-1.5 text-sm font-medium text-slate-600 hover:bg-slate-100">Mis anuncios</Link>
                    {canPublish ? (
                        <Link to="/dashboard/tablon/nuevo" className="rounded-lg bg-brand-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-brand-700">+ Publicar</Link>
                    ) : (
                        <UpgradePrompt variant="inline" message="Publicar en el tablón requiere un plan de pago." />
                    )}
                </div>
            </div>

            <div className="mb-4 flex flex-wrap gap-1">
                {TABS.map((t) => (
                    <button
                        key={t.key}
                        onClick={() => setCategoria(t.key)}
                        className={clsx('rounded-full px-3 py-1.5 text-sm font-medium transition', categoria === t.key ? 'bg-brand-600 text-white' : 'bg-white text-slate-600 ring-1 ring-slate-200 hover:bg-slate-50')}
                    >
                        {t.label}
                    </button>
                ))}
            </div>

            {isError ? (
                <p className="rounded-lg bg-rose-50 px-3 py-2 text-sm text-rose-600">
                    {error?.friendlyMessage ?? 'No se pudo cargar el tablón.'}
                </p>
            ) : isLoading ? (
                <p className="text-sm text-slate-400">Cargando…</p>
            ) : anuncios.length === 0 ? (
                <p className="rounded-2xl bg-white p-8 text-center text-sm text-slate-400 shadow-sm ring-1 ring-slate-200">
                    No hay anuncios en esta categoría todavía. ¡Publica el primero!
                </p>
            ) : (
                <ul className="space-y-3">
                    {anuncios.map((a) => {
                        const own = user && a.user_id === user.id;
                        return (
                            <li key={a.id} className="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
                                <div className="flex items-start justify-between gap-2">
                                    <span className={clsx('rounded-full px-2 py-0.5 text-xs font-bold', BADGES[a.categoria])}>{a.categoria}</span>
                                    {a.created_at && <span className="text-xs text-slate-400">{a.created_at.slice(0, 10)}</span>}
                                </div>
                                <p className="mt-2 text-sm font-semibold text-slate-800">{a.titulo}</p>
                                <p className="mt-1 text-sm text-slate-600">{(a.contenido ?? '').slice(0, 150)}{(a.contenido ?? '').length > 150 ? '…' : ''}</p>
                                {a.categoria === 'coche' && (a.localidad_origen || a.localidad_destino) && (
                                    <p className="mt-2 text-xs font-medium text-blue-700">{a.localidad_origen ?? '?'} → {a.localidad_destino ?? '?'}</p>
                                )}
                                {user && !own && (
                                    <button onClick={() => setContactAnuncio(a)} className="mt-3 rounded-lg bg-brand-50 px-3 py-1.5 text-sm font-semibold text-brand-700 hover:bg-brand-100">
                                        Contactar
                                    </button>
                                )}
                            </li>
                        );
                    })}
                </ul>
            )}

            {contactAnuncio && <ContactModal anuncio={contactAnuncio} onClose={() => setContactAnuncio(null)} />}
        </div>
    );
}
