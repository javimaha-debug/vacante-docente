import { useParams, useNavigate } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import api from '../../lib/api';

const SKILL_INFO = {
    reading:   { label: 'Reading',   icon: '📖', color: 'blue' },
    grammar:   { label: 'Grammar',   icon: '📝', color: 'purple' },
    writing:   { label: 'Writing',   icon: '✍️',  color: 'green' },
    listening: { label: 'Listening', icon: '🎧', color: 'orange' },
    speaking:  { label: 'Speaking',  icon: '🗣️', color: 'red' },
};

const MASTERY_LABELS = {
    beginner: { label: 'Principiante', color: 'text-red-500' },
    intermediate: { label: 'Intermedio', color: 'text-amber-500' },
    advanced: { label: 'Avanzado', color: 'text-blue-500' },
    mastered: { label: 'Dominado', color: 'text-green-500' },
};

export default function SkillFocusMode() {
    const { skill } = useParams();
    const navigate = useNavigate();
    const info = SKILL_INFO[skill] ?? SKILL_INFO.grammar;

    const { data: progress, isLoading: loadingProgress } = useQuery({
        queryKey: ['b2-progress', skill],
        queryFn: async () => {
            try { return (await api.get(`/b2/progress/${skill}`)).data; } catch { return null; }
        },
        staleTime: 15_000,
    });

    const mastery = MASTERY_LABELS[progress?.mastery_level ?? 'beginner'];

    return (
        <div className="max-w-lg mx-auto px-4 py-8 space-y-6">
            {/* Header */}
            <div className="flex items-center gap-3">
                <button
                    onClick={() => navigate('/dashboard/b2')}
                    className="p-2 rounded-lg text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors"
                >
                    <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" />
                    </svg>
                </button>
                <span className="text-2xl">{info.icon}</span>
                <div>
                    <h1 className="text-xl font-bold text-gray-900 dark:text-gray-100">{info.label}</h1>
                    {!loadingProgress && progress && (
                        <p className={`text-sm font-medium ${mastery.color}`}>{mastery.label}</p>
                    )}
                </div>
            </div>

            {/* Progress card */}
            {progress && (
                <div className="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl p-5 space-y-3">
                    <div className="flex items-center justify-between">
                        <span className="text-sm text-gray-500 dark:text-gray-400">Score actual</span>
                        <span className={`text-2xl font-bold tabular-nums ${progress.current_score >= 75 ? 'text-green-600 dark:text-green-400' : progress.current_score >= 60 ? 'text-amber-600 dark:text-amber-400' : 'text-red-600 dark:text-red-400'}`}>
                            {progress.current_score}/100
                        </span>
                    </div>
                    <div className="w-full bg-gray-100 dark:bg-gray-700 rounded-full h-2">
                        <div
                            className={`h-2 rounded-full transition-all duration-700 ${progress.current_score >= 75 ? 'bg-green-500' : progress.current_score >= 60 ? 'bg-amber-500' : 'bg-red-500'}`}
                            style={{ width: `${progress.current_score}%` }}
                        />
                    </div>
                    <div className="flex justify-between text-xs text-gray-400 dark:text-gray-500">
                        <span>{progress.exercises_completed} ejercicios completados</span>
                        <span>{progress.accuracy_percentage ?? 0}% accuracy</span>
                    </div>
                </div>
            )}

            {/* Start exercise button */}
            <button
                onClick={() => navigate(`/dashboard/b2/exercise/${skill}`)}
                className="w-full py-4 bg-brand-600 hover:bg-brand-700 text-white font-semibold rounded-2xl text-lg transition-colors shadow-md hover:shadow-lg"
            >
                {info.icon} Practicar {info.label}
            </button>

            {/* Tips */}
            <div className="bg-blue-50 dark:bg-blue-950 border border-blue-200 dark:border-blue-800 rounded-xl p-4">
                <p className="text-sm text-blue-700 dark:text-blue-300 font-medium mb-1">💡 Cómo funciona</p>
                <ul className="text-xs text-blue-600 dark:text-blue-400 space-y-1">
                    <li>• Cada respuesta correcta suma puntos al score</li>
                    <li>• Verde ≥75, Amarillo ≥60, Rojo &lt;60</li>
                    <li>• Repite cuando quieras — ejercicios del pool aleatorio</li>
                </ul>
            </div>
        </div>
    );
}
