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
import ListBoard from './components/ListBoard';
import ExportPanel from './components/ExportPanel';
import ProcesoSelector from './components/ProcesoSelector';
import CambiosBanner from './components/CambiosBanner';

import LoginPage from './components/auth/LoginPage';
import Dashboard from './components/dashboard/Dashboard';
import DashboardHome from './components/dashboard/DashboardHome';
import UserProfile from './components/dashboard/UserProfile';
import MisEspecialidades from './components/dashboard/MisEspecialidades';
import PlanesPage from './components/dashboard/PlanesPage';
import MiPosicion from './components/dashboard/MiPosicion';
import CalendarioPage from './components/dashboard/CalendarioPage';
import MiPreparacion from './components/oposicion/MiPreparacion';
import Normativa from './components/oposicion/Normativa';
import Convocatorias from './components/oposicion/Convocatorias';
import Asistente from './components/oposicion/Asistente';
import MisDocumentos from './components/dashboard/MisDocumentos';

import AdminLayout from './components/superadmin/AdminLayout';
import AdminDashboard from './components/superadmin/AdminDashboard';
import AdminUsuarios from './components/superadmin/AdminUsuarios';
import AdminUsuarioDetalle from './components/superadmin/AdminUsuarioDetalle';
import AdminSuscripciones from './components/superadmin/AdminSuscripciones';
import SuperAdminImportaciones from './components/superadmin/AdminImportaciones';
import AdminMetricas from './components/superadmin/AdminMetricas';
import AdminSistema from './components/superadmin/AdminSistema';
import AdminMonitorDocs from './components/superadmin/AdminMonitorDocs';
import AdminCalendario from './components/superadmin/AdminCalendario';
import AdminAiUsage from './components/superadmin/AdminAiUsage';
import AdminConvocatorias from './components/superadmin/AdminConvocatorias';
import AdminNormativa from './components/superadmin/AdminNormativa';
import AdminTemarios from './components/superadmin/AdminTemarios';
import AdminRecursos from './components/superadmin/AdminRecursos';
import CentrosList from './components/centros/CentrosList';
import CentroDetail from './components/centros/CentroDetail';
import TablonList from './components/tablon/TablonList';
import TablonForm from './components/tablon/TablonForm';
import MisAnuncios from './components/tablon/MisAnuncios';
import TablonResponder from './components/tablon/TablonResponder';
import RecursosPage from './components/dashboard/RecursosPage';

import { useUserList } from './hooks/useUserList';
import { useVacancies } from './hooks/useVacancies';
import { useCambios } from './hooks/useCambios';
import { useDistances } from './hooks/useDistances';
import { useListSync } from './hooks/useListSync';
import { AuthContext, useAuth, useProvideAuth } from './hooks/useAuth';
import { useFeatures } from './hooks/useFeatures';
import { getSpecialtyId, setSpecialtyId, clearSpecialty, getProcesoId, setProcesoId, getStoredFilters, setStoredFilters } from './lib/session';
import { DEFAULT_FILTERS, matchesFilters, statusEnabled, sortVacancies, countActiveFilters } from './lib/vacancyFilters';

const queryClient = new QueryClient({
    defaultOptions: { queries: { refetchOnWindowFocus: false, retry: 1 } },
});

