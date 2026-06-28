import { useState } from 'react';
import { NavLink, Outlet } from 'react-router-dom';
import clsx from 'clsx';
import { useAuth } from '../../hooks/useAuth';
import NotificationBell from './NotificationBell';

const NAV = [
    { to: '/dashboard', label: 'Inicio', end: true, icon: '🏠' },
    { to: '/dashboard/perfil', label: 'Mi Perfil', icon: '👤' },
    { to: '/dashboard/vacantes', label: 'Explorador Vacantes', icon: '🔎' },
    { to: '/dashboard/lista', label: 'Mi Lista', icon: '📋' },
    { to: '/dashboard/centros', label: 'Centros', icon: '🏫' },
    { to: '/dashboard/tablon', label: 'Tablón', icon: '📌' },
];

function NavItem({ item, onNavigate }) {
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

export default function Dashboard() {
    const { user, logout } = useAuth();
    const [open, setOpen] = useState(false);

    const initials = (user?.name || user?.email || '?').slice(0, 1).toUpperCase();

    return (
        <div className="flex h-full flex-col">
            {/* Top bar with horizontal navigation */}
            <header className="z-30 shrink-0 border-b border-slate-200 bg-white shadow-sm">
                <div className="flex items-center gap-3 px-4 py-2.5">
                    <button
                        className="rounded-lg p-2 text-slate-500 hover:bg-slate-100 lg:hidden"
                        onClick={() => setOpen((v) => !v)}
                        aria-label="Menú"
                    >
                        ☰
                    </button>
                    <span className="text-lg font-extrabold tracking-tight text-slate-900">
                        Vacante<span className="text-brand-600">Docente</span>
                    </span>

                    {/* Horizontal menu (desktop) */}
                    <nav className="scroll-thin ml-2 hidden items-center gap-1 overflow-x-auto lg:flex">
                        {NAV.map((item) => (
                            <NavItem key={item.to} item={item} />
                        ))}
                    </nav>

                    <div className="ml-auto flex items-center gap-3">
                        <NotificationBell />
                        <div className="flex items-center gap-2">
                            {user?.avatar_url ? (
                                <img
                                    src={user.avatar_url}
                                    alt=""
                                    className="h-8 w-8 rounded-full ring-1 ring-slate-200"
                                    referrerPolicy="no-referrer"
                                />
                            ) : (
                                <span className="flex h-8 w-8 items-center justify-center rounded-full bg-brand-100 text-sm font-bold text-brand-700">
                                    {initials}
                                </span>
                            )}
                            <span className="hidden text-sm font-medium text-slate-700 sm:block">
                                {user?.name ?? 'Mi cuenta'}
                            </span>
                        </div>
                        <button
                            onClick={logout}
                            className="rounded-lg px-3 py-1.5 text-sm font-medium text-slate-600 hover:bg-slate-100"
                        >
                            Salir
                        </button>
                    </div>
                </div>

                {/* Collapsible menu (mobile/tablet) */}
                {open && (
                    <nav className="grid grid-cols-2 gap-1 border-t border-slate-200 px-4 pb-3 pt-2 sm:grid-cols-3 lg:hidden">
                        {NAV.map((item) => (
                            <NavItem key={item.to} item={item} onNavigate={() => setOpen(false)} />
                        ))}
                    </nav>
                )}
            </header>

            {/* Main content — full width, fits the screen */}
            <main className="scroll-thin min-h-0 flex-1 overflow-y-auto bg-slate-100 p-4 sm:p-6">
                <Outlet />
            </main>
        </div>
    );
}
