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
        color: 'brand',
    },
    {
        tipo: 'numerico',
        label: 'Numérico',
        icon: '📊',
        preguntas: 3,
        minutos: 10,
        desc: 'Razonamiento con datos numéricos y tablas',
        color: 'amber',
    },
    {
        tipo: 'abstracto',
        label: 'Abstracto',
        icon: '🔷',
        preguntas: 2,
        minutos: 6,
        desc: 'Patrones visuales y secuencias lógicas',
        color: 'purple',
    },
];

function StatCard({ label, value, sub }) {
    return (
        <div className="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4 text-center">
            <div className="text-2xl font-bold text-brand-600 dark:text-brand-400">{value ?? '—'}</div>
            <div className="text-sm font-medium text-gray-700 dark:text-gray-300 mt-0.5">{label}</div>
            {sub && <div className="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{sub}</div>}
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
        staleTime: 60_000,
    });

    return (
        <div className="max-w-2xl mx-auto px-4 py-8 space-y-8">
            {/* Header */}
            <div className="text-center space-y-2">
                <div className="text-4xl">🇪🇺</div>
                <h1 className="text-2xl font-bold text-gray-900 dark:text-gray-100">
                    Preparación EPSO AST3
                </h1>
                <p className="text-gray-500 dark:text-gray-400 text-sm">
                    Tests de razonamiento · Flashcards UE · Optimizado para TDAH
                </p>
            </div>

            {/* Progress pills */}
            {progreso && (
                <div className="grid grid-cols-3 gap-3">
                    {TIPOS.map(t => {
                        const p = progreso[t.tipo];
                        const pct = p?.total ? Math.round((p.correctas / p.total) * 100) : null;
                        return (
                            <StatCard
                                key={t.tipo}
                                label={t.label}
                                value={pct != null ? `${pct}%` : '—'}
                                sub={p?.total ? `${p.correctas}/${p.total}` : 'Sin datos'}
                            />
                        );
                    })}
                </div>
            )}

            {/* Test buttons */}
            <div className="space-y-3">
                <h2 className="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">
                    Empezar test
                </h2>
                {TIPOS.map(t => (
                    <button
                        key={t.tipo}
                        onClick={() => navigate(`/dashboard/epso/test/${t.tipo}`)}
                        className="w-full flex items-center gap-4 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl p-5 text-left hover:border-brand-400 hover:shadow-md transition-all group"
                    >
                        <span className="text-3xl">{t.icon}</span>
                        <div className="flex-1 min-w-0">
                            <div className="flex items-baseline gap-2">
                                <span className="text-lg font-semibold text-gray-900 dark:text-gray-100 group-hover:text-brand-600 dark:group-hover:text-brand-400 transition-colors">
                                    {t.label}
                                </span>
                                <span className="text-sm text-gray-400 dark:text-gray-500">
                                    {t.preguntas} preguntas · {t.minutos} min
                                </span>
                            </div>
                            <p className="text-sm text-gray-500 dark:text-gray-400 mt-0.5">{t.desc}</p>
                        </div>
                        <svg className="w-5 h-5 text-gray-400 group-hover:text-brand-500 transition-colors flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
                        </svg>
                    </button>
                ))}
            </div>

            {/* Flashcards shortcut */}
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
        </div>
    );
}
