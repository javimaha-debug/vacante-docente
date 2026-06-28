import { useState } from 'react';
import { NavLink, Outlet, useNavigate } from 'react-router-dom';
import clsx from 'clsx';
import { useAuth } from '../../hooks/useAuth';
import { AdminErrorBoundary } from './ui';

const NAV = [
    { to: '/superadmin', label: 'Dashboard', end: true, icon: '📊' },
    { to: '/superadmin/usuarios', label: 'Usuarios', icon: '👥' },
    { to: '/superadmin/suscripciones', label: 'Suscripciones', icon: '💳' },
    { to: '/superadmin/metricas', label: 'Métricas', icon: '📈' },
    { to: '/superadmin/sistema', label: 'Sistema', icon: '🛠️' },
];

function Item({ item, onNavigate }) {
    return (
        <NavLink
            to={item.to}
            end={item.end}
            onClick={onNavigate}
            className={({ isActive }) =>
                clsx(
                    'flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition',
                    isActive ? 'bg-sky-600 text-white' : 'text-slate-300 hover:bg-slate-800'
                )
            }
        >
            <span aria-hidden="true">{item.icon}</span>
            {item.label}
        </NavLink>
    );
}

/**
 * Dark-themed shell for the super-admin panel. Separate from the teacher
 * dashboard layout: fixed left sidebar (#0F172A) + content area.
 */
export default function AdminLayout() {
    const { user } = useAuth();
    const navigate = useNavigate();
    const [open, setOpen] = useState(false);

    return (
        <div className="flex h-full bg-slate-900 text-slate-100">
            {/* Sidebar */}
            <aside
                className={clsx(
                    'fixed inset-y-0 left-0 z-40 w-64 shrink-0 transform border-r border-slate-800 bg-[#0F172A] p-4 transition-transform lg:static lg:translate-x-0',
                    open ? 'translate-x-0' : '-translate-x-full'
                )}
                style={{ backgroundColor: '#0F172A' }}
            >
                <div className="mb-6 flex items-center justify-between">
                    <span className="text-lg font-extrabold tracking-tight">
                        VD <span className="text-sky-400">Admin</span>
                    </span>
                    <button className="text-slate-400 lg:hidden" onClick={() => setOpen(false)} aria-label="Cerrar menú">✕</button>
                </div>
                <nav className="space-y-1">
                    {NAV.map((item) => (
                        <Item key={item.to} item={item} onNavigate={() => setOpen(false)} />
                    ))}
                </nav>
                <div className="mt-8 border-t border-slate-800 pt-4">
                    <button
                        onClick={() => navigate('/dashboard')}
                        className="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-sm text-slate-400 hover:bg-slate-800"
                    >
                        ← Volver a la app
                    </button>
                </div>
            </aside>

            {open && <div className="fixed inset-0 z-30 bg-black/50 lg:hidden" onClick={() => setOpen(false)} />}

            {/* Content */}
            <div className="flex min-w-0 flex-1 flex-col">
                <header className="flex items-center gap-3 border-b border-slate-800 px-4 py-3 lg:px-6">
                    <button className="rounded-lg p-2 text-slate-400 hover:bg-slate-800 lg:hidden" onClick={() => setOpen(true)} aria-label="Menú">☰</button>
                    <h1 className="text-sm font-semibold text-slate-300">Panel de administración</h1>
                    <div className="ml-auto flex items-center gap-2 text-sm text-slate-400">
                        <span className="hidden sm:block">{user?.name}</span>
                        <span className="flex h-8 w-8 items-center justify-center rounded-full bg-sky-600/30 text-sm font-bold text-sky-300">
                            {(user?.name || '?').slice(0, 1).toUpperCase()}
                        </span>
                    </div>
                </header>
                <main className="scroll-thin min-h-0 flex-1 overflow-y-auto p-4 lg:p-6">
                    <AdminErrorBoundary>
                        <Outlet />
                    </AdminErrorBoundary>
                </main>
            </div>
        </div>
    );
}
