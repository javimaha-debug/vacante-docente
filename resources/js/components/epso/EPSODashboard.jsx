import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import api from '../../lib/api';

const NIVELES = [
    { value: 'ast', label: 'AST', desc: 'Asistente', color: 'brand' },
    { value: 'ad', label: 'AD', desc: 'Administrador', color: 'indigo' },
];

const TIPOS_BASE = [
    { tipo: 'verbal',    label: 'Verbal',    icon: '📝', desc: 'Comprensión de textos y relaciones lógicas' },
    { tipo: 'numerico',  label: 'Numérico',  icon: '📊', desc: 'Razonamiento con datos numéricos y tablas' },
    { tipo: 'abstracto', label: 'Abstracto', icon: '🔷', desc: 'Patrones, secuencias y analogías lógicas' },
];

const CONFIG_NIVEL = {
    ast: {
        verbal:    { preguntas: 4, minutos: 7 },
        numerico:  { preguntas: 3, minutos: 10 },
        abstracto: { preguntas: 2, minutos: 6 },
    },
    ad: {
        verbal:    { preguntas: 5, minutos: 10 },
        numerico:  { preguntas: 4, minutos: 12 },
        abstracto: { preguntas: 3, minutos: 8 },
        sjt:       { preguntas: 4, minutos: 15 },
    },
};

function NivelSelector({ nivel, onChange }) {
    return (
        <div className="flex gap-2 p-1 bg-gray-100 dark:bg-gray-800 rounded-xl w-fit mx-auto">
            {NIVELES.map((n) => (
                <button
                    key={n.value}
                    onClick={() => onChange(n.value)}
                    className={`px-5 py-2 rounded-lg text-sm font-semibold transition-all ${
                        nivel === n.value
                            ? 'bg-white dark:bg-gray-700 text-brand-700 dark:text-brand-400 shadow-sm'
                            : 'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300'
                    }`}
                >
                    <span className="block font-bold">{n.label}</span>
                    <span className="block text-xs font-normal opacity-70">{n.desc}</span>
                </button>
            ))}
        </div>
    );
}

function ScoreBar({ pct }) {
    const color = pct >= 80 ? 'bg-green-500' : pct >= 60 ? 'bg-amber-500' : 'bg-red-500';
    return (
        <div className="w-full bg-gray-100 dark:bg-gray-700 rounded-full h-1.5 mt-2">
            <div className={`${color} h-1.5 rounded-full transition-all duration-700`} style={{ width: `${pct}%` }} />
        </div>
    );
}

function TipoCard({ tipo, label, icon, desc, preguntas, minutos, prog, nivel, isNew }) {
    const navigate = useNavigate();
    const pct = prog?.total ? Math.round((prog.correctas / prog.total) * 100) : null;
    const scoreMedio = prog?.score_medio ?? null;

    return (
        <button
            onClick={() => navigate(`/dashboard/epso/test/${tipo}?nivel=${nivel}`)}
            className="w-full flex items-center gap-4 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl p-5 text-left hover:border-brand-400 hover:shadow-md transition-all group"
        >
            <span className="text-3xl flex-shrink-0">{icon}</span>
            <div className="flex-1 min-w-0">
                <div className="flex items-baseline gap-2 flex-wrap">
                    <span className="text-lg font-semibold text-gray-900 dark:text-gray-100 group-hover:text-brand-600 dark:group-hover:text-brand-400 transition-colors">
                        {label}
                    </span>
                    {isNew && (
                        <span className="text-[10px] font-bold uppercase tracking-wide bg-indigo-100 dark:bg-indigo-900/40 text-indigo-700 dark:text-indigo-300 px-1.5 py-0.5 rounded-full">
                            Solo AD
                        </span>
                    )}
                    <span className="text-sm text-gray-400 dark:text-gray-500">
                        {preguntas}Q · {minutos} min
                    </span>
                    {scoreMedio != null && (
                        <span className={`ml-auto text-sm font-bold tabular-nums ${scoreMedio >= 80 ? 'text-green-600 dark:text-green-400' : scoreMedio >= 60 ? 'text-amber-600 dark:text-amber-400' : 'text-red-600 dark:text-red-400'}`}>
                            {scoreMedio}%
                        </span>
                    )}
                </div>
                <p className="text-sm text-gray-500 dark:text-gray-400 mt-0.5">{desc}</p>
                {pct != null && <ScoreBar pct={pct} />}
                {prog?.num_sesiones > 0 && (
                    <p className="text-xs text-gray-400 dark:text-gray-500 mt-1">
                        {prog.num_sesiones} sesión{prog.num_sesiones !== 1 ? 'es' : ''} · {prog.correctas}/{prog.total} correctas
                    </p>
                )}
            </div>
            <svg className="w-5 h-5 text-gray-400 group-hover:text-brand-500 transition-colors flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
            </svg>
        </button>
    );
}

