import { Link } from 'react-router-dom';
import { TITULAR } from './info';
import { openCookiePreferences } from './CookieBanner';

const LINKS = [
    { to: '/legal/aviso-legal', label: 'Aviso legal' },
    { to: '/legal/privacidad', label: 'Política de privacidad' },
    { to: '/legal/cookies', label: 'Política de cookies' },
    { to: '/legal/terminos', label: 'Términos y condiciones' },
];

// Public footer with the legally required links. Rendered on the login screen,
// the dashboard and the legal pages themselves.
export default function Footer() {
    const year = new Date().getFullYear();
    return (
        <footer className="border-t border-slate-200 bg-white">
            <div className="mx-auto flex max-w-5xl flex-col items-center gap-3 px-4 py-5 text-center sm:flex-row sm:justify-between sm:text-left">
                <p className="text-xs text-slate-400">
                    © {year} {TITULAR.marca}. Todos los derechos reservados.
                </p>
                <nav className="flex flex-wrap items-center justify-center gap-x-4 gap-y-1">
                    {LINKS.map((l) => (
                        <Link key={l.to} to={l.to} className="text-xs font-medium text-slate-500 hover:text-brand-700">
                            {l.label}
                        </Link>
                    ))}
                    <button
                        type="button"
                        onClick={openCookiePreferences}
                        className="text-xs font-medium text-slate-500 hover:text-brand-700"
                    >
                        Configurar cookies
                    </button>
                </nav>
            </div>
        </footer>
    );
}
