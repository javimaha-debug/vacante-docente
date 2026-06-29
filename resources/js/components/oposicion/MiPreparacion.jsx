import { useMemo, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import clsx from 'clsx';
import api from '../../lib/api';
import { useAuth } from '../../hooks/useAuth';
import { useEscapeKey } from '../../hooks/useEscapeKey';
import { useFocusTrap } from '../../hooks/useFocusTrap';
import {
    CUERPO_LABEL,
    StatusBadge,
    SectionTitle,
    TEMA_STATUS,
    useSpecialtyCatalogue,
    especialidadLabel,
} from './shared';

const STATUS_ORDER = ['pendiente', 'en_progreso', 'dominado'];
const STATUS_NEXT = { pendiente: 'en_progreso', en_progreso: 'dominado', dominado: 'pendiente' };

export default function MiPreparacion() {
    const { byCode } = useSpecialtyCatalogue();
    const [activeCode, setActiveCode] = useState(null);

    const { data: espData, isLoading: espLoading } = useQuery({
        queryKey: ['oposicion', 'especialidades'],
        queryFn: async () => (await api.get('/oposicion/especialidades')).data,
    });

    const especialidades = espData?.data ?? [];
    // Default the active specialty to the first one once they load.
    const active = especialidades.find((e) => e.especialidad_code === activeCode) ?? especialidades[0] ?? null;
    const code = active?.especialidad_code ?? null;

    if (espLoading) {
        return <p className="text-sm text-slate-400">Cargando tu preparación…</p>;
    }

    if (especialidades.length === 0) {
        return <EmptyState />;
    }

    return (
        <div className="mx-auto max-w-7xl">
            <div className="grid grid-cols-1 gap-5 lg:grid-cols-[260px_minmax(0,1fr)_300px]">
                <LeftSidebar
                    especialidades={especialidades}
                    activeCode={code}
                    onSelect={setActiveCode}
                    byCode={byCode}
                />
                <MainArea code={code} byCode={byCode} />
                <RightSidebar code={code} active={active} />
            </div>
        </div>
    );
}

/* ------------------------------------------------------------------ */
/* Left sidebar: specialty selector + stats                            */
/* ------------------------------------------------------------------ */

function LeftSidebar({ especialidades, activeCode, onSelect, byCode }) {
    const [adding, setAdding] = useState(false);
    const { data: stats } = useQuery({
        queryKey: ['oposicion', 'stats'],
        queryFn: async () => (await api.get('/oposicion/stats')).data,
    });

    return (
        <aside className="space-y-4">
            <div className="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
                <SectionTitle>En preparación</SectionTitle>
                <ul className="mt-3 space-y-1.5">
                    {especialidades.map((e) => (
                        <li key={e.id}>
                            <button
                                onClick={() => onSelect(e.especialidad_code)}
                                className={clsx(
                                    'flex w-full items-center justify-between gap-2 rounded-xl px-3 py-2 text-left text-sm transition',
                                    e.especialidad_code === activeCode
                                        ? 'bg-brand-600 text-white shadow-sm'
                                        : 'text-slate-600 hover:bg-slate-100'
                                )}
                            >
                                <span className="truncate">{especialidadLabel(e.especialidad_code, byCode)}</span>
                                <span className={clsx('shrink-0 rounded-full px-1.5 py-0.5 text-[10px] font-bold uppercase',
                                    e.especialidad_code === activeCode ? 'bg-white/20' : 'bg-slate-100 text-slate-500')}>
                                    {CUERPO_LABEL[e.cuerpo]}
                                </span>
                            </button>
                        </li>
                    ))}
                </ul>
                <button
                    onClick={() => setAdding(true)}
                    className="mt-3 w-full rounded-xl border border-dashed border-slate-300 px-3 py-2 text-sm font-medium text-slate-500 hover:border-brand-400 hover:text-brand-600"
                >
                    + Añadir especialidad
                </button>
            </div>

            <StatsCard stats={stats} />

            {adding && <AddEspecialidadModal existing={especialidades} onClose={() => setAdding(false)} onAdded={(c) => { onSelect(c); setAdding(false); }} />}
        </aside>
    );
}

function StatsCard({ stats }) {
    const total = stats?.total_temas ?? 0;
    const pct = stats?.pct_dominado ?? 0;
    const racha = stats?.racha_dias ?? 0;
    const horas = Math.round(((stats?.total_minutos ?? 0) / 60) * 10) / 10;

    return (
        <div className="rounded-2xl bg-gradient-to-br from-brand-600 to-brand-700 p-4 text-white shadow-brand">
            <p className="text-xs font-semibold uppercase tracking-wide text-white/70">Tu progreso</p>
            <div className="mt-3 grid grid-cols-2 gap-3">
                <Stat label="Temas" value={total} />
                <Stat label="% dominado" value={`${pct}%`} />
                <Stat label="Racha" value={`${racha} d`} />
                <Stat label="Horas" value={horas} />
            </div>
            <div className="mt-3 h-2 overflow-hidden rounded-full bg-white/20">
                <div className="h-full rounded-full bg-amber-400 transition-all" style={{ width: `${pct}%` }} />
            </div>
        </div>
    );
}

function Stat({ label, value }) {
    return (
        <div>
            <p className="text-xl font-bold leading-tight">{value}</p>
            <p className="text-[11px] text-white/70">{label}</p>
        </div>
    );
}

/* ------------------------------------------------------------------ */
/* Main area: temas grouped by status                                  */
/* ------------------------------------------------------------------ */

function MainArea({ code, byCode }) {
    const qc = useQueryClient();
    const [showSesion, setShowSesion] = useState(false);
    const [showImport, setShowImport] = useState(false);

    const { data, isLoading } = useQuery({
        queryKey: ['oposicion', 'temas', code],
        queryFn: async () => (await api.get('/oposicion/temas', { params: { especialidad: code } })).data,
        enabled: !!code,
    });

    const temas = data?.data ?? [];
    const grouped = useMemo(() => {
        const g = { pendiente: [], en_progreso: [], dominado: [] };
        for (const t of temas) (g[t.status] ?? g.pendiente).push(t);
        return g;
    }, [temas]);

    // Is there an official BOE temario for this specialty?
    const { data: oficial } = useQuery({
        queryKey: ['oposicion', 'temario-oficial', code],
        queryFn: async () => (await api.get('/oposicion/temario-oficial', { params: { especialidad_code: code } })).data,
        enabled: !!code,
    });

    const invalidate = () => {
        qc.invalidateQueries({ queryKey: ['oposicion', 'temas', code] });
        qc.invalidateQueries({ queryKey: ['oposicion', 'stats'] });
    };

    const importOficial = useMutation({
        mutationFn: async () => (await api.post('/oposicion/temas/import-oficial', { especialidad_code: code })).data,
        onSuccess: invalidate,
    });

    return (
        <section className="space-y-4">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <SectionTitle sub={`${temas.length} temas · ${especialidadLabel(code, byCode)}`}>Temario</SectionTitle>
                <div className="flex gap-2">
                    <button
                        onClick={() => setShowImport(true)}
                        className="rounded-lg border border-slate-200 px-3 py-1.5 text-sm font-medium text-slate-600 hover:bg-slate-100"
                    >
                        Importar temario
                    </button>
                    <button
                        onClick={() => setShowSesion(true)}
                        className="rounded-lg bg-brand-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-brand-700"
                    >
                        Registrar sesión de hoy
                    </button>
                </div>
            </div>

            {isLoading ? (
                <p className="text-sm text-slate-400">Cargando temas…</p>
            ) : temas.length === 0 ? (
                <div className="rounded-2xl bg-white p-8 text-center shadow-sm ring-1 ring-slate-200">
                    {oficial?.exists ? (
                        <>
                            <div className="text-3xl">📘</div>
                            <p className="mt-2 text-sm font-medium text-slate-700">
                                Hemos encontrado el temario oficial de {oficial.especialidad_nombre} con {oficial.total_temas} temas.
                            </p>
                            <p className="mt-1 text-sm text-slate-400">¿Quieres importarlo como punto de partida?</p>
                            {oficial.preview?.length > 0 && (
                                <ul className="mx-auto mt-3 max-w-md space-y-1 text-left">
                                    {oficial.preview.map((t) => (
                                        <li key={t.numero} className="truncate text-xs text-slate-500">{t.numero}. {t.titulo}</li>
                                    ))}
                                    <li className="text-xs text-slate-400">…</li>
                                </ul>
                            )}
                            <div className="mt-4 flex flex-wrap justify-center gap-2">
                                <button
                                    onClick={() => importOficial.mutate()}
                                    disabled={importOficial.isPending}
                                    className="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700 disabled:opacity-60"
                                >
                                    {importOficial.isPending ? 'Importando…' : 'Importar temario oficial'}
                                </button>
                                <button
                                    onClick={() => setShowImport(true)}
                                    className="rounded-lg border border-slate-200 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100"
                                >
                                    Crear mi propio temario
                                </button>
                            </div>
                        </>
                    ) : (
                        <>
                            <p className="text-sm font-medium text-slate-600">Aún no tienes temas en esta especialidad.</p>
                            <p className="mt-1 text-sm text-slate-400">Usa «Importar temario» para pegar tu lista de temas.</p>
                        </>
                    )}
                </div>
            ) : (
                <div className="space-y-5">
                    {STATUS_ORDER.map((status) => (
                        <TemaGroup
                            key={status}
                            status={status}
                            temas={grouped[status]}
                            onChanged={invalidate}
                        />
                    ))}
                </div>
            )}

            {showSesion && <SesionModal temas={temas} onClose={() => setShowSesion(false)} onSaved={invalidate} />}
            {showImport && <ImportModal code={code} onClose={() => setShowImport(false)} onSaved={invalidate} />}
        </section>
    );
}

function TemaGroup({ status, temas, onChanged }) {
    const meta = TEMA_STATUS[status];
    if (temas.length === 0) return null;

    return (
        <div>
            <div className="mb-2 flex items-center gap-2">
                <span className={clsx('h-2 w-2 rounded-full', meta.dot)} />
                <h3 className="text-sm font-semibold text-slate-700">{meta.label}</h3>
                <span className="text-xs text-slate-400">{temas.length}</span>
            </div>
            <ul className="space-y-2">
                {temas.map((t) => <TemaRow key={t.id} tema={t} onChanged={onChanged} />)}
            </ul>
        </div>
    );
}

function TemaRow({ tema, onChanged }) {
    const [open, setOpen] = useState(false);
    const [panel, setPanel] = useState(null); // 'esquema' | 'bibliografia' | null
    const [notas, setNotas] = useState(tema.notas ?? '');
    const navigate = useNavigate();

    const update = useMutation({
        mutationFn: async (patch) => (await api.patch(`/oposicion/temas/${tema.id}`, patch)).data,
        onSuccess: onChanged,
    });
    const remove = useMutation({
        mutationFn: async () => (await api.delete(`/oposicion/temas/${tema.id}`)).data,
        onSuccess: onChanged,
    });

    // Official esquema/bibliografía, loaded on demand when a panel opens.
    const { data: oficial } = useQuery({
        queryKey: ['oposicion', 'tema-oficial', tema.id],
        queryFn: async () => (await api.get(`/oposicion/temas/${tema.id}/oficial`)).data,
        enabled: open && tema.tiene_esquema && panel !== null,
        staleTime: 5 * 60 * 1000,
    });

    // Create a temario-scoped conversation for this tema and open the assistant.
    const estudiar = useMutation({
        mutationFn: async () => (await api.post('/ai/conversations', {
            mode: 'chat',
            context_type: 'temario',
            especialidad_code: tema.especialidad_code,
            tema_numero: tema.numero,
            title: `Tema ${tema.numero}: ${tema.titulo}`.slice(0, 80),
        })).data,
        onSuccess: (conv) => navigate(`/dashboard/asistente?c=${conv.id}`),
    });

    const progreso = tema.esquema_progreso ?? [];
    const togglePunto = (idx) => {
        const next = progreso.includes(idx) ? progreso.filter((i) => i !== idx) : [...progreso, idx];
        update.mutate({ esquema_progreso: next });
    };

    return (
        <li className="rounded-xl bg-white p-3 shadow-sm ring-1 ring-slate-200">
            <div className="flex items-center gap-3">
                <span className="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-xs font-bold text-slate-500">
                    {tema.numero}
                </span>
                <button onClick={() => setOpen((v) => !v)} className="min-w-0 flex-1 text-left">
                    <p className="truncate text-sm font-medium text-slate-800">{tema.titulo}</p>
                    <div className="flex flex-wrap items-center gap-1.5">
                        {tema.es_oficial && (
                            <span className="rounded-full bg-blue-50 px-1.5 py-0.5 text-[10px] font-bold text-blue-700">Oficial BOE</span>
                        )}
                        {Number.isFinite(tema.score) && tema.score != null && (
                            <span className="rounded-full bg-brand-50 px-1.5 py-0.5 text-[10px] font-bold text-brand-700">{tema.score} pts</span>
                        )}
                        {tema.last_studied_at && (
                            <span className="text-[11px] text-slate-400">Estudiado {new Date(tema.last_studied_at).toLocaleDateString('es-ES')}</span>
                        )}
                    </div>
                </button>
                <button
                    onClick={() => update.mutate({ status: STATUS_NEXT[tema.status] })}
                    title="Cambiar estado"
                    className="shrink-0"
                >
                    <StatusBadge status={tema.status} />
                </button>
            </div>

            {open && (
                <div className="mt-3 border-t border-slate-100 pt-3">
                    <div className="flex flex-wrap gap-1.5">
                        {STATUS_ORDER.map((s) => (
                            <button
                                key={s}
                                onClick={() => update.mutate({ status: s })}
                                className={clsx('rounded-lg px-2.5 py-1 text-xs font-semibold transition',
                                    tema.status === s ? TEMA_STATUS[s].tone : 'text-slate-500 hover:bg-slate-100')}
                            >
                                {TEMA_STATUS[s].label}
                            </button>
                        ))}
                    </div>

                    {tema.tiene_esquema && (
                        <div className="mt-2 flex flex-wrap gap-1.5">
                            <button onClick={() => setPanel(panel === 'esquema' ? null : 'esquema')} className="rounded-lg bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600 hover:bg-slate-200">
                                Ver esquema
                            </button>
                            <button onClick={() => setPanel(panel === 'bibliografia' ? null : 'bibliografia')} className="rounded-lg bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600 hover:bg-slate-200">
                                Ver bibliografía
                            </button>
                        </div>
                    )}
                    <button
                        onClick={() => estudiar.mutate()}
                        disabled={estudiar.isPending}
                        className="mt-2 rounded-lg bg-brand-600 px-2.5 py-1 text-xs font-semibold text-white hover:bg-brand-700 disabled:opacity-60"
                    >
                        ✨ Estudiar con IA
                    </button>

                    {panel === 'esquema' && <EsquemaPanel esquema={oficial?.esquema} progreso={progreso} onToggle={togglePunto} />}
                    {panel === 'bibliografia' && <BibliografiaPanel bibliografia={oficial?.bibliografia} />}

                    <textarea
                        value={notas}
                        onChange={(e) => setNotas(e.target.value)}
                        onBlur={() => notas !== (tema.notas ?? '') && update.mutate({ notas })}
                        rows={2}
                        placeholder="Notas sobre este tema…"
                        className="mt-2 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-brand-400 focus:ring-brand-400"
                    />
                    <div className="mt-2 flex justify-end">
                        <button onClick={() => remove.mutate()} className="text-xs font-medium text-rose-500 hover:text-rose-700">
                            Eliminar tema
                        </button>
                    </div>
                </div>
            )}
        </li>
    );
}

function EsquemaPanel({ esquema, progreso, onToggle }) {
    if (!esquema) return <p className="mt-2 text-xs text-slate-400">Cargando esquema…</p>;
    if (esquema.length === 0) return <p className="mt-2 text-xs text-slate-400">Este tema aún no tiene esquema generado.</p>;

    const revisados = progreso.length;
    return (
        <div className="mt-2 rounded-lg bg-slate-50 p-3">
            <p className="mb-2 text-[11px] font-semibold text-slate-500">{revisados} de {esquema.length} puntos revisados</p>
            <ol className="space-y-2">
                {esquema.map((p, i) => (
                    <li key={i} className="text-sm">
                        <label className="flex cursor-pointer items-start gap-2">
                            <input type="checkbox" checked={progreso.includes(i)} onChange={() => onToggle(i)} className="mt-0.5 rounded text-brand-600 focus:ring-brand-400" />
                            <span className="font-semibold text-slate-700">{p.punto ?? p}</span>
                        </label>
                        {Array.isArray(p.subpuntos) && p.subpuntos.length > 0 && (
                            <ul className="ml-6 mt-1 list-disc space-y-0.5 text-xs text-slate-500">
                                {p.subpuntos.map((s, j) => <li key={j}>{s}</li>)}
                            </ul>
                        )}
                    </li>
                ))}
            </ol>
        </div>
    );
}

function BibliografiaPanel({ bibliografia }) {
    if (!bibliografia) return <p className="mt-2 text-xs text-slate-400">Cargando bibliografía…</p>;
    if (bibliografia.length === 0) return <p className="mt-2 text-xs text-slate-400">Sin bibliografía generada.</p>;

    return (
        <div className="mt-2 space-y-2">
            {bibliografia.map((b, i) => {
                const link = b.url || `https://www.google.com/search?q=${encodeURIComponent([b.titulo, b.autor].filter(Boolean).join(' '))}`;
                return (
                    <div key={i} className="rounded-lg bg-slate-50 p-2.5 text-sm">
                        <div className="flex items-center gap-2">
                            {b.tipo && <span className="rounded-full bg-slate-200 px-1.5 py-0.5 text-[10px] font-bold uppercase text-slate-600">{b.tipo}</span>}
                            <span className="font-semibold text-slate-700">{b.titulo}</span>
                        </div>
                        <p className="mt-0.5 text-xs text-slate-500">
                            {[b.autor, b.editorial, b.año ?? b['año']].filter(Boolean).join(' · ')}
                        </p>
                        <a href={link} target="_blank" rel="noopener noreferrer" className="mt-1 inline-block text-xs font-medium text-brand-700 hover:text-brand-800">
                            {b.url ? 'Abrir ↗' : 'Buscar en Google ↗'}
                        </a>
                    </div>
                );
            })}
        </div>
    );
}

/* ------------------------------------------------------------------ */
/* Right sidebar: calendar + próximas fechas + streak                  */
/* ------------------------------------------------------------------ */

function RightSidebar({ code, active }) {
    const { user } = useAuth();
    const { data: stats } = useQuery({
        queryKey: ['oposicion', 'stats'],
        queryFn: async () => (await api.get('/oposicion/stats')).data,
    });
    const { data: conv } = useQuery({
        queryKey: ['convocatorias', 'preparacion', active?.cuerpo],
        queryFn: async () => (await api.get('/convocatorias', { params: { cuerpo: active?.cuerpo || undefined } })).data,
    });

    const racha = stats?.racha_dias ?? 0;
    const studiedDays = new Set((stats?.sesiones_30_dias ?? []).map((s) => s.fecha));

    // Upcoming dated convocatorias (next official/estimated date in the future).
    const proximas = (conv?.data ?? [])
        .map((c) => ({ ...c, fecha: c.fecha_oficial || c.fecha_estimada }))
        .filter((c) => c.fecha && c.fecha >= new Date().toISOString().slice(0, 10))
        .sort((a, b) => a.fecha.localeCompare(b.fecha))
        .slice(0, 4);

    return (
        <aside className="space-y-4">
            <div className="rounded-2xl bg-amber-50 p-4 ring-1 ring-amber-500/20">
                <p className="text-sm font-bold text-amber-800">🔥 Llevas {racha} {racha === 1 ? 'día' : 'días'} estudiando</p>
                <p className="mt-0.5 text-xs text-amber-700/80">
                    {racha === 0 ? '¡Registra una sesión hoy para empezar tu racha!' : '¡Sigue así, no rompas la cadena!'}
                </p>
            </div>

            <div className="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
                <SectionTitle sub="Últimos 30 días">Calendario de estudio</SectionTitle>
                <MiniCalendar studiedDays={studiedDays} />
            </div>

            <div className="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
                <SectionTitle>Próximas fechas</SectionTitle>
                {proximas.length === 0 ? (
                    <p className="mt-2 text-xs text-slate-400">No hay fechas próximas para tu especialidad.</p>
                ) : (
                    <ul className="mt-2 space-y-2">
                        {proximas.map((c) => (
                            <li key={c.id} className="flex items-start gap-2 text-sm">
                                <span className="mt-0.5 text-amber-500">📅</span>
                                <div className="min-w-0">
                                    <p className="truncate font-medium text-slate-700">{c.titulo}</p>
                                    <p className="text-xs text-slate-400">{new Date(c.fecha).toLocaleDateString('es-ES')}</p>
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </aside>
    );
}

function MiniCalendar({ studiedDays }) {
    // Render the last 35 days as a 5x7 grid, oldest first.
    const days = [];
    const today = new Date();
    for (let i = 34; i >= 0; i--) {
        const d = new Date(today);
        d.setDate(today.getDate() - i);
        const iso = d.toISOString().slice(0, 10);
        days.push({ iso, studied: studiedDays.has(iso), label: d.getDate() });
    }

    return (
        <div className="mt-3 grid grid-cols-7 gap-1.5">
            {days.map((d) => (
                <div
                    key={d.iso}
                    title={d.iso}
                    className={clsx(
                        'flex aspect-square items-center justify-center rounded-md text-[10px] font-medium',
                        d.studied ? 'bg-brand-600 text-white' : 'bg-slate-100 text-slate-400'
                    )}
                >
                    {d.label}
                </div>
            ))}
        </div>
    );
}

/* ------------------------------------------------------------------ */
/* Modals                                                              */
/* ------------------------------------------------------------------ */

function ModalShell({ title, onClose, children }) {
    useEscapeKey(onClose);
    const trapRef = useFocusTrap();
    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4" onClick={onClose}>
            <div ref={trapRef} role="dialog" aria-modal="true" aria-label={title} className="w-full max-w-md rounded-2xl bg-white p-5 shadow-xl" onClick={(e) => e.stopPropagation()}>
                <div className="flex items-center justify-between">
                    <h3 className="font-heading text-base font-bold text-slate-800">{title}</h3>
                    <button onClick={onClose} className="text-slate-400 hover:text-slate-600">✕</button>
                </div>
                <div className="mt-4">{children}</div>
            </div>
        </div>
    );
}

function SesionModal({ temas, onClose, onSaved }) {
    const [minutos, setMinutos] = useState(60);
    const [selected, setSelected] = useState([]);
    const [notas, setNotas] = useState('');

    const save = useMutation({
        mutationFn: async () => (await api.post('/oposicion/sesiones', {
            minutos: Number(minutos),
            temas_estudiados: selected,
            notas: notas || null,
        })).data,
        onSuccess: () => { onSaved(); onClose(); },
    });

    const toggle = (id) => setSelected((s) => s.includes(id) ? s.filter((x) => x !== id) : [...s, id]);

    return (
        <ModalShell title="Registrar sesión de hoy" onClose={onClose}>
            <label className="block text-sm font-medium text-slate-600">Minutos estudiados</label>
            <input
                type="number" min={1} max={1440} value={minutos}
                onChange={(e) => setMinutos(e.target.value)}
                className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-400 focus:ring-brand-400"
            />

            <p className="mt-3 text-sm font-medium text-slate-600">Temas trabajados</p>
            <div className="mt-1 max-h-40 space-y-1 overflow-y-auto rounded-lg border border-slate-200 p-2">
                {temas.length === 0 && <p className="text-xs text-slate-400">No hay temas todavía.</p>}
                {temas.map((t) => (
                    <label key={t.id} className="flex cursor-pointer items-center gap-2 rounded-md px-2 py-1 text-sm hover:bg-slate-50">
                        <input type="checkbox" checked={selected.includes(t.id)} onChange={() => toggle(t.id)} className="rounded text-brand-600 focus:ring-brand-400" />
                        <span className="truncate">{t.numero}. {t.titulo}</span>
                    </label>
                ))}
            </div>

            <textarea
                value={notas} onChange={(e) => setNotas(e.target.value)} rows={2}
                placeholder="Notas (opcional)…"
                className="mt-3 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-400 focus:ring-brand-400"
            />

            <div className="mt-4 flex justify-end gap-2">
                <button onClick={onClose} className="rounded-lg px-3 py-1.5 text-sm text-slate-600 hover:bg-slate-100">Cancelar</button>
                <button
                    disabled={save.isPending || !minutos}
                    onClick={() => save.mutate()}
                    className="rounded-lg bg-brand-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-brand-700 disabled:opacity-60"
                >
                    {save.isPending ? 'Guardando…' : 'Guardar sesión'}
                </button>
            </div>
        </ModalShell>
    );
}

function ImportModal({ code, onClose, onSaved }) {
    const [text, setText] = useState('');

    const lines = text.split('\n').map((l) => l.trim()).filter(Boolean);

    const save = useMutation({
        mutationFn: async () => (await api.post('/oposicion/temas/bulk', {
            especialidad_code: code,
            temas: lines.map((titulo) => ({ titulo })),
        })).data,
        onSuccess: () => { onSaved(); onClose(); },
    });

    return (
        <ModalShell title="Importar temario" onClose={onClose}>
            <p className="text-sm text-slate-500">Pega tu lista de temas, uno por línea. Se numerarán automáticamente.</p>
            <textarea
                value={text} onChange={(e) => setText(e.target.value)} rows={10}
                placeholder={'El sistema educativo español\nLa programación didáctica\n…'}
                className="mt-2 w-full rounded-lg border border-slate-300 px-3 py-2 font-mono text-sm focus:border-brand-400 focus:ring-brand-400"
            />
            <div className="mt-4 flex items-center justify-between">
                <span className="text-xs text-slate-400">{lines.length} temas detectados</span>
                <div className="flex gap-2">
                    <button onClick={onClose} className="rounded-lg px-3 py-1.5 text-sm text-slate-600 hover:bg-slate-100">Cancelar</button>
                    <button
                        disabled={save.isPending || lines.length === 0}
                        onClick={() => save.mutate()}
                        className="rounded-lg bg-brand-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-brand-700 disabled:opacity-60"
                    >
                        {save.isPending ? 'Importando…' : `Importar ${lines.length} temas`}
                    </button>
                </div>
            </div>
        </ModalShell>
    );
}

function AddEspecialidadModal({ existing, onClose, onAdded }) {
    const { groups } = useSpecialtyCatalogue();
    const [cuerpo, setCuerpo] = useState('maestros');
    const [specialtyCode, setSpecialtyCode] = useState('');
    const [otrosCode, setOtrosCode] = useState('');

    const list = groups[cuerpo] ?? [];
    const existingCodes = new Set(existing.map((e) => `${e.cuerpo}:${e.especialidad_code}`));

    const save = useMutation({
        mutationFn: async () => {
            const code = cuerpo === 'otros' ? otrosCode.trim() : specialtyCode;
            return (await api.post('/oposicion/especialidades', { especialidad_code: code, cuerpo })).data;
        },
        onSuccess: (d) => onAdded(d.especialidad_code),
    });

    const chosenCode = cuerpo === 'otros' ? otrosCode.trim() : specialtyCode;
    const dup = chosenCode && existingCodes.has(`${cuerpo}:${chosenCode}`);

    return (
        <ModalShell title="Añadir especialidad" onClose={onClose}>
            <label className="block text-sm font-medium text-slate-600">Cuerpo</label>
            <select
                value={cuerpo}
                onChange={(e) => { setCuerpo(e.target.value); setSpecialtyCode(''); }}
                className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-400 focus:ring-brand-400"
            >
                {Object.entries(CUERPO_LABEL).map(([v, l]) => <option key={v} value={v}>{l}</option>)}
            </select>

            <label className="mt-3 block text-sm font-medium text-slate-600">Especialidad</label>
            {cuerpo === 'otros' ? (
                <input
                    value={otrosCode} onChange={(e) => setOtrosCode(e.target.value)}
                    placeholder="Código o nombre de la especialidad"
                    className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-400 focus:ring-brand-400"
                />
            ) : (
                <select
                    value={specialtyCode} onChange={(e) => setSpecialtyCode(e.target.value)}
                    className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-400 focus:ring-brand-400"
                >
                    <option value="">Selecciona…</option>
                    {list.map((s) => <option key={s.id} value={s.code}>{s.code} · {s.name}</option>)}
                </select>
            )}

            {dup && <p className="mt-2 text-xs text-amber-600">Ya estás preparando esta especialidad.</p>}

            <div className="mt-4 flex justify-end gap-2">
                <button onClick={onClose} className="rounded-lg px-3 py-1.5 text-sm text-slate-600 hover:bg-slate-100">Cancelar</button>
                <button
                    disabled={save.isPending || !chosenCode || dup}
                    onClick={() => save.mutate()}
                    className="rounded-lg bg-brand-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-brand-700 disabled:opacity-60"
                >
                    {save.isPending ? 'Añadiendo…' : 'Añadir'}
                </button>
            </div>
        </ModalShell>
    );
}

/* ------------------------------------------------------------------ */
/* Empty state                                                         */
/* ------------------------------------------------------------------ */

function EmptyState() {
    const qc = useQueryClient();
    const { groups } = useSpecialtyCatalogue();
    const [cuerpo, setCuerpo] = useState('maestros');
    const [specialtyCode, setSpecialtyCode] = useState('');
    const [otrosCode, setOtrosCode] = useState('');

    const list = groups[cuerpo] ?? [];

    const save = useMutation({
        mutationFn: async () => {
            const code = cuerpo === 'otros' ? otrosCode.trim() : specialtyCode;
            return (await api.post('/oposicion/especialidades', { especialidad_code: code, cuerpo })).data;
        },
        onSuccess: () => qc.invalidateQueries({ queryKey: ['oposicion', 'especialidades'] }),
    });

    const chosenCode = cuerpo === 'otros' ? otrosCode.trim() : specialtyCode;

    return (
        <div className="mx-auto max-w-lg">
            <div className="rounded-2xl bg-white p-8 text-center shadow-sm ring-1 ring-slate-200">
                <div className="text-4xl">🎓</div>
                <h1 className="mt-3 font-heading text-xl font-bold text-slate-800">¿A qué oposición te presentas?</h1>
                <p className="mt-1 text-sm text-slate-500">Elige tu cuerpo y especialidad para empezar a organizar tu temario.</p>

                <div className="mt-6 space-y-3 text-left">
                    <div>
                        <label className="block text-sm font-medium text-slate-600">Cuerpo</label>
                        <select
                            value={cuerpo} onChange={(e) => { setCuerpo(e.target.value); setSpecialtyCode(''); }}
                            className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-400 focus:ring-brand-400"
                        >
                            {Object.entries(CUERPO_LABEL).map(([v, l]) => <option key={v} value={v}>{l}</option>)}
                        </select>
                    </div>
                    <div>
                        <label className="block text-sm font-medium text-slate-600">Especialidad</label>
                        {cuerpo === 'otros' ? (
                            <input
                                value={otrosCode} onChange={(e) => setOtrosCode(e.target.value)}
                                placeholder="Código o nombre de la especialidad"
                                className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-400 focus:ring-brand-400"
                            />
                        ) : (
                            <select
                                value={specialtyCode} onChange={(e) => setSpecialtyCode(e.target.value)}
                                className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-400 focus:ring-brand-400"
                            >
                                <option value="">Selecciona…</option>
                                {list.map((s) => <option key={s.id} value={s.code}>{s.code} · {s.name}</option>)}
                            </select>
                        )}
                    </div>
                </div>

                <button
                    disabled={save.isPending || !chosenCode}
                    onClick={() => save.mutate()}
                    className="mt-6 w-full rounded-xl bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-700 disabled:opacity-60"
                >
                    {save.isPending ? 'Empezando…' : 'Empezar mi preparación'}
                </button>
            </div>
        </div>
    );
}
