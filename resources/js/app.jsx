import '../css/app.css';
import { useCallback, useMemo, useRef, useState } from 'react';
import { createRoot } from 'react-dom/client';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';

import Layout from './components/Layout';
import SpecialtySelector from './components/SpecialtySelector';
import FiltersPanel from './components/FiltersPanel';
import HomeAddressPanel from './components/HomeAddressPanel';
import KanbanBoard from './components/KanbanBoard';
import SortableList from './components/SortableList';
import VacancyCard from './components/VacancyCard';
import ExportPanel from './components/ExportPanel';

import LoginPage from './components/auth/LoginPage';
import Dashboard from './components/dashboard/Dashboard';
import DashboardHome from './components/dashboard/DashboardHome';
import UserProfile from './components/dashboard/UserProfile';
import MisEspecialidades from './components/dashboard/MisEspecialidades';

import { useUserList } from './hooks/useUserList';
import { useVacancies } from './hooks/useVacancies';
import { useDistances } from './hooks/useDistances';
import { AuthContext, useAuth, useProvideAuth } from './hooks/useAuth';
import { getSpecialtyId, setSpecialtyId, clearSpecialty } from './lib/session';

const queryClient = new QueryClient({
    defaultOptions: { queries: { refetchOnWindowFocus: false, retry: 1 } },
});

const EMPTY_FILTERS = { search: '', provincia: '', tiposCentro: [], tags: [] };

function Organizer({ specialtyId, onChangeSpecialty, initialView = 'kanban' }) {
    const [filters, setFilters] = useState(EMPTY_FILTERS);
    const [showDiscarded, setShowDiscarded] = useState(false);
    const [viewMode, setViewMode] = useState(initialView);
    const [exporting, setExporting] = useState(false);
    const notesTimers = useRef({});

    const { list, listId, preferences, savePreferences, updateAddress, geocode } = useUserList(specialtyId);
    const distances = useDistances(listId);
    const { vacancies, total, isLoading, hasNextPage, fetchNextPage, isFetchingNextPage } = useVacancies(
        specialtyId,
        filters
    );

    // Preference lookup + derived columns.
    const prefByVacancy = useMemo(() => {
        const map = new Map();
        for (const p of preferences) map.set(p.vacancy_id, p);
        return map;
    }, [preferences]);

    const selected = useMemo(() => preferences.filter((p) => p.status === 'selected'), [preferences]);
    const discarded = useMemo(() => preferences.filter((p) => p.status === 'discarded'), [preferences]);
    const neutral = useMemo(
        () => vacancies.filter((v) => (prefByVacancy.get(v.id)?.status ?? 'neutral') === 'neutral'),
        [vacancies, prefByVacancy]
    );

    const persist = useCallback(
        (rows) => savePreferences.mutate(rows),
        [savePreferences]
    );

    const handleStatusChange = useCallback(
        (vacancyId, status) => {
            const existing = prefByVacancy.get(vacancyId);
            const position = status === 'selected' ? selected.length + 1 : 0;
            persist([{ vacancy_id: vacancyId, status, position, notes: existing?.notes ?? null }]);
        },
        [prefByVacancy, selected.length, persist]
    );

    const handleReorder = useCallback(
        (orderedVacancyIds) => {
            const rows = orderedVacancyIds.map((vacancyId, index) => {
                const existing = prefByVacancy.get(vacancyId);
                return {
                    vacancy_id: vacancyId,
                    status: 'selected',
                    position: index + 1,
                    notes: existing?.notes ?? null,
                };
            });
            persist(rows);
        },
        [prefByVacancy, persist]
    );

    const handleNotesChange = useCallback(
        (vacancyId, notes) => {
            clearTimeout(notesTimers.current[vacancyId]);
            notesTimers.current[vacancyId] = setTimeout(() => {
                const existing = prefByVacancy.get(vacancyId);
                if (!existing) return;
                persist([
                    {
                        vacancy_id: vacancyId,
                        status: existing.status,
                        position: existing.position,
                        notes,
                    },
                ]);
            }, 700);
        },
        [prefByVacancy, persist]
    );

    const counts = { total, selected: selected.length, discarded: discarded.length };

    const sidebar = (
        <div className="space-y-5">
            <HomeAddressPanel list={list} geocode={geocode} distances={distances} selectedCount={selected.length} />
            <FiltersPanel
                filters={filters}
                setFilters={setFilters}
                counts={counts}
                showDiscarded={showDiscarded}
                setShowDiscarded={setShowDiscarded}
            />
        </div>
    );

    return (
        <Layout
            specialty={list?.specialty}
            vacancyCount={total}
            onChangeSpecialty={onChangeSpecialty}
            onExport={() => setExporting(true)}
            viewMode={viewMode}
            onViewModeChange={setViewMode}
            sidebar={sidebar}
        >
            {isLoading ? (
                <div className="flex h-full items-center justify-center text-sm text-slate-400">Cargando vacantes…</div>
            ) : viewMode === 'kanban' ? (
                <KanbanBoard
                    neutral={neutral}
                    selected={selected}
                    discarded={discarded}
                    showDiscarded={showDiscarded}
                    onStatusChange={handleStatusChange}
                    onNotesChange={handleNotesChange}
                    onReorder={handleReorder}
                    hasMore={hasNextPage}
                    onLoadMore={fetchNextPage}
                    isLoadingMore={isFetchingNextPage}
                />
            ) : (
                <ListView
                    selected={selected}
                    neutral={neutral}
                    onStatusChange={handleStatusChange}
                    onNotesChange={handleNotesChange}
                    onReorder={handleReorder}
                    hasMore={hasNextPage}
                    onLoadMore={fetchNextPage}
                    isLoadingMore={isFetchingNextPage}
                />
            )}

            {exporting && (
                <ExportPanel selected={selected} specialty={list?.specialty} onClose={() => setExporting(false)} />
            )}
        </Layout>
    );
}

