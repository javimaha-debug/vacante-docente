import LegalLayout, { Section } from './LegalLayout';
import { openCookiePreferences } from './CookieBanner';

export default function PoliticaCookies() {
    return (
        <LegalLayout
            title="Política de cookies"
            subtitle="Qué cookies y almacenamiento local usamos (art. 22.2 LSSI-CE)."
        >
            <Section title="1. ¿Qué son las cookies?">
                <p>
                    Las cookies y tecnologías similares (como el almacenamiento local del navegador) son pequeños archivos
                    de información que se guardan en tu dispositivo cuando usas la aplicación. Permiten que el servicio
                    funcione y, en su caso, recoger información de uso.
                </p>
            </Section>

            <Section title="2. Cookies técnicas (necesarias)">
                <p>
                    Son imprescindibles para el funcionamiento y no requieren consentimiento. Usamos almacenamiento local
                    para mantener tu sesión y recordar tus preferencias:
                </p>
                <ul className="ml-5 list-disc space-y-1">
                    <li><code>vd.session_token</code> — identifica tus listas cuando no has iniciado sesión.</li>
                    <li><code>vd.specialty_id</code>, <code>vd.proceso_id</code>, <code>vd.explorer_filters</code> — recuerdan tu selección y filtros.</li>
                    <li><code>vd.cookie_consent</code> — guarda tu elección sobre las cookies.</li>
                    <li>Token de sesión (autenticación) cuando inicias sesión.</li>
                </ul>
            </Section>

            <Section title="3. Cookies analíticas (opcionales)">
                <p>
                    Con tu consentimiento, usamos herramientas de monitorización (Sentry) para medir el rendimiento y
                    detectar errores de forma agregada, con el fin de mejorar el servicio. Puedes activarlas o desactivarlas
                    en cualquier momento. La monitorización básica de errores técnicos se realiza sin datos identificativos.
                </p>
            </Section>

            <Section title="4. Gestión de tus preferencias">
                <p>
                    Puedes aceptar, rechazar o configurar las cookies no esenciales en cualquier momento:
                </p>
                <p>
                    <button
                        type="button"
                        onClick={openCookiePreferences}
                        className="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700"
                    >
                        Configurar cookies
                    </button>
                </p>
                <p>
                    También puedes eliminar o bloquear las cookies desde la configuración de tu navegador, aunque ello
                    podría afectar al funcionamiento de la aplicación.
                </p>
            </Section>
        </LegalLayout>
    );
}