function Historial({ sesiones }) {
    if (!sesiones?.length) return null;
    const TIPO_ICON = { verbal: '📝', numerico: '📊', abstracto: '🔷', sjt: '🧭' };
    return (
        <div>
            <h2 className="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-3">
                Últimas sesiones
            </h2>
            <div className="space-y-2">
                {sesiones.slice(0, 5).map(s => {
                    const pct = s.preguntas_respondidas ? Math.round((s.correctas / s.preguntas_respondidas) * 100) : 0;
                    const fecha = s.fecha_fin ? new Date(s.fecha_fin).toLocaleDateString('es-ES', { day: '2-digit', month: 'short' }) : '';
                    const color = pct >= 80 ? 'text-green-600 dark:text-green-400' : pct >= 60 ? 'text-amber-600 dark:text-amber-400' : 'text-red-500 dark:text-red-400';
                    return (
                        <div key={s.id} className="flex items-center gap-3 bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700 rounded-xl px-4 py-2.5">
                            <span className="text-lg">{TIPO_ICON[s.tipo_razonamiento] ?? '📋'}</span>
                            <div className="flex-1 min-w-0">
                                <span className="text-sm font-medium text-gray-700 dark:text-gray-300 capitalize">{s.tipo_razonamiento}</span>
                                <span className="text-xs text-gray-400 dark:text-gray-500 ml-2">{fecha}</span>
                            </div>
                            <span className={`text-sm font-bold tabular-nums ${color}`}>{pct}%</span>
                            <span className="text-xs text-gray-400 dark:text-gray-500">{s.correctas}/{s.preguntas_respondidas}</span>
                        </div>
                    );
                })}
            </div>
        </div>
    );
}

const NIVEL_KEY = 'epso_nivel_preferido';

export default function EPSODashboard() {
    const navigate = useNavigate();
    const [nivel, setNivel] = useState(() => localStorage.getItem(NIVEL_KEY) ?? 'ast');

    const handleNivel = (v) => {
        setNivel(v);
        localStorage.setItem(NIVEL_KEY, v);
    };

    const { data: progreso } = useQuery({
        queryKey: ['epso-progreso', nivel],
        queryFn: async () => {
            try { return (await api.get('/epso/progreso')).data; } catch { return null; }
        },
        staleTime: 30_000,
    });

    const { data: historial } = useQuery({
        queryKey: ['epso-historial'],
        queryFn: async () => {
            try { return (await api.get('/epso/historial')).data; } catch { return []; }
        },
        staleTime: 30_000,
    });

    const totalSesiones = historial?.length ?? 0;
    const config = CONFIG_NIVEL[nivel] ?? CONFIG_NIVEL.ast;
    const tipos = nivel === 'ad'
        ? [...TIPOS_BASE, { tipo: 'sjt', label: 'Juzgamiento Situacional', icon: '🧭', desc: 'Escenarios profesionales de la función pública europea' }]
        : TIPOS_BASE;

    return (
        <div className="max-w-2xl mx-auto px-4 py-8 space-y-8">
            {/* Header */}
            <div className="text-center space-y-3">
                <div className="text-4xl">🇪🇺</div>
                <h1 className="text-2xl font-bold text-gray-900 dark:text-gray-100">
                    Preparación EPSO
                </h1>
                <p className="text-gray-500 dark:text-gray-400 text-sm">
                    {totalSesiones > 0
                        ? `${totalSesiones} sesión${totalSesiones !== 1 ? 'es' : ''} completada${totalSesiones !== 1 ? 's' : ''}`
                        : 'Tests de razonamiento · Flashcards UE'}
                </p>
                <NivelSelector nivel={nivel} onChange={handleNivel} />
                <p className="text-xs text-gray-400 dark:text-gray-500">
                    {nivel === 'ast'
                        ? 'Nivel AST: Asistente — preguntas de dificultad media'
                        : 'Nivel AD: Administrador — preguntas avanzadas + Test de Juzgamiento Situacional'}
                </p>
            </div>

            {/* Tests */}
            <div className="space-y-3">
                <h2 className="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">
                    Tests de razonamiento
                </h2>
                {tipos.map(t => (
                    <TipoCard
                        key={t.tipo}
                        {...t}
                        preguntas={config[t.tipo]?.preguntas ?? 3}
                        minutos={config[t.tipo]?.minutos ?? 8}
                        prog={progreso?.[t.tipo]}
                        nivel={nivel}
                        isNew={t.tipo === 'sjt'}
                    />
                ))}
            </div>

            {/* Flashcards */}
            <div>
                <h2 className="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-3">
                    Conocimiento UE
                </h2>
                <button
                    onClick={() => navigate('/dashboard/epso/flashcards')}
                    className="w-full flex items-center gap-4 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl p-5 text-left hover:border-brand-400 hover:shadow-md transition-all group"
                >
                    <span className="text-3xl">🎴</span>
                    <div className="flex-1 min-w-0">
                        <div className="flex items-baseline gap-2">
                            <span className="text-lg font-semibold text-gray-900 dark:text-gray-100 group-hover:text-brand-600 dark:group-hover:text-brand-400 transition-colors">
                                Flashcards UE
                            </span>
                            <span className="text-sm text-gray-400 dark:text-gray-500">15 min / sesión</span>
                        </div>
                        <p className="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
                            Instituciones · Historia · Políticas · Derecho
                        </p>
                    </div>
                    <svg className="w-5 h-5 text-gray-400 group-hover:text-brand-500 transition-colors flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
                    </svg>
                </button>
            </div>

            {/* Historial */}
            <Historial sesiones={historial} />
        </div>
    );
}
