import { useState } from 'react';
import { NavLink, Outlet } from 'react-router-dom';
import clsx from 'clsx';
import { useAuth } from '../../hooks/useAuth';

const NAV = [
    { to: '/dashboard', label: 'Inicio', end: true, icon: '🏠' },
    { to: '/dashboard/perfil', label: 'Mi Perfil', icon: '👤' },
    { to: '/dashboard/especialidades', label: 'Mis Especialidades', icon: '🎓' },
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
                    'flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition',
                    isActive
                        ? 'bg-brand-600 text-white shadow-sm'
                        : 'text-slate-600 hover:bg-slate-100'
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
            {/* Top bar */}
            <header className="z-30 flex shrink-0 items-center gap-3 border-b border-slate-200 bg-white px-4 py-2.5 shadow-sm">
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

                <div className="ml-auto flex items-center gap-3">
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
            </header>

            <div className="flex min-h-0 flex-1">
                {/* Sidebar — overlay on mobile, fixed column on desktop */}
                {open && (
                    <div
                        className="fixed inset-0 z-20 bg-slate-900/30 lg:hidden"
                        onClick={() => setOpen(false)}
                        aria-hidden="true"
                    />
                )}
                <aside
                    className={clsx(
                        'fixed inset-y-0 left-0 z-20 w-64 shrink-0 border-r border-slate-200 bg-white p-3 pt-16 transition-transform lg:static lg:z-0 lg:translate-x-0 lg:pt-3',
                        open ? 'translate-x-0' : '-translate-x-full'
                    )}
                >
                    <nav className="space-y-1">
                        {NAV.map((item) => (
                            <NavItem key={item.to} item={item} onNavigate={() => setOpen(false)} />
                        ))}
                    </nav>
                </aside>

                {/* Main content */}
                <main className="scroll-thin min-h-0 flex-1 overflow-y-auto bg-slate-100 p-4 sm:p-6">
                    <Outlet />
                </main>
            </div>
        </div>
    );
}
