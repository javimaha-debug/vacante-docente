import '../css/app.css';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { createRoot } from 'react-dom/client';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';

import Layout from './components/Layout';
import SpecialtySelector from './components/SpecialtySelector';
import FiltersPanel from './components/FiltersPanel';
import HomeAddressPanel from './components/HomeAddressPanel';
import KanbanBoard from './components/KanbanBoard';
import SortableRows from './components/SortableRows';
import VacancyRow from './components/VacancyRow';
import ExportPanel from './components/ExportPanel';
import ProcesoSelector from './components/ProcesoSelector';

import LoginPage from './components/auth/LoginPage';
import Dashboard from './components/dashboard/Dashboard';
import DashboardHome from './components/dashboard/DashboardHome';
import UserProfile from './components/dashboard/UserProfile';
import MisEspecialidades from './components/dashboard/MisEspecialidades';
import CentrosList from './components/centros/CentrosList';
import CentroDetail from './components/centros/CentroDetail';
import TablonList from './components/tablon/TablonList';
import TablonForm from './components/tablon/TablonForm';
import MisAnuncios from './components/tablon/MisAnuncios';
import TablonResponder from './components/tablon/TablonResponder';

import { useUserList } from './hooks/useUserList';
import { useVacancies } from './hooks/useVacancies';
import { useDistances } from './hooks/useDistances';
import { useListSync } from './hooks/useListSync';
import { AuthContext, useAuth, useProvideAuth } from './hooks/useAuth';
import { getSpecialtyId, setSpecialtyId, clearSpecialty, getProcesoId, setProcesoId } from './lib/session';

const queryClient = new QueryClient({
    defaultOptions: { queries: { refetchOnWindowFocus: false, retry: 1 } },
});

const EMPTY_FILTERS = {
    search: '',
    provincia: '',
    tiposCentro: [],
    tags: [],
    reqLing: false,
    itinerante: false,
    maxDistance: '',
    sort: 'priority',
};

function Organizer({ specialtyId, onChangeSpecialty, initialView = 'kanban' }) {
    const [filters, setFilters] = useState(EMPTY_FILTERS);
    const [showDiscarded, setShowDiscarded] = useState(false);
    const [viewMode, setViewMode] = useState(initialView);
    const [exporting, setExporting] = useState(false);
    const [procesoId, setProcesoIdState] = useState(getProcesoId);
    const notesTimers = useRef({});
    const hydratedRef = useRef(false);

    // Persist the chosen proceso so the explorer remembers it across reloads.
    const handleProcesoChange = useCallback((id) => {
        setProcesoIdState(id);
        setProcesoId(id);
    }, []);

    const { isAuthenticated, user } = useAuth();

    const { list, listId, preferences, savePreferences, updateAddress, geocode } = useUserList(specialtyId);
    const distances = useDistances(listId);
    const { vacancies, total, isLoading, hasNextPage, fetchNextPage, isFetchingNextPage } = useVacancies(
        specialtyId,
        filters,
        procesoId
    );

    const { status: syncStatus, savedItems, isHydrating, sync } = useListSync({
        specialtyId,
        procesoId,
        enabled: isAuthenticated,
    });

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

    // Candidate list with the powerful filters applied client-side: a maximum
    // driving distance and the chosen ordering. (Province / type / req. ling. /
    // itinerant / search are already applied server-side.)
    const neutralSorted = useMemo(() => {
        const drivingKm = (v) => v.distances?.driving_ida?.distance_km ?? v.distances?.driving_tornada?.distance_km ?? null;

        let list = neutral;
        const max = parseFloat(filters.maxDistance);
        if (!Number.isNaN(max)) {
            list = list.filter((v) => {
                const km = drivingKm(v);
                return km != null && km <= max;
            });
        }

        const sort = filters.sort ?? 'priority';
        if (sort === 'priority') return list;

        const cmp = {
            distance: (a, b) => (drivingKm(a) ?? Infinity) - (drivingKm(b) ?? Infinity),
            num: (a, b) => (a.num ?? 0) - (b.num ?? 0),
            localidad: (a, b) => (a.localidad ?? '').localeCompare(b.localidad ?? ''),
            centro: (a, b) => (a.centro_nombre ?? '').localeCompare(b.centro_nombre ?? ''),
        }[sort];

        return cmp ? [...list].sort(cmp) : list;
    }, [neutral, filters.maxDistance, filters.sort]);

    const persist = useCallback(
        (rows) => savePreferences.mutate(rows),
        [savePreferences]
    );

    // Logged-in users: hydrate the kanban once from the account-saved list,
    // then keep the account in sync as the selection changes (debounced).
    useEffect(() => {
        if (!isAuthenticated) {
            hydratedRef.current = true;
            return;
        }
        if (hydratedRef.current || isHydrating || !listId) return;

        if (savedItems.length && selected.length === 0) {
            persist(
                savedItems.map((it, i) => ({
                    vacancy_id: it.id,
                    status: 'selected',
                    position: it.position ?? i + 1,
                    notes: it.notes ?? null,
                }))
            );
        }
        hydratedRef.current = true;
    }, [isAuthenticated, isHydrating, savedItems, selected.length, listId, persist]);

    useEffect(() => {
        if (!isAuthenticated || !hydratedRef.current) return;
        sync(
            selected.map((p, i) => ({
                vacancy_id: p.vacancy_id,
                position: p.position ?? i + 1,
                status: 'selected',
                notes: p.notes ?? null,
            }))
        );
    }, [selected, isAuthenticated, sync]);

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
            <div className="rounded-xl bg-white p-3 shadow-sm ring-1 ring-slate-200">
                <ProcesoSelector
                    value={procesoId}
                    onChange={handleProcesoChange}
                    colectivoBody={user?.colectivo?.body ?? null}
                />
                {isAuthenticated && (
                    <p className="mt-2 text-xs font-medium text-slate-400">
                        {syncStatus === 'saving' && <span className="text-amber-600">Guardando…</span>}
                        {syncStatus === 'saved' && <span className="text-green-600">Guardado ✓</span>}
                        {syncStatus === 'idle' && <span>Tu lista se guarda en tu cuenta</span>}
                    </p>
                )}
            </div>
            <HomeAddressPanel
                list={list}
                geocode={geocode}
                distances={distances}
                vacancyIds={vacancies.map((v) => v.id)}
            />
            <FiltersPanel
                filters={filters}
                setFilters={setFilters}
                counts={counts}
                showDiscarded={showDiscarded}
                setShowDiscarded={setShowDiscarded}
            />
        </div>
    );

    const home = list?.home_lat != null && list?.home_lng != null
        ? { lat: list.home_lat, lng: list.home_lng }
        : null;

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
                    neutral={neutralSorted}
                    selected={selected}
                    discarded={discarded}
                    home={home}
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
                    neutral={neutralSorted}
                    home={home}
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

