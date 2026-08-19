const GRADE_INFO = {
    A: { color: 'text-green-600 dark:text-green-400', bg: 'bg-green-50 dark:bg-green-950 border-green-200 dark:border-green-800', label: 'Distinction' },
    B: { color: 'text-blue-600 dark:text-blue-400', bg: 'bg-blue-50 dark:bg-blue-950 border-blue-200 dark:border-blue-800', label: 'Merit' },
    C: { color: 'text-brand-600 dark:text-brand-400', bg: 'bg-brand-50 dark:bg-brand-950 border-brand-200 dark:border-brand-800', label: 'Pass' },
    D: { color: 'text-amber-600 dark:text-amber-400', bg: 'bg-amber-50 dark:bg-amber-950 border-amber-200 dark:border-amber-800', label: 'Near miss' },
    U: { color: 'text-red-600 dark:text-red-400', bg: 'bg-red-50 dark:bg-red-950 border-red-200 dark:border-red-800', label: 'Fail' },
};

function SkillScore({ label, score, icon }) {
    const color = score >= 75 ? 'bg-green-500' : score >= 60 ? 'bg-amber-500' : 'bg-red-500';
    return (
        <div className="space-y-1.5">
            <div className="flex justify-between items-center">
                <span className="text-sm text-gray-600 dark:text-gray-400">{icon} {label}</span>
                <span className="text-sm font-bold text-gray-800 dark:text-gray-200 tabular-nums">{score ?? '—'}/100</span>
            </div>
            <div className="h-2 bg-gray-100 dark:bg-gray-700 rounded-full overflow-hidden">
                <div className={`h-full ${color} rounded-full transition-all duration-700`} style={{ width: `${score ?? 0}%` }} />
            </div>
        </div>
    );
}

export default function ExamResult({ result, onClose }) {
    const grade = result.grade ?? 'U';
    const info = GRADE_INFO[grade] ?? GRADE_INFO.U;
    const passed = result.passed;

    return (
        <div className="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
            <div className="bg-white dark:bg-gray-900 rounded-2xl max-w-sm w-full p-6 space-y-5 shadow-2xl">
                {/* Score hero */}
                <div className={`rounded-2xl border-2 p-5 text-center ${info.bg}`}>
                    <p className="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-1">Cambridge Score</p>
                    <p className={`text-6xl font-bold tabular-nums ${info.color}`}>{result.cambridge_score}</p>
                    <p className="text-sm text-gray-500 dark:text-gray-400 mt-1">Nivel: {result.level}</p>
                    <div className={`inline-flex items-center gap-1.5 mt-3 px-3 py-1 rounded-full text-xs font-bold ${info.color} border ${info.bg}`}>
                        Grade {grade} — {info.label}
                    </div>
                </div>

                {/* Verdict */}
                <p className="text-center text-sm font-medium text-gray-700 dark:text-gray-300">
                    {passed ? '✅' : '❌'} {result.message}
                </p>

                {/* Section scores */}
                <div className="space-y-3">
                    <SkillScore label="Reading" score={result.reading_score} icon="📖" />
                    <SkillScore label="Writing" score={result.writing_score} icon="✍️" />
                    <SkillScore label="Listening" score={result.listening_score} icon="🎧" />
                </div>

                {/* CTA */}
                <div className="space-y-2">
                    {!passed && (
                        <p className="text-xs text-center text-gray-500 dark:text-gray-400">
                            Necesitas 160 puntos para aprobar. ¡Sigue practicando!
                        </p>
                    )}
                    <button
                        onClick={onClose}
                        className="w-full py-3 bg-brand-600 hover:bg-brand-700 text-white font-semibold rounded-xl text-sm transition-colors"
                    >
                        Volver al Dashboard
                    </button>
                </div>
            </div>
        </div>
    );
}
