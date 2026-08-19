function ScoreBar({ score, label }) {
    const color = score >= 75 ? 'bg-green-500' : score >= 60 ? 'bg-amber-500' : 'bg-red-500';
    return (
        <div className="space-y-1">
            <div className="flex justify-between text-xs text-gray-500 dark:text-gray-400">
                <span>{label}</span>
                <span className="font-bold">{score}/100</span>
            </div>
            <div className="w-full bg-gray-100 dark:bg-gray-700 rounded-full h-2">
                <div className={`${color} h-2 rounded-full transition-all duration-700`} style={{ width: `${score}%` }} />
            </div>
        </div>
    );
}

export default function ImmediateScoring({ result, skill, exercise, onNext, onBack }) {
    const isCorrect = result?.is_correct;
    const points = result?.points_earned ?? 0;
    const newScore = result?.new_score ?? result?.score?.total;

    const SKILL_LABELS = { reading: 'Reading', grammar: 'Grammar', writing: 'Writing', listening: 'Listening', speaking: 'Speaking' };

    return (
        <div className="space-y-4 animate-in fade-in slide-in-from-bottom-4 duration-300">
            {/* Result banner */}
            <div className={`rounded-2xl p-5 border-2 ${isCorrect ? 'bg-green-50 dark:bg-green-950 border-green-200 dark:border-green-800' : 'bg-red-50 dark:bg-red-950 border-red-200 dark:border-red-800'}`}>
                <div className="flex items-center justify-between">
                    <div>
                        <p className={`text-xl font-bold ${isCorrect ? 'text-green-700 dark:text-green-300' : 'text-red-700 dark:text-red-300'}`}>
                            {isCorrect ? '✅ Correcto!' : '❌ Incorrecto'}
                        </p>
                        {isCorrect && points > 0 && (
                            <p className="text-sm text-green-600 dark:text-green-400 font-semibold mt-0.5">
                                +{points} punto{points !== 1 ? 's' : ''}
                            </p>
                        )}
                    </div>
                    {newScore !== undefined && (
                        <div className="text-right">
                            <p className="text-xs text-gray-400 dark:text-gray-500">{SKILL_LABELS[skill]}</p>
                            <p className={`text-2xl font-bold tabular-nums ${newScore >= 75 ? 'text-green-600 dark:text-green-400' : newScore >= 60 ? 'text-amber-600 dark:text-amber-400' : 'text-red-600 dark:text-red-400'}`}>
                                {newScore}/100
                            </p>
                        </div>
                    )}
                </div>
            </div>

            {/* Answer comparison (for MCQ) */}
            {result?.correct_answer && !isCorrect && (
                <div className="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-4 space-y-2">
                    <p className="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Respuesta correcta</p>
                    <p className="text-sm font-medium text-green-700 dark:text-green-300">{result.correct_answer}</p>
                </div>
            )}

            {/* Writing/speaking detailed score */}
            {result?.score && typeof result.score === 'object' && (
                <div className="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-4 space-y-3">
                    <p className="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Evaluación detallada</p>
                    {Object.entries(result.score)
                        .filter(([k]) => k !== 'total' && k !== 'wpm')
                        .map(([key, val]) => (
                            <ScoreBar key={key} score={Number(val)} label={key.charAt(0).toUpperCase() + key.slice(1)} />
                        ))}
                </div>
            )}

            {/* Feedback (writing/speaking) */}
            {result?.feedback && typeof result.feedback === 'object' && (
                <div className="bg-amber-50 dark:bg-amber-950 border border-amber-200 dark:border-amber-800 rounded-xl p-4 space-y-1.5">
                    <p className="text-xs font-semibold text-amber-700 dark:text-amber-300 uppercase">Feedback</p>
                    {Object.values(result.feedback).map((msg, i) => (
                        <p key={i} className="text-sm text-amber-700 dark:text-amber-300">• {msg}</p>
                    ))}
                </div>
            )}

            {/* Explanation */}
            {result?.explanation && (
                <div className="bg-blue-50 dark:bg-blue-950 border border-blue-200 dark:border-blue-800 rounded-xl p-4">
                    <p className="text-xs font-semibold text-blue-700 dark:text-blue-300 uppercase mb-1">Explicación</p>
                    <p className="text-sm text-blue-700 dark:text-blue-300">{result.explanation}</p>
                </div>
            )}

            {/* Actions */}
            <div className="flex gap-3">
                <button
                    onClick={onBack}
                    className="flex-1 py-3 border border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-300 font-semibold rounded-xl hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors text-sm"
                >
                    ← Volver
                </button>
                <button
                    onClick={onNext}
                    className="flex-[2] py-3 bg-brand-600 hover:bg-brand-700 text-white font-semibold rounded-xl transition-colors"
                >
                    Siguiente ejercicio →
                </button>
            </div>
        </div>
    );
}