function Organizer({ specialtyId, onChangeSpecialty, initialView = 'kanban', focused = false }) {
    const [filters, setFilters] = useState(() => ({ ...DEFAULT_FILTERS, ...(getStoredFilters() ?? {}) }));
    const [viewMode, setViewMode] = useState(initialView);

    // Persist the filter set across navigation/reloads.
    useEffect(() => {
        setStoredFilters(filters);
    }, [filters]);

    const clearFilters = useCallback(() => setFilters({ ...DEFAULT_FILTERS }), []);
    const showDiscarded = statusEnabled(filters, 'discarded');
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
    const { can } = useFeatures();
    // Free plan caps the saved list at 30 vacancies; paid plans are unlimited.
    const FREE_LIST_LIMIT = 30;
    const canUnlimited = can('vacantes_ilimitadas');
    const canExport = can('exportar_ovidoc');
    const [limitHit, setLimitHit] = useState(false);

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

    const cambios = useCambios(procesoId);

    // Load every page so ALL plazas are available (filters + counter are
    // client-side, so partial pages would hide matches / undercount). With
    // per_page=1000 most specialties fit in one page; larger ones load the rest.
    useEffect(() => {
        if (hasNextPage && !isFetchingNextPage) {
            fetchNextPage();
        }
    }, [hasNextPage, isFetchingNextPage, fetchNextPage]);

    // Preference lookup + derived columns.
    const prefByVacancy = useMemo(() => {
        const map = new Map();
        for (const p of preferences) map.set(p.vacancy_id, p);
        return map;
    }, [preferences]);

    const selected = useMemo(() => preferences.filter((p) => p.status === 'selected'), [preferences]);
    const revisar = useMemo(() => preferences.filter((p) => p.status === 'revisar'), [preferences]);
    const discarded = useMemo(() => preferences.filter((p) => p.status === 'discarded'), [preferences]);
    const neutral = useMemo(
        () => vacancies.filter((v) => (prefByVacancy.get(v.id)?.status ?? 'neutral') === 'neutral'),
        [vacancies, prefByVacancy]
    );

    // All explorer filters are applied client-side, combined with AND, so the
    // results and the counter stay in sync. Status (estado) decides which groups
    // are shown; the field filters apply to every group.
    const neutralSorted = useMemo(() => {
        if (!statusEnabled(filters, 'neutral')) return [];
        const list = neutral.filter((v) => matchesFilters(v, filters));
        return sortVacancies(list, filters.sort);
    }, [neutral, filters]);

    // Field filters also narrow "Mi lista" / "A revisar" / "Descartadas" for
    // display (full sets are kept for counts, sync, export and reorder).
    const selectedShown = useMemo(
        () => (statusEnabled(filters, 'selected') ? selected.filter((p) => matchesFilters(p.vacancy, filters)) : []),
        [selected, filters]
    );
    const revisarShown = useMemo(
        () => (statusEnabled(filters, 'revisar') ? revisar.filter((p) => matchesFilters(p.vacancy, filters)) : []),
        [revisar, filters]
    );
    const discardedShown = useMemo(
        () => (statusEnabled(filters, 'discarded') ? discarded.filter((p) => matchesFilters(p.vacancy, filters)) : []),
        [discarded, filters]
    );

    // How many vacancies match the active filters across all shown statuses.
    const matchingCount = neutralSorted.length + selectedShown.length + revisarShown.length + discardedShown.length;

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

    // Preload the home address from the authenticated user's profile the first
    // time the explorer opens with an empty list, so distances work without
    // re-entering the address.
    const homePreloadedRef = useRef(false);
    useEffect(() => {
        if (homePreloadedRef.current || !isAuthenticated || !listId || !list) return;
        if (list.has_home) {
            homePreloadedRef.current = true;
            return;
        }
        const lat = user?.lat_origen;
        const lng = user?.lng_origen;
        if (lat != null && lng != null) {
            // Profile has verified coordinates: use them directly.
            homePreloadedRef.current = true;
            updateAddress.mutate({
                home_address: user?.direccion_origen ?? '',
                home_lat: Number(lat),
                home_lng: Number(lng),
            });
        } else if (user?.direccion_origen) {
            // Profile has an address but no stored coordinates: geocode the text
            // so the origin still preloads and distances can be computed.
            homePreloadedRef.current = true;
            geocode.mutate(user.direccion_origen);
        }
    }, [isAuthenticated, listId, list, user, updateAddress, geocode]);

    const handleStatusChange = useCallback(
        (vacancyId, status) => {
            const existing = prefByVacancy.get(vacancyId);
            // Free plan: cap the number of selected vacancies. Don't silently
            // drop the action — surface the limit so the user can upgrade.
            if (
                status === 'selected'
                && !canUnlimited
                && existing?.status !== 'selected'
                && selected.length >= FREE_LIST_LIMIT
            ) {
                setLimitHit(true);
                return;
            }
            const position = status === 'selected' ? selected.length + 1 : 0;
            persist([{ vacancy_id: vacancyId, status, position, notes: existing?.notes ?? null }]);
        },
        [prefByVacancy, selected.length, persist, canUnlimited]
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

    const counts = { total, matching: matchingCount, selected: selected.length, revisar: revisar.length, discarded: discarded.length };

    const sidebar = (
        <div className="space-y-5">
            <div className="rounded-xl bg-white p-3 shadow-sm ring-1 ring-slate-200">
                <ProcesoSelector
                    value={procesoId}
                    onChange={handleProcesoChange}
                    colectivoBody={user?.colectivo?.body ?? null}
                />
                {isAuthenticated && (
                    <p className="mt-2 text-xs font-medium text-slate-400" role="status" aria-live="polite">
                        {syncStatus === 'saving' && <span className="text-amber-600">Guardando…</span>}
                        {syncStatus === 'saved' && <span className="text-green-600">Guardado ✓</span>}
                        {syncStatus === 'error' && <span className="text-rose-600">No se pudo guardar — reintenta</span>}
                        {syncStatus === 'idle' && <span>Tu lista se guarda en tu cuenta</span>}
                    </p>
                )}
            </div>
            <HomeAddressPanel
                list={list}
                geocode={geocode}
                distances={distances}
                // Compute travel times for every loaded vacancy so distance is
                // available while browsing candidates, not just the selected.
                vacancyIds={vacancies.map((v) => v.id)}
            />
            <FiltersPanel
                filters={filters}
                setFilters={setFilters}
                counts={counts}
                onClear={clearFilters}
            />
        </div>
    );

    const home = list?.home_lat != null && list?.home_lng != null
        ? { lat: list.home_lat, lng: list.home_lng }
        : null;

    // Focused "Mi Lista" page: only the prioritised selection, no explorer.
    const focusedSidebar = (
        <div className="space-y-5">
            <div className="rounded-xl bg-white p-3 shadow-sm ring-1 ring-slate-200">
                <ProcesoSelector
                    value={procesoId}
                    onChange={handleProcesoChange}
                    colectivoBody={user?.colectivo?.body ?? null}
                />
            </div>
            <HomeAddressPanel
                list={list}
                geocode={geocode}
                distances={distances}
                vacancyIds={selected.map((p) => p.vacancy_id)}
            />
        </div>
    );

    const focusedContent = (
        <div className="scroll-thin h-full space-y-3 overflow-y-auto pr-1">
            <div className="flex items-baseline justify-between">
                <h1 className="text-lg font-bold text-slate-800">Mi lista priorizada</h1>
                <span className="text-sm text-slate-400">{selected.length} vacantes</span>
            </div>
            <p className="text-xs text-slate-500">
                Arrastra <span className="font-semibold">⠿</span> para ordenar tus preferencias. Pulsa «Exportar lista» arriba para descargarla.
            </p>
            <SortableRows
                items={selected}
                home={home}
                onReorder={handleReorder}
                onStatusChange={handleStatusChange}
                onNotesChange={handleNotesChange}
                emptyLabel="Aún no has añadido vacantes. Ve a «Explorador Vacantes» y pulsa ✓ Mi lista."
            />
        </div>
    );

    return (
        <Layout
            specialty={list?.specialty}
            vacancyCount={total}
            onChangeSpecialty={onChangeSpecialty}
            onExport={() => setExporting(true)}
            exportLocked={!canExport}
            viewMode={focused ? undefined : viewMode}
            onViewModeChange={focused ? undefined : setViewMode}
            sidebar={focused ? focusedSidebar : sidebar}
            filterCount={focused ? 0 : countActiveFilters(filters)}
        >
            {focused ? (
                focusedContent
            ) : isLoading ? (
                <div role="status" className="flex h-full items-center justify-center text-sm text-slate-400">Cargando vacantes…</div>
            ) : (
                <div className="flex h-full flex-col">
                    <CambiosBanner cambios={cambios} />
                    <div className="min-h-0 flex-1">
                        {viewMode === 'kanban' ? (
                            <KanbanBoard
                                neutral={neutralSorted}
                                selected={selectedShown}
                                revisar={revisarShown}
                                discarded={discardedShown}
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
                                selected={selectedShown}
                                revisar={revisarShown}
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
                    </div>
                </div>
            )}

            {exporting && (
                <ExportPanel selected={selected} specialty={list?.specialty} onClose={() => setExporting(false)} />
            )}

            {limitHit && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4" onClick={() => setLimitHit(false)}>
                    <div onClick={(e) => e.stopPropagation()}>
                        <UpgradePrompt
                            title={`Límite de ${FREE_LIST_LIMIT} vacantes`}
                            message={`El plan Gratis permite guardar hasta ${FREE_LIST_LIMIT} vacantes. Mejora tu plan para una lista ilimitada.`}
                        />
                        <button onClick={() => setLimitHit(false)} className="mx-auto mt-3 block text-sm text-slate-500 hover:text-slate-700">Cerrar</button>
                    </div>
                </div>
            )}
        </Layout>
    );
}

// Powerful working list: one compact row per vacancy in a single drag surface,
// so candidates can be dragged up into "Mi lista" and reordered. Distance is
// shown inline on every row.
function ListView({ selected, revisar, neutral, home, onStatusChange, onNotesChange, onReorder, hasMore, onLoadMore, isLoadingMore }) {
    return (
        <ListBoard
            selected={selected}
            revisar={revisar}
            neutral={neutral}
            home={home}
            onStatusChange={onStatusChange}
            onNotesChange={onNotesChange}
            onReorder={onReorder}
            hasMore={hasMore}
            onLoadMore={onLoadMore}
            isLoadingMore={isLoadingMore}
        />
    );
}

// Existing vacancy explorer flow (specialty selection → kanban/list organizer).
// Rendered inside the dashboard at /dashboard/vacantes and /dashboard/lista.
function VacancyExplorer({ initialView = 'kanban', focused = false }) {
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
                focused={focused}
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

// Gate for the super-admin panel: must be authenticated AND admin/superadmin.
function RequireSuperAdmin({ children }) {
    const { isAuthenticated, loading, user } = useAuth();
    if (loading) return <Spinner />;
    if (!isAuthenticated) return <Navigate to="/" replace />;
    if (!user?.is_admin && !user?.is_superadmin) return <Navigate to="/dashboard" replace />;
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
                <Route path="planes" element={<PlanesPage />} />
                <Route path="vacantes" element={<VacancyExplorer initialView="kanban" />} />
                <Route path="lista" element={<VacancyExplorer initialView="list" focused />} />
                <Route path="mi-posicion" element={<MiPosicion />} />
                <Route path="centros" element={<CentrosList />} />
                <Route path="centros/:codigo" element={<CentroDetail />} />
                <Route path="tablon" element={<TablonList />} />
                <Route path="tablon/nuevo" element={<TablonForm />} />
                <Route path="tablon/mis-anuncios" element={<MisAnuncios />} />
                <Route path="calendario" element={<CalendarioPage />} />
                {/* Modo Oposición sections. */}
                <Route path="oposicion" element={<MiPreparacion />} />
                <Route path="mis-documentos" element={<MisDocumentos />} />
                <Route path="normativa" element={<Normativa />} />
                <Route path="convocatorias" element={<Convocatorias />} />
                <Route path="asistente" element={<Asistente />} />
                {/* Sections not yet built (docente). */}
                <Route path="recursos" element={<RecursosPage />} />
            </Route>

            {/* Super-admin panel: separate dark SPA at /superadmin/*. */}
            <Route
                path="/superadmin"
                element={
                    <RequireSuperAdmin>
                        <AdminLayout />
                    </RequireSuperAdmin>
                }
            >
                <Route index element={<AdminDashboard />} />
                <Route path="usuarios" element={<AdminUsuarios />} />
                <Route path="usuarios/:id" element={<AdminUsuarioDetalle />} />
                <Route path="suscripciones" element={<AdminSuscripciones />} />
                <Route path="convocatorias" element={<AdminConvocatorias />} />
                <Route path="normativa" element={<AdminNormativa />} />
                <Route path="temarios" element={<AdminTemarios />} />
                <Route path="recursos" element={<AdminRecursos />} />
                <Route path="importaciones" element={<SuperAdminImportaciones />} />
                <Route path="monitor-docs" element={<AdminMonitorDocs />} />
                <Route path="calendario" element={<AdminCalendario />} />
                <Route path="ia-usage" element={<AdminAiUsage />} />
                <Route path="metricas" element={<AdminMetricas />} />
                <Route path="sistema" element={<AdminSistema />} />
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
