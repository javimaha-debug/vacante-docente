import { useState } from 'react';

export default function Layout({ specialty, vacancyCount, onChangeSpecialty, onExport, viewMode, onViewModeChange, sidebar, children }) {
    const [sidebarOpen, setSidebarOpen] = useState(false);

    return (
        <div className="flex h-full flex-col">
            {/* Header */}
            <header className="z-20 flex shrink-0 items-center gap-3 border-b border-slate-200 bg-white px-4 py-2.5 shadow-sm">
                <button
                    className="rounded-lg p-2 text-slate-500 hover:bg-slate-100 lg:hidden"
                    onClick={() => setSidebarOpen((v) => !v)}
                    aria-label="Menú"
                >
                    ☰
                </button>

                <div className="flex items-center gap-2">
                    <span className="text-lg font-extrabold tracking-tight text-slate-900">
                        Vacante<span className="text-brand-600">Docente</span>
                    </span>
                </div>

                {specialty && (
                    <div className="hidden items-center gap-2 sm:flex">
                        <span className="text-slate-300">/</span>
                        <span className="text-sm font-medium text-slate-700">{specialty.name}</span>
                        <span className="rounded-full bg-brand-100 px-2 py-0.5 text-xs font-bold text-brand-700">
                            {vacancyCount}
                        </span>
                    </div>
                )}

                <div className="ml-auto flex items-center gap-2">
                    {onViewModeChange && (
                        <div className="hidden rounded-lg bg-slate-100 p-0.5 sm:flex">
                            <ViewTab active={viewMode === 'kanban'} onClick={() => onViewModeChange('kanban')}>
                                Kanban
                            </ViewTab>
                            <ViewTab active={viewMode === 'list'} onClick={() => onViewModeChange('list')}>
                                Lista
                            </ViewTab>
                        </div>
                    )}
                    <button
                        onClick={onChangeSpecialty}
                        className="rounded-lg px-3 py-1.5 text-sm font-medium text-slate-600 hover:bg-slate-100"
                    >
                        Cambiar especialidad
                    </button>
                    <button
                        onClick={onExport}
                        className="rounded-lg bg-brand-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-brand-700"
                    >
                        Exportar lista
                    </button>
                </div>
            </header>

            {/* Body */}
            <div className="flex min-h-0 flex-1">
                {/* Sidebar */}
                <aside
                    className={[
                        'scroll-thin absolute inset-y-0 left-0 z-30 w-80 shrink-0 overflow-y-auto border-r border-slate-200 bg-white p-4 pt-16 transition-transform lg:static lg:z-0 lg:translate-x-0 lg:pt-4',
                        sidebarOpen ? 'translate-x-0 shadow-2xl' : '-translate-x-full',
                    ].join(' ')}
                >
                    {sidebar}
                </aside>

                {sidebarOpen && (
                    <div className="absolute inset-0 z-20 bg-slate-900/30 lg:hidden" onClick={() => setSidebarOpen(false)} />
                )}

                {/* Main */}
                <main className="min-h-0 flex-1 overflow-hidden p-3 sm:p-4">{children}</main>
            </div>
        </div>
    );
}

function ViewTab({ active, onClick, children }) {
    return (
        <button
            onClick={onClick}
            className={[
                'rounded-md px-3 py-1 text-xs font-semibold transition',
                active ? 'bg-white text-brand-700 shadow' : 'text-slate-500 hover:text-slate-800',
            ].join(' ')}
        >
            {children}
        </button>
    );
}
