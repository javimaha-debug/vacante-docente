import { useQuery } from '@tanstack/react-query';
import api from '../../lib/api';

function CategoryBar({ category, count, total }) {
    const pct = total > 0 ? Math.round((count / total) * 100) : 0;
    return (
        <div className="space-y-1">
            <div className="flex justify-between text-xs text-gray-600 dark:text-gray-400">
                <span className="capitalize">{category}</span>
                <span className="font-semibold">{count} errores</span>
            </div>
            <div className="h-2 bg-gray-100 dark:bg-gray-700 rounded-full overflow-hidden">
                <div className="h-full bg-red-400 dark:bg-red-600 rounded-full transition-all duration-500" style={{ width: `${pct}%` }} />
            </div>
        </div>
    );
}

export default function ErrorAnalysis({ onClose }) {
    const { data: errors, isLoading: errLoading } = useQuery({
        queryKey: ['b2-error-analysis'],
        queryFn: async () => (await api.get('/b2/analytics/errors')).data,
        staleTime: 60000,
    });

    const { data: trend, isLoading: trendLoading } = useQuery({
        queryKey: ['b2-score-trend'],
        queryFn: async () => (await api.get('/b2/analytics/trend', { params: { days: 14 } })).data,
        staleTime: 60000,
    });

    const { data: habits } = useQuery({
        queryKey: ['b2-habits'],
        queryFn: async () => (await api.get('/b2/analytics/habits')).data,
        staleTime: 60000,
    });

    const isLoading = errLoading || trendLoading;

    return (
        <div className="fixed inset-0 bg-black/50 z-50 flex items-end sm:items-center justify-center p-0 sm:p-4">
            <div className="bg-white dark:bg-gray-900 w-full sm:max-w-lg sm:rounded-2xl flex flex-col max-h-[90vh] shadow-2xl overflow-hidden">
                {/* Header */}
                <div className="flex items-center justify-between px-5 py-4 border-b border-gray-200 dark:border-gray-700">
                    <h2 className="text-base font-bold text-gray-900 dark:text-gray-100">📊 Análisis de errores</h2>
                    <button onClick={onClose} className="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                        <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                {/* Content */}
                <div className="flex-1 overflow-y-auto p-5 space-y-5">
                    {isLoading ? (
                        <div className="space-y-3 animate-pulse">
                            {[1,2,3].map(i => <div key={i} className="h-10 bg-gray-100 dark:bg-gray-700 rounded-xl" />)}
                        </div>
                    ) : (
                        <>
                            {/* Summary */}
                            {errors && (
                                <div className="grid grid-cols-3 gap-3">
                                    <div className="bg-red-50 dark:bg-red-950 border border-red-200 dark:border-red-800 rounded-xl p-3 text-center">
                                        <p className="text-2xl font-bold text-red-600 dark:text-red-400 tabular-nums">{errors.total_errors}</p>
                                        <p className="text-xs text-red-500 dark:text-red-400 mt-0.5">Total errores</p>
                                    </div>
                                    <div className="bg-amber-50 dark:bg-amber-950 border border-amber-200 dark:border-amber-800 rounded-xl p-3 text-center">
                                        <p className="text-2xl font-bold text-amber-600 dark:text-amber-400 tabular-nums">
                                            {Object.keys(errors.by_skill || {}).length}
                                        </p>
                                        <p className="text-xs text-amber-500 dark:text-amber-400 mt-0.5">Skills con errores</p>
                                    </div>
                                    <div className="bg-brand-50 dark:bg-brand-950 border border-brand-200 dark:border-brand-800 rounded-xl p-3 text-center">
                                        <p className="text-2xl font-bold text-brand-600 dark:text-brand-400 tabular-nums">
                                            {habits?.active_days_last_30 ?? '—'}
                                        </p>
                                        <p className="text-xs text-brand-500 dark:text-brand-400 mt-0.5">Días activo</p>
                                    </div>
                                </div>
                            )}

                            {/* Top error categories */}
                            {errors?.top_error_categories?.length > 0 && (
                                <div className="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-4 space-y-3">
                                    <p className="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Categorías con más errores</p>
                                    {errors.top_error_categories.map((cat, i) => (
                                        <CategoryBar
                                            key={i}
                                            category={cat.category}
                                            count={cat.count}
                                            total={errors.total_errors}
                                        />
                                    ))}
                                </div>
                            )}

                            {/* Errors by skill */}
                            {errors?.by_skill && Object.entries(errors.by_skill).length > 0 && (
                                <div className="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-4 space-y-3">
                                    <p className="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Errores por skill</p>
                                    {Object.entries(errors.by_skill).sort(([,a],[,b]) => b.count - a.count).map(([skill, data]) => (
                                        <div key={skill} className="flex items-center justify-between">
                                            <span className="text-sm capitalize text-gray-700 dark:text-gray-300">{skill}</span>
                                            <span className="text-sm font-semibold text-red-600 dark:text-red-400">{data.count} errores</span>
                                        </div>
                                    ))}
                                </div>
                            )}

                            {/* Score trend */}
                            {trend?.trend && Object.keys(trend.trend).length > 0 && (
                                <div className="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-4">
                                    <p className="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase mb-3">Tendencia últimos 14 días</p>
                                    <div className="space-y-2">
                                        {Object.entries(trend.trend).map(([skill, data]) => {
                                            const lastEntry = data[data.length - 1];
                                            const firstEntry = data[0];
                                            const delta = lastEntry && firstEntry ? lastEntry.accuracy - firstEntry.accuracy : 0;
                                            return (
                                                <div key={skill} className="flex items-center justify-between text-sm">
                                                    <span className="capitalize text-gray-700 dark:text-gray-300">{skill}</span>
                                                    <div className="flex items-center gap-2">
                                                        <span className="font-semibold tabular-nums text-gray-800 dark:text-gray-200">
                                                            {lastEntry?.accuracy ?? '—'}%
                                                        </span>
                                                        {delta !== 0 && (
                                                            <span className={`text-xs font-semibold ${delta > 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'}`}>
                                                                {delta > 0 ? '↑' : '↓'}{Math.abs(delta)}%
                                                            </span>
                                                        )}
                                                    </div>
                                                </div>
                                            );
                                        })}
                                    </div>
                                </div>
                            )}

                            {/* No data */}
                            {(!errors || errors.total_errors === 0) && (
                                <div className="text-center py-10">
                                    <p className="text-4xl mb-3">🎉</p>
                                    <p className="text-sm font-semibold text-gray-700 dark:text-gray-300">¡Sin errores registrados!</p>
                                    <p className="text-xs text-gray-400 dark:text-gray-500 mt-1">Practica más ejercicios para ver tu análisis.</p>
                                </div>
                            )}
                        </>
                    )}
                </div>
            </div>
        </div>
    );
}
