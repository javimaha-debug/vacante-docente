import { useEffect, useRef, useState } from 'react';
import { NavLink, Outlet, useNavigate } from 'react-router-dom';
import clsx from 'clsx';
import { useAuth } from '../../hooks/useAuth';
import api from '../../lib/api';
import NotificationBell from './NotificationBell';
import OnboardingWizard from '../onboarding/OnboardingWizard';
import ImpersonationBanner from '../shared/ImpersonationBanner';
import { LogoHorizontalTeal } from '../brand/DoccentiaLogo';
import Footer from '../legal/Footer';

// Sidebar/nav items per active mode (Part 12).
const NAV_BY_MODE = {
    bolsa: [
        { to: '/dashboard', label: 'Inicio', end: true, icon: '🏠' },
        { to: '/dashboard/vacantes', label: 'Vacantes', icon: '🔎' },
        { to: '/dashboard/lista', label: 'Mi Lista', icon: '📋' },
        { to: '/dashboard/mi-posicion', label: 'Mi posición', icon: '📍' },
        { to: '/dashboard/centros', label: 'Centros', icon: '🏫' },
        { to: '/dashboard/calendario', label: 'Calendario', icon: '📅' },
        { to: '/dashboard/tablon', label: 'Tablón', icon: '📌' },
    ],
    oposicion: [
        { to: '/dashboard/oposicion', label: 'Mi preparación', icon: '📖' },
        { to: '/dashboard/mis-documentos', label: 'Mis documentos', icon: '📂' },
        { to: '/dashboard/normativa', label: 'Normativa', icon: '📄' },
        { to: '/dashboard/convocatorias', label: 'Convocatorias', icon: '📅' },
        { to: '/dashboard/asistente', label: 'Asistente IA', icon: '✨' },
    ],
    docente: [
        { to: '/dashboard/aula', label: 'Mi aula', icon: '🧑‍🏫', comingSoon: true },
        { to: '/dashboard/normativa', label: 'Normativa', icon: '📄', comingSoon: true },
        { to: '/dashboard/asistente', label: 'Asistente IA', icon: '✨', comingSoon: true },
        { to: '/dashboard/recursos', label: 'Banco de recursos', icon: '📚', comingSoon: true },
    ],
};

const MODOS = [
    { value: 'bolsa', label: 'Bolsa', icon: '📋' },
    { value: 'oposicion', label: 'Oposición', icon: '🎓' },
    { value: 'docente', label: 'Docente', icon: '👩‍🏫' },
];

function NavItem({ item, onNavigate }) {
    // Not-yet-built sections: shown but muted, non-clickable, with a badge.
    if (item.comingSoon) {
        return (
            <span
                className="flex cursor-default items-center gap-2 whitespace-nowrap rounded-lg px-3 py-2 text-sm font-medium text-slate-400"
                title="Próximamente"
                aria-disabled="true"
            >
                <span className="text-base" aria-hidden="true">{item.icon}</span>
                {item.label}
                <span className="rounded-full bg-slate-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-slate-400">
                    Próximamente
                </span>
            </span>
        );
    }

    return (
        <NavLink
            to={item.to}
            end={item.end}
            onClick={onNavigate}
            className={({ isActive }) =>
                clsx(
                    'flex items-center gap-2 whitespace-nowrap rounded-lg px-3 py-2 text-sm font-medium transition',
                    isActive ? 'bg-brand-600 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-100'
                )
            }
        >
            <span className="text-base" aria-hidden="true">{item.icon}</span>
            {item.label}
        </NavLink>
    );
}

function ModeSelector() {
    const { user, refresh, patchUser } = useAuth();
    const [open, setOpen] = useState(false);
    const ref = useRef(null);
    const navigate = useNavigate();
    const active = MODOS.find((m) => m.value === user?.modo_activo) ?? MODOS[0];

    useClickOutside(ref, () => setOpen(false));

    const change = async (modo) => {
        setOpen(false);
        if (modo === user?.modo_activo) return;
        const previous = user?.modo_activo;
        patchUser({ modo_activo: modo }); // optimistic
        const defaultRoute = (NAV_BY_MODE[modo] ?? NAV_BY_MODE.bolsa)[0].to;
        navigate(defaultRoute);
        try {
            await api.put('/user/modo', { modo_activo: modo });
            await refresh();
        } catch {
            patchUser({ modo_activo: previous }); // revert on failure
            const fallback = (NAV_BY_MODE[previous] ?? NAV_BY_MODE.bolsa)[0].to;
            navigate(fallback);
        }
    };

    return (
        <div ref={ref} className="relative">
            <button
                onClick={() => setOpen((v) => !v)}
                className="flex items-center gap-1.5 rounded-lg border border-slate-200 px-2.5 py-1.5 text-sm font-medium text-slate-600 hover:bg-slate-50"
            >
                <span aria-hidden="true">{active.icon}</span>
                <span className="hidden sm:block">{active.label}</span>
                <span className="text-xs text-slate-400">▾</span>
            </button>
            {open && (
                <div className="absolute left-0 z-40 mt-1 w-56 rounded-xl border border-slate-200 bg-white py-1 shadow-lg">
                    {MODOS.map((m) => (
                        <button
                            key={m.value}
                            onClick={() => change(m.value)}
                            className={clsx(
                                'flex w-full items-center gap-2 px-3 py-2 text-left text-sm hover:bg-slate-50',
                                m.value === active.value ? 'font-semibold text-brand-700' : 'text-slate-600'
                            )}
                        >
                            <span aria-hidden="true">{m.icon}</span>
                            {m.label}
                            {m.value === active.value && <span className="ml-auto text-brand-600">✓</span>}
                        </button>
                    ))}
                </div>
            )}
        </div>
    );
}