function ListView({ selected, neutral, onStatusChange, onNotesChange, onReorder, hasMore, onLoadMore, isLoadingMore }) {
    return (
        <div className="scroll-thin mx-auto h-full max-w-3xl space-y-6 overflow-y-auto pr-1">
            <section>
                <h2 className="mb-2 text-sm font-bold text-slate-700">
                    Mi lista priorizada <span className="text-slate-400">({selected.length})</span>
                </h2>
                <SortableList
                    items={selected}
                    onReorder={onReorder}
                    onStatusChange={onStatusChange}
                    onNotesChange={onNotesChange}
                />
            </section>

            <section>
                <h2 className="mb-2 text-sm font-bold text-slate-700">
                    Todas las vacantes <span className="text-slate-400">({neutral.length})</span>
                </h2>
                <div className="space-y-2">
                    {neutral.map((v) => (
                        <VacancyCard
                            key={v.id}
                            vacancy={v}
                            status="neutral"
                            onStatusChange={(status) => onStatusChange(v.id, status)}
                        />
                    ))}
                </div>
                {hasMore && (
                    <button
                        onClick={onLoadMore}
                        disabled={isLoadingMore}
                        className="mt-3 w-full rounded-lg bg-white px-3 py-2 text-sm font-semibold text-brand-600 ring-1 ring-slate-200 hover:bg-brand-50 disabled:opacity-60"
                    >
                        {isLoadingMore ? 'Cargando…' : 'Cargar más vacantes'}
                    </button>
                )}
            </section>
        </div>
    );
}

// Existing vacancy explorer flow (specialty selection → kanban/list organizer).
// Rendered inside the dashboard at /dashboard/vacantes and /dashboard/lista.
function VacancyExplorer({ initialView = 'kanban' }) {
    const [specialtyId, setActiveSpecialty] = useState(getSpecialtyId());
    const [isSelecting, setIsSelecting] = useState(false);

    const handleSelect = (specialty) => {
        setIsSelecting(true);
        setSpecialtyId(specialty.id);
        setActiveSpecialty(specialty.id);
        setIsSelecting(false);
    };

    const handleChangeSpecialty = () => {
        clearSpecialty();
        setActiveSpecialty(null);
    };

    if (!specialtyId) {
        return <SpecialtySelector onSelect={handleSelect} isSelecting={isSelecting} />;
    }

    return (
        <div className="-m-4 h-full sm:-m-6">
            <Organizer
                specialtyId={specialtyId}
                onChangeSpecialty={handleChangeSpecialty}
                initialView={initialView}
            />
        </div>
    );
}

function ComingSoon({ title }) {
    return (
        <div className="mx-auto max-w-md rounded-2xl bg-white p-8 text-center shadow-sm ring-1 ring-slate-200">
            <h1 className="text-lg font-bold text-slate-800">{title}</h1>
            <p className="mt-2 text-sm text-slate-400">Esta sección estará disponible próximamente.</p>
        </div>
    );
}

function Spinner() {
    return <div className="flex h-full items-center justify-center text-sm text-slate-400">Cargando…</div>;
}

// Provides shared auth state to the tree.
function AuthProvider({ children }) {
    const auth = useProvideAuth();
    return <AuthContext.Provider value={auth}>{children}</AuthContext.Provider>;
}

// Gate for the dashboard: wait for the user to load, redirect to login if absent.
function RequireAuth({ children }) {
    const { isAuthenticated, loading } = useAuth();
    if (loading) return <Spinner />;
    if (!isAuthenticated) return <Navigate to="/" replace />;
    return children;
}

// Root: authenticated users go to the dashboard, everyone else sees login.
function RootRoute() {
    const { isAuthenticated, loading } = useAuth();
    if (loading) return <Spinner />;
    return isAuthenticated ? <Navigate to="/dashboard" replace /> : <LoginPage />;
}

function AppRoutes() {
    return (
        <Routes>
            <Route path="/" element={<RootRoute />} />
            <Route
                path="/dashboard"
                element={
                    <RequireAuth>
                        <Dashboard />
                    </RequireAuth>
                }
            >
                <Route index element={<DashboardHome />} />
                <Route path="perfil" element={<UserProfile />} />
                <Route path="especialidades" element={<MisEspecialidades />} />
                <Route path="vacantes" element={<VacancyExplorer initialView="kanban" />} />
                <Route path="lista" element={<VacancyExplorer initialView="list" />} />
                <Route path="centros" element={<ComingSoon title="Centros" />} />
                <Route path="tablon" element={<ComingSoon title="Tablón" />} />
            </Route>
            <Route path="*" element={<Navigate to="/" replace />} />
        </Routes>
    );
}

createRoot(document.getElementById('app')).render(
    <QueryClientProvider client={queryClient}>
        <BrowserRouter>
            <AuthProvider>
                <AppRoutes />
            </AuthProvider>
        </BrowserRouter>
    </QueryClientProvider>
);
