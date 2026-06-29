import { useState } from 'react';
import { NavLink, Outlet, useNavigate } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import clsx from 'clsx';
import { useAuth } from '../../hooks/useAuth';
import { AdminErrorBoundary } from './ui';
import { LogoHorizontalWhite } from '../brand/DoccentiaLogo';
import api from '../../lib/api';

const NAV = [
    { to: '/superadmin', label: 'Dashboard', end: true, icon: '📊' },
    { to: '/superadmin/usuarios', label: 'Usuarios', icon: '👥' },
    { to: '/superadmin/suscripciones', label: 'Suscripciones', icon: '💳' },
    { to: '/superadmin/convocatorias', label: 'Convocatorias', icon: '📅' },
    { to: '/superadmin/normativa', label: 'Normativa', icon: '📄' },
    { to: '/superadmin/temarios', label: 'Temarios', icon: '📚' },
    { to: '/superadmin/recursos', label: 'Recursos', icon: '🔗' },
    { to: '/superadmin/importaciones', label: 'Importaciones', icon: '🔄' },
    { to: '/superadmin/monitor-docs', label: 'Monitor Docs', icon: '📑', badge: 'docs-pending' },
    { to: '/superadmin/calendario', label: 'Calendario', icon: '📅' },
    { to: '/superadmin/ia-usage', label: 'IA Usage', icon: '🤖' },
    { to: '/superadmin/metricas', label: 'Métricas', icon: '📈' },
    { to: '/superadmin/modo-docente', label: 'Modo Docente', icon: '🧑‍🏫', badge: 'docente-pendiente' },
    { to: '/superadmin/sistema', label: 'Sistema', icon: '🛠️' },
];

function Item({ item, onNavigate, count }) {
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
            <span className="flex-1">{item.label}</span>
            {count > 0 && (
                <span className="rounded-full bg-rose-500 px-1.5 py-0.5 text-[10px] font-bold text-white">{count}</span>
            )}
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

    // Pending-docs badge on the "Monitor Docs" nav item.
    const { data: docStats } = useQuery({
        queryKey: ['admin', 'documents', 'stats'],
        queryFn: async () => (await api.get('/superadmin/documents/stats')).data,
        refetchInterval: 120000,
    });
    const pendingDocs = docStats?.pendientes ?? 0;

    // Pending moderation badge on "Modo Docente" nav item.
    const { data: docenteStats } = useQuery({
        queryKey: ['admin-docente-stats'],
        queryFn: async () => (await api.get('/superadmin/docente/stats')).data,
        refetchInterval: 120000,
    });
    const pendingDocente = docenteStats?.banco?.pendientes_moderacion ?? 0;

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
                    <div className="flex items-center gap-2">
                        <LogoHorizontalWhite className="h-6 w-auto" />
                        <span className="text-xs font-semibold uppercase tracking-wide text-sky-400">Admin</span>
                    </div>
                    <button className="text-slate-400 lg:hidden" onClick={() => setOpen(false)} aria-label="Cerrar menú">✕</button>
                </div>
                <nav className="space-y-1">
                    {NAV.map((item) => (
                        <Item
                            key={item.to}
                            item={item}
                            onNavigate={() => setOpen(false)}
                            count={item.badge === 'docs-pending' ? pendingDocs : item.badge === 'docente-pendiente' ? pendingDocente : 0}
                        />
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