function AvatarMenu() {
    const { user, logout } = useAuth();
    const navigate = useNavigate();
    const [open, setOpen] = useState(false);
    const ref = useRef(null);
    useClickOutside(ref, () => setOpen(false));

    const initials = (user?.name || user?.email || '?').slice(0, 1).toUpperCase();
    const isAdmin = Boolean(user?.is_admin) || Boolean(user?.is_superadmin);

    const go = (path) => { setOpen(false); navigate(path); };

    return (
        <div ref={ref} className="relative">
            <button onClick={() => setOpen((v) => !v)} className="flex items-center gap-2 rounded-lg px-1.5 py-1 hover:bg-slate-100">
                {user?.avatar_url ? (
                    <img src={user.avatar_url} alt="" className="h-8 w-8 rounded-full ring-1 ring-slate-200" referrerPolicy="no-referrer" />
                ) : (
                    <span className="flex h-8 w-8 items-center justify-center rounded-full bg-brand-100 text-sm font-bold text-brand-700">{initials}</span>
                )}
                <span className="hidden text-sm font-medium text-slate-700 sm:block">{user?.name ?? 'Mi cuenta'}</span>
                <span className="text-xs text-slate-400">▾</span>
            </button>
            {open && (
                <div className="absolute right-0 z-40 mt-1 w-56 rounded-xl border border-slate-200 bg-white py-1 shadow-lg">
                    <div className="border-b border-slate-100 px-3 py-2">
                        <p className="truncate text-sm font-semibold text-slate-800">{user?.name}</p>
                        <p className="truncate text-xs text-slate-400">{user?.plan_label ?? 'Gratis'}</p>
                    </div>
                    <MenuItem onClick={() => go('/dashboard/perfil')} icon="👤">Mi Perfil</MenuItem>
                    <MenuItem onClick={() => go('/dashboard/especialidades')} icon="🎓">Mis Especialidades</MenuItem>
                    <MenuItem onClick={() => go('/dashboard/planes')} icon="✨">Planes</MenuItem>
                    {isAdmin && <MenuItem onClick={() => go('/superadmin')} icon="🛡️">Panel admin</MenuItem>}
                    <div className="my-1 border-t border-slate-100" />
                    <MenuItem onClick={() => { setOpen(false); logout(); }} icon="🚪">Salir</MenuItem>
                </div>
            )}
        </div>
    );
}

function MenuItem({ children, onClick, icon }) {
    return (
        <button onClick={onClick} className="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-slate-600 hover:bg-slate-50">
            <span aria-hidden="true">{icon}</span>
            {children}
        </button>
    );
}

function useClickOutside(ref, handler) {
    useEffect(() => {
        const listener = (e) => {
            if (ref.current && !ref.current.contains(e.target)) handler();
        };
        document.addEventListener('mousedown', listener);
        return () => document.removeEventListener('mousedown', listener);
    }, [ref, handler]);
}

export default function Dashboard() {
    const { user } = useAuth();
    const [open, setOpen] = useState(false);

    // Block the app behind onboarding until completed (cannot be dismissed).
    if (user && user.onboarding_completed === false) {
        return <OnboardingWizard />;
    }

    const modo = user?.modo_activo ?? 'bolsa';
    // Imports live entirely in the SuperAdmin panel now — no import UI for users.
    const fullNav = NAV_BY_MODE[modo] ?? NAV_BY_MODE.bolsa;

    return (
        <div className="flex h-full flex-col">
            <ImpersonationBanner />
            <header className="z-30 shrink-0 border-b border-slate-200 bg-white shadow-sm">
                <div className="flex items-center gap-3 px-4 py-2.5">
                    <button className="rounded-lg p-2 text-slate-500 hover:bg-slate-100 lg:hidden" onClick={() => setOpen((v) => !v)} aria-label="Menú">☰</button>
                    <LogoHorizontalTeal className="h-8 w-auto" />

                    <div className="ml-1"><ModeSelector /></div>

                    <nav className="scroll-thin ml-2 hidden items-center gap-1 overflow-x-auto lg:flex">
                        {fullNav.map((item) => <NavItem key={item.to} item={item} />)}
                    </nav>

                    <div className="ml-auto flex items-center gap-3">
                        <NotificationBell />
                        <AvatarMenu />
                    </div>
                </div>

                {open && (
                    <nav className="grid grid-cols-2 gap-1 border-t border-slate-200 px-4 pb-3 pt-2 sm:grid-cols-3 lg:hidden">
                        {fullNav.map((item) => <NavItem key={item.to} item={item} onNavigate={() => setOpen(false)} />)}
                    </nav>
                )}
            </header>

            <main className="scroll-thin min-h-0 flex-1 overflow-y-auto bg-slate-100">
                <div className="p-4 sm:p-6">
                    <Outlet />
                </div>
                <Footer />
            </main>
        </div>
    );
}
