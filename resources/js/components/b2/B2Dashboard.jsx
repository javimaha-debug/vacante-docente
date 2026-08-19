import { useNavigate } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import api from '../../lib/api';

const SKILLS = [
    { key: 'reading',   label: 'Reading',   icon: '📖', desc: 'Comprensión lectora · Textos B2' },
    { key: 'grammar',   label: 'Grammar',   icon: '📝', desc: 'Phrasal verbs · Word formation · Tenses' },
    { key: 'writing',   label: 'Writing',   icon: '✍️',  desc: 'Emails · Essays · Reports · Letters' },
    { key: 'listening', label: 'Listening', icon: '🎧', desc: 'Diálogos · Monólogos · Short talks' },
    { key: 'speaking',  label: 'Speaking',  icon: '🗣️', desc: 'Fluency · Vocabulary · Pronunciation' },
];

function ScoreRing({ score }) {
    const color = score >= 75 ? 'text-green-500 dark:text-green-400'
        : score >= 60 ? 'text-amber-500 dark:text-amber-400'
        : 'text-red-500 dark:text-red-400';
    return (
        <div className="flex flex-col items-center">
            <span className={`text-5xl font-bold tabular-nums ${color}`}>{score}</span>
            <span className="text-sm text-gray-400 dark:text-gray-500 mt-1">/ 100</span>
        </div>
    );
}

function SkillBar({ skill, data, onClick }) {
    const score = data?.score ?? 0;
    const barColor = score >= 75 ? 'bg-green-500' : score >= 60 ? 'bg-amber-500' : 'bg-red-500';

    return (
        <button
            onClick={onClick}
            className="w-full flex items-center gap-4 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl p-4 text-left hover:border-brand-400 hover:shadow-md transition-all group"
        >
            <span className="text-2xl flex-shrink-0">{skill.icon}</span>
            <div className="flex-1 min-w-0">
                <div className="flex items-center justify-between mb-1">
                    <span className="text-sm font-semibold text-gray-900 dark:text-gray-100 group-hover:text-brand-600 dark:group-hover:text-brand-400 transition-colors">
                        {skill.label}
                    </span>
                    <span className={`text-sm font-bold tabular-nums ${score >= 75 ? 'text-green-600 dark:text-green-400' : score >= 60 ? 'text-amber-600 dark:text-amber-400' : 'text-red-600 dark:text-red-400'}`}>
                        {score}/100
                    </span>
                </div>
                <div className="w-full bg-gray-100 dark:bg-gray-700 rounded-full h-2">
                    <div
                        className={`${barColor} h-2 rounded-full transition-all duration-700`}
                        style={{ width: `${score}%` }}
                    />
                </div>
                <p className="text-xs text-gray-400 dark:text-gray-500 mt-1">{skill.desc}</p>
                {data?.exercises_completed > 0 && (
                    <p className="text-xs text-gray-400 dark:text-gray-500">
                        {data.exercises_completed} ejercicios · {data.accuracy ?? 0}% accuracy
                    </p>
                )}
            </div>
            <svg className="w-4 h-4 text-gray-400 group-hover:text-brand-500 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
            </svg>
        </button>
    );
}

function StreakBadge({ streak }) {
    if (!streak?.current) return null;
    return (
        <div className="flex items-center gap-1.5 bg-orange-50 dark:bg-orange-950 text-orange-600 dark:text-orange-400 px-3 py-1.5 rounded-full text-sm font-semibold">
            🔥 {streak.current} día{streak.current !== 1 ? 's' : ''} seguido{streak.current !== 1 ? 's' : ''}
        </div>
    );
}

export default function B2Dashboard() {
    const navigate = useNavigate();

    const { data: dashboard, isLoading } = useQuery({
        queryKey: ['b2-dashboard'],
        queryFn: async () => {
            try { return (await api.get('/b2/dashboard')).data; } catch { return null; }
        },
        staleTime: 30_000,
    });

    const overallScore = dashboard?.profile?.overall_score ?? 0;
    const skills = dashboard?.skills ?? {};
    const streak = dashboard?.streak;
    const hasDiagnostic = !!dashboard?.profile?.diagnostic_completed_at;

    return (
        <div className="max-w-2xl mx-auto px-4 py-8 space-y-8">
            {/* Header */}
            <div className="text-center space-y-3">
                <div className="text-5xl">🇬🇧</div>
                <h1 className="text-2xl font-bold text-gray-900 dark:text-gray-100">B2 English</h1>
                <p className="text-sm text-gray-500 dark:text-gray-400">
                    Preparación Cambridge · Sistema on-demand
                </p>
                {streak && <div className="flex justify-center"><StreakBadge streak={streak} /></div>}
            </div>

            {/* Overall score */}
            <div className="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl p-6 text-center">
                <p className="text-xs font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-wide mb-3">
                    Puntuación Global
                </p>
                {isLoading ? (
                    <div className="h-14 bg-gray-100 dark:bg-gray-700 rounded-xl animate-pulse" />
                ) : (
                    <ScoreRing score={overallScore} />
                )}
                {!hasDiagnostic && !isLoading && (
                    <div className="mt-4">
                        <button
                            onClick={() => navigate('/dashboard/b2/diagnostic')}
                            className="px-5 py-2 bg-brand-600 hover:bg-brand-700 text-white text-sm font-semibold rounded-xl transition-colors"
                        >
                            📋 Hacer test diagnóstico
                        </button>
                        <p className="text-xs text-gray-400 dark:text-gray-500 mt-2">
                            Descubre tu nivel actual en 20 minutos
                        </p>
                    </div>
                )}
                {hasDiagnostic && dashboard?.profile?.diagnostic_band && (
                    <p className="text-sm text-gray-500 dark:text-gray-400 mt-2">
                        Nivel: <span className="font-semibold text-brand-600 dark:text-brand-400">{dashboard.profile.diagnostic_band}</span>
                    </p>
                )}
            </div>

            {/* Skills */}
            <div className="space-y-3">
                <h2 className="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">
                    Skills · Haz click para practicar
                </h2>
                {SKILLS.map(skill => (
                    <SkillBar
                        key={skill.key}
                        skill={skill}
                        data={skills[skill.key]}
                        onClick={() => navigate(`/dashboard/b2/skill/${skill.key}`)}
                    />
                ))}
            </div>

            {/* Quick actions */}
            <div className="grid grid-cols-3 gap-3">
                {[
                    { label: 'Grammar', icon: '📝', skill: 'grammar' },
                    { label: 'Reading', icon: '📖', skill: 'reading' },
                    { label: 'Writing', icon: '✍️', skill: 'writing' },
                ].map(q => (
                    <button
                        key={q.skill}
                        onClick={() => navigate(`/dashboard/b2/skill/${q.skill}`)}
                        className="flex flex-col items-center gap-1.5 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-3 hover:border-brand-400 hover:shadow-sm transition-all"
                    >
                        <span className="text-xl">{q.icon}</span>
                        <span className="text-xs font-medium text-gray-700 dark:text-gray-300">{q.label}</span>
                    </button>
                ))}
            </div>
        </div>
    );
}
