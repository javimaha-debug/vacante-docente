import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { getConsent, hasDecided, saveConsent, acceptAll, rejectAll } from '../../lib/consent';

// Opens the granular preferences panel from anywhere (e.g. the footer link).
export function openCookiePreferences() {
    window.dispatchEvent(new CustomEvent('vd:open-cookie-preferences'));
}

// LSSI-CE art. 22.2 cookie consent. Non-essential categories are opt-in; the
// banner blocks nothing but stays until the user makes an explicit choice
// (accept / reject / configure all carry equal weight, per AEPD guidance).
export default function CookieBanner() {
    const [visible, setVisible] = useState(() => !hasDecided());
    const [configuring, setConfiguring] = useState(false);
    const [analiticas, setAnaliticas] = useState(() => getConsent()?.analiticas ?? false);

    useEffect(() => {
        const open = () => {
            setAnaliticas(getConsent()?.analiticas ?? false);
            setConfiguring(true);
            setVisible(true);
        };
        window.addEventListener('vd:open-cookie-preferences', open);
        return () => window.removeEventListener('vd:open-cookie-preferences', open);
    }, []);

    if (!visible) return null;

    const close = () => {
        setVisible(false);
        setConfiguring(false);
    };

    const handleAcceptAll = () => { acceptAll(); close(); };
    const handleRejectAll = () => { rejectAll(); close(); };
    const handleSave = () => { saveConsent({ analiticas }); close(); };

    return (
        <div className="fixed inset-x-0 bottom-0 z-[60] p-3 sm:p-4">
            <div className="mx-auto max-w-2xl rounded-2xl bg-white p-5 shadow-xl ring-1 ring-slate-200">
                <h2 className="font-heading text-base font-bold text-slate-900">🍪 Cookies y privacidad</h2>
                <p className="mt-2 text-sm leading-relaxed text-slate-600">
                    Usamos cookies y almacenamiento local <span className="font-semibold">técnicos</span>, necesarios para
                    que la aplicación funcione (sesión, preferencias). De forma opcional, usamos cookies
                    <span className="font-semibold"> analíticas</span> para entender el uso y mejorar el servicio. Puedes
                    aceptarlas, rechazarlas o configurarlas. Más información en nuestra{' '}
                    <Link to="/legal/cookies" className="font-semibold text-brand-700 underline">Política de cookies</Link> y{' '}
                    <Link to="/legal/privacidad" className="font-semibold text-brand-700 underline">Política de privacidad</Link>.
                </p>

                {configuring && (
                    <div className="mt-4 space-y-3 rounded-xl bg-slate-50 p-4">
                        <label className="flex items-start gap-3 opacity-70">
                            <input type="checkbox" checked disabled className="mt-0.5 h-4 w-4 rounded" />
                            <span className="text-sm">
                                <span className="font-semibold text-slate-800">Técnicas (necesarias)</span>
                                <span className="block text-xs text-slate-500">Imprescindibles para iniciar sesión y guardar tus preferencias. Siempre activas.</span>
                            </span>
                        </label>
                        <label className="flex items-start gap-3">
                            <input
                                type="checkbox"
                                checked={analiticas}
                                onChange={(e) => setAnaliticas(e.target.checked)}
                                className="mt-0.5 h-4 w-4 rounded text-brand-600 focus:ring-brand-500"
                            />
                            <span className="text-sm">
                                <span className="font-semibold text-slate-800">Analíticas</span>
                                <span className="block text-xs text-slate-500">Medición de uso y rendimiento de forma anónima/agregada. Opcional.</span>
                            </span>
                        </label>
                    </div>
                )}

                <div className="mt-4 flex flex-col gap-2 sm:flex-row sm:justify-end">
                    {configuring ? (
                        <button onClick={handleSave} className="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">
                            Guardar preferencias
                        </button>
                    ) : (
                        <button onClick={() => setConfiguring(true)} className="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                            Configurar
                        </button>
                    )}
                    <button onClick={handleRejectAll} className="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                        Rechazar
                    </button>
                    <button onClick={handleAcceptAll} className="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">
                        Aceptar todas
                    </button>
                </div>
            </div>
        </div>
    );
}
