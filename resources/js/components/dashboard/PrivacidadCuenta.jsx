import { useState } from 'react';
import { Link } from 'react-router-dom';
import api from '../../lib/api';
import { useAuth } from '../../hooks/useAuth';

// "Privacidad y datos" card: lets the user exercise their RGPD rights directly
// — download a copy of their data (art. 20) and delete their account (art. 17).
export default function PrivacidadCuenta() {
    const { logout } = useAuth();
    const [downloading, setDownloading] = useState(false);
    const [confirmOpen, setConfirmOpen] = useState(false);
    const [confirmText, setConfirmText] = useState('');
    const [deleting, setDeleting] = useState(false);
    const [error, setError] = useState(null);

    const downloadData = async () => {
        setError(null);
        setDownloading(true);
        try {
            const res = await api.get('/user/export');
            const blob = new Blob([JSON.stringify(res.data, null, 2)], { type: 'application/json' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'doccentia-mis-datos.json';
            document.body.appendChild(a);
            a.click();
            a.remove();
            URL.revokeObjectURL(url);
        } catch (err) {
            setError(err?.friendlyMessage ?? 'No se pudieron descargar tus datos.');
        } finally {
            setDownloading(false);
        }
    };

    const deleteAccount = async () => {
        setError(null);
        setDeleting(true);
        try {
            await api.delete('/user/account', { data: { confirmacion: confirmText } });
            await logout();
            window.location.assign('/');
        } catch (err) {
            setError(err?.friendlyMessage ?? 'No se pudo eliminar la cuenta.');
            setDeleting(false);
        }
    };

    return (
        <div className="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200 sm:p-6">
            <h2 className="font-heading text-lg font-bold text-slate-800">Privacidad y datos</h2>
            <p className="mt-1 text-sm text-slate-500">
                Gestiona tus derechos de protección de datos. Consulta nuestra{' '}
                <Link to="/legal/privacidad" className="font-medium text-brand-700 underline">Política de privacidad</Link>.
            </p>

            <div className="mt-5 space-y-4">
                <div className="flex flex-col gap-2 rounded-xl bg-slate-50 p-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p className="text-sm font-semibold text-slate-800">Descargar mis datos</p>
                        <p className="text-xs text-slate-500">Una copia en formato JSON de los datos asociados a tu cuenta (derecho de portabilidad).</p>
                    </div>
                    <button
                        type="button"
                        onClick={downloadData}
                        disabled={downloading}
                        className="shrink-0 rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-white disabled:opacity-60"
                    >
                        {downloading ? 'Preparando…' : 'Descargar'}
                    </button>
                </div>

                <div className="flex flex-col gap-2 rounded-xl bg-rose-50 p-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p className="text-sm font-semibold text-rose-800">Eliminar mi cuenta</p>
                        <p className="text-xs text-rose-600">Borra de forma permanente tu cuenta, tus documentos y todos tus datos. Esta acción no se puede deshacer.</p>
                    </div>
                    <button
                        type="button"
                        onClick={() => { setConfirmOpen(true); setConfirmText(''); setError(null); }}
                        className="shrink-0 rounded-lg bg-rose-600 px-4 py-2 text-sm font-semibold text-white hover:bg-rose-700"
                    >
                        Eliminar cuenta
                    </button>
                </div>

                {error && <p className="text-sm text-rose-600">{error}</p>}
            </div>

            {confirmOpen && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4" onClick={() => !deleting && setConfirmOpen(false)}>
                    <div className="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl" onClick={(e) => e.stopPropagation()}>
                        <h3 className="font-heading text-lg font-bold text-slate-900">¿Eliminar tu cuenta?</h3>
                        <p className="mt-2 text-sm text-slate-600">
                            Se eliminarán de forma permanente tu perfil, tus documentos, tus conversaciones con la IA y el
                            resto de tus datos. Para confirmar, escribe <span className="font-bold">ELIMINAR</span>.
                        </p>
                        <input
                            type="text"
                            value={confirmText}
                            onChange={(e) => setConfirmText(e.target.value)}
                            placeholder="ELIMINAR"
                            className="mt-4 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-rose-400 focus:ring-rose-400"
                            autoFocus
                        />
                        {error && <p className="mt-2 text-sm text-rose-600">{error}</p>}
                        <div className="mt-5 flex justify-end gap-2">
                            <button
                                type="button"
                                onClick={() => setConfirmOpen(false)}
                                disabled={deleting}
                                className="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                            >
                                Cancelar
                            </button>
                            <button
                                type="button"
                                onClick={deleteAccount}
                                disabled={deleting || confirmText !== 'ELIMINAR'}
                                className="rounded-lg bg-rose-600 px-4 py-2 text-sm font-semibold text-white hover:bg-rose-700 disabled:opacity-50"
                            >
                                {deleting ? 'Eliminando…' : 'Eliminar definitivamente'}
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}
