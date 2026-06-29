import { Link } from 'react-router-dom';
import { LogoHorizontalTeal } from '../brand/DoccentiaLogo';
import Footer from './Footer';
import { ACTUALIZADO } from './info';

// Shared shell + typography for the public legal pages.
export default function LegalLayout({ title, subtitle, children }) {
    return (
        <div className="flex min-h-full flex-col bg-slate-50">
            <header className="border-b border-slate-200 bg-white">
                <div className="mx-auto flex max-w-3xl items-center justify-between px-4 py-3">
                    <Link to="/" aria-label="Inicio">
                        <LogoHorizontalTeal className="h-8 w-auto" />
                    </Link>
                    <Link to="/" className="text-sm font-semibold text-brand-700 hover:text-brand-800">
                        ← Volver
                    </Link>
                </div>
            </header>

            <main className="mx-auto w-full max-w-3xl flex-1 px-4 py-8 sm:py-12">
                <h1 className="font-heading text-2xl font-bold text-slate-900 sm:text-3xl">{title}</h1>
                {subtitle && <p className="mt-2 text-sm text-slate-500">{subtitle}</p>}
                <p className="mt-1 text-xs text-slate-400">Última actualización: {ACTUALIZADO}</p>

                <div className="legal-prose mt-8 space-y-6 text-sm leading-relaxed text-slate-700">
                    {children}
                </div>
            </main>

            <Footer />
        </div>
    );
}

// Small heading + paragraph helpers so each document reads consistently.
export function H2({ children }) {
    return <h2 className="font-heading text-lg font-bold text-slate-900">{children}</h2>;
}

export function Section({ title, children }) {
    return (
        <section className="space-y-2">
            <H2>{title}</H2>
            {children}
        </section>
    );
}
