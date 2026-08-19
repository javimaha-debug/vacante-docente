import { useNavigate } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import api from '../../lib/api';

const TIPOS = [
    {
        tipo: 'verbal',
        label: 'Verbal',
        icon: '📝',
        preguntas: 4,
        minutos: 7,
        desc: 'Comprensión de textos y relaciones lógicas',
    },
    {
        tipo: 'numerico',
        label: 'Numérico',
        icon: '📊',
        preguntas: 3,
        minutos: 10,
        desc: 'Razonamiento con datos numéricos y tablas',
    },
    {
        tipo: 'abstracto',
        label: 'Abstracto',
        icon: '🔷',
        preguntas: 2,
        minutos: 6,
        desc: 'Patrones visuales y secuencias lógicas',
    },
];

function ScoreBar({ pct }) {
    const color = pct >= 80 ? 'bg-green-500' : pct >= 60 ? 'bg-amber-500' : 'bg-red-500';
    return (
        <div className="w-full bg-gray-100 dark:bg-gray-700 rounded-full h-1.5 mt-2">
            <div className={`${color} h-1.5 rounded-full transition-all duration-700`} style={{ width: `${pct}%` }} />
        </div>
    );
}

function TipoCard({ t, prog }) {
    const navigate = useNavigate();
    const pct = prog?.total ? Math.round((prog.correctas / prog.total) * 100) : null;
    const scoreMedio = prog?.score_medio ?? null;

    return (
        <button
            onClick={() => navigate(`/dashboard/epso/test/${t.tipo}`)}
            className="w-full flex items-center gap-4 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl p-5 text-left hover:border-brand-400 hover:shadow-md transition-all group"
        >
            <span className="text-3xl flex-shrink-0">{t.icon}</span>
            <div className="flex-1 min-w-0">
                <div className="flex items-baseline gap-2">
                    <span className="text-lg font-semibold text-gray-900 dark:text-gray-100 group-hover:text-brand-600 dark:group-hover:text-brand-400 transition-colors">
                        {t.label}
                    </span>
                    <span className="text-sm text-gray-400 dark:text-gray-500">
                        {t.preguntas}Q · {t.minutos} min
                    </span>
                    {scoreMedio != null && (
                        <span className={`ml-auto text-sm font-bold tabular-nums ${scoreMedio >= 80 ? 'text-green-600 dark:text-green-400' : scoreMedio >= 60 ? 'text-amber-600 dark:text-amber-400' : 'text-red-600 dark:text-red-400'}`}>
                            {scoreMedio}%
                        </span>
                    )}
                </div>
                <p className="text-sm text-gray-500 dark:text-gray-400 mt-0.5">{t.desc}</p>
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

    const TIPO_ICON = { verbal: '📝', numerico: '📊', abstracto: '🔷' };

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

export default function EPSODashboard() {
    const navigate = useNavigate();

    const { data: progreso } = useQuery({
        queryKey: ['epso-progreso'],
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

    return (
        <div className="max-w-2xl mx-auto px-4 py-8 space-y-8">
            {/* Header */}
            <div className="text-center space-y-2">
                <div className="text-4xl">🇪🇺</div>
                <h1 className="text-2xl font-bold text-gray-900 dark:text-gray-100">
                    Preparación EPSO AST3
                </h1>
                <p className="text-gray-500 dark:text-gray-400 text-sm">
                    {totalSesiones > 0
                        ? `${totalSesiones} sesión${totalSesiones !== 1 ? 'es' : ''} completada${totalSesiones !== 1 ? 's' : ''}`
                        : 'Tests de razonamiento · Flashcards UE'}
                </p>
            </div>

            {/* Tests */}
            <div className="space-y-3">
                <h2 className="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">
                    Tests de razonamiento
                </h2>
                {TIPOS.map(t => (
                    <TipoCard key={t.tipo} t={t} prog={progreso?.[t.tipo]} />
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