// Powerful working list: one compact row per vacancy, with the prioritised
// list (drag-and-drop to move a vacancy up/down) on top and the filtered/sorted
// candidates below. Distance is shown inline on every row.
function ListView({ selected, neutral, home, onStatusChange, onNotesChange, onReorder, hasMore, onLoadMore, isLoadingMore }) {
    return (
        <div className="scroll-thin mx-auto h-full max-w-4xl space-y-6 overflow-y-auto pr-1">
            <section>
                <h2 className="mb-2 text-sm font-bold text-slate-700">
                    Mi lista priorizada <span className="text-slate-400">({selected.length})</span>
                    <span className="ml-2 text-xs font-normal text-slate-400">arrastra ⠿ para ordenar</span>
                </h2>
                <SortableRows
                    items={selected}
                    home={home}
                    onReorder={onReorder}
                    onStatusChange={onStatusChange}
                    onNotesChange={onNotesChange}
                />
            </section>

            <section>
                <h2 className="mb-2 text-sm font-bold text-slate-700">
                    Vacantes <span className="text-slate-400">({neutral.length})</span>
                </h2>
                <div className="space-y-1.5">
                    {neutral.map((v) => (
                        <VacancyRow
                            key={v.id}
                            vacancy={v}
                            status="neutral"
                            home={home}
                            onStatusChange={(status) => onStatusChange(v.id, status)}
                        />
                    ))}
                </div>
                {neutral.length === 0 && (
                    <p className="rounded-lg border border-dashed border-slate-200 px-3 py-6 text-center text-xs text-slate-400">
                        No hay vacantes con estos filtros.
                    </p>
                )}
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
            {/* Signed reply link from the tablón contact email. */}
            <Route path="/tablon/responder/:contacto" element={<TablonResponder />} />
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
                <Route path="centros" element={<CentrosList />} />
                <Route path="centros/:codigo" element={<CentroDetail />} />
                <Route path="tablon" element={<TablonList />} />
                <Route path="tablon/nuevo" element={<TablonForm />} />
                <Route path="tablon/mis-anuncios" element={<MisAnuncios />} />
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
