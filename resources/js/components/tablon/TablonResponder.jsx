import { useState } from 'react';
import { useMutation } from '@tanstack/react-query';
import { useParams, Link } from 'react-router-dom';
import api from '../../lib/api';
import { useAuth } from '../../hooks/useAuth';

// Landing for the signed reply link from the contact email
// (/tablon/responder/:contacto). The owner writes a reply that is emailed back
// to the (anonymous) requester. Authorization is enforced server-side: the
// caller must be authenticated and own the announcement.
export default function TablonResponder() {
    const { contacto } = useParams();
    const { isAuthenticated, loading } = useAuth();
    const [mensaje, setMensaje] = useState('');

    const mut = useMutation({
        mutationFn: async () => (await api.post(`/tablon/contactos/${contacto}/responder`, { mensaje })).data,
    });

    const Shell = ({ children }) => (
        <div className="flex min-h-full items-center justify-center bg-slate-100 px-4 py-12">
            <div className="w-full max-w-md rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
                <h1 className="text-lg font-bold text-slate-800">
                    Responder · Vacante<span className="text-brand-600">Docente</span>
                </h1>
                {children}
            </div>
        </div>
    );

    if (loading) {
        return <Shell><p className="mt-3 text-sm text-slate-400">Cargando…</p></Shell>;
    }

    if (!isAuthenticated) {
        return (
            <Shell>
                <p className="mt-3 text-sm text-slate-600">
                    Inicia sesión con la cuenta del anuncio para responder a este mensaje.
                </p>
                <a
                    href="/auth/google"
                    className="mt-4 inline-block rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700"
                >
                    Acceder con Google
                </a>
            </Shell>
        );
    }

    if (mut.isSuccess) {
        return (
            <Shell>
                <p className="mt-3 text-sm font-medium text-green-600">Respuesta enviada ✓</p>
                <p className="mt-1 text-sm text-slate-500">
                    Hemos enviado tu respuesta por email a la persona que te contactó.
                </p>
                <Link to="/dashboard/tablon/mis-anuncios" className="mt-4 inline-block text-sm font-semibold text-brand-600 hover:text-brand-700">
                    Ver mis anuncios →
                </Link>
            </Shell>
        );
    }

    return (
        <Shell>
            <p className="mt-2 text-sm text-slate-500">
                Tu respuesta se enviará por email. La otra persona permanece anónima hasta que decida compartir su información.
            </p>
            <textarea
                value={mensaje}
                onChange={(e) => setMensaje(e.target.value)}
                rows={5}
                placeholder="Escribe tu respuesta…"
                className="mt-3 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-400 focus:ring-brand-400"
            />
            {mut.isError && (
                <p className="mt-2 text-sm text-rose-600">
                    {mut.error?.response?.status === 403
                        ? 'No puedes responder a este mensaje (no es tu anuncio).'
                        : (mut.error?.friendlyMessage ?? 'No se pudo enviar la respuesta.')}
                </p>
            )}
            <button
                disabled={mut.isPending || mensaje.trim() === ''}
                onClick={() => mut.mutate()}
                className="mt-3 w-full rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700 disabled:opacity-60"
            >
                {mut.isPending ? 'Enviando…' : 'Enviar respuesta'}
            </button>
        </Shell>
    );
}
