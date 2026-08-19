import { useState, useEffect, useCallback } from 'react';
import { useMutation } from '@tanstack/react-query';
import api from '../../lib/api';

function ExamTimer({ totalMinutes, onExpire }) {
    const [secondsLeft, setSecondsLeft] = useState(totalMinutes * 60);

    useEffect(() => {
        const interval = setInterval(() => {
            setSecondsLeft(prev => {
                if (prev <= 1) {
                    clearInterval(interval);
                    onExpire?.();
                    return 0;
                }
                return prev - 1;
            });
        }, 1000);
        return () => clearInterval(interval);
    }, []);

    const mins = Math.floor(secondsLeft / 60);
    const secs = secondsLeft % 60;
    const isLow = secondsLeft < 120;

    return (
        <div className={`flex items-center gap-1.5 text-sm font-mono font-bold ${isLow ? 'text-red-600 dark:text-red-400' : 'text-gray-700 dark:text-gray-300'}`}>
            <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            {String(mins).padStart(2, '0')}:{String(secs).padStart(2, '0')}
        </div>
    );
}

export default function ExamSection({ examId, skill, sectionData, sectionInfo, sectionIndex, totalSections, onComplete }) {
    const [answers, setAnswers] = useState({});
    const [textAnswer, setTextAnswer] = useState('');
    const [submitted, setSubmitted] = useState(false);
    const [sectionResult, setSectionResult] = useState(null);

    const exercises = sectionData?.exercises ?? [];

    const submitMutation = useMutation({
        mutationFn: async (payload) => {
            const res = await api.post(`/b2/mock-exam/${examId}/section`, payload);
            return res.data;
        },
        onSuccess: (data) => {
            setSectionResult(data);
            setSubmitted(true);
        },
    });

    const handleSubmit = useCallback(() => {
        if (submitMutation.isPending) return;

        let payload;
        if (skill === 'writing') {
            payload = { skill, answers: textAnswer || '(no answer)' };
        } else {
            payload = { skill, answers };
        }
        submitMutation.mutate(payload);
    }, [skill, answers, textAnswer, submitMutation]);

    const answeredCount = skill === 'writing' ? (textAnswer.trim() ? 1 : 0) : Object.keys(answers).length;
    const totalQuestions = skill === 'writing' ? 1 : exercises.length;

    return (
        <div className="fixed inset-0 bg-gray-50 dark:bg-gray-950 z-50 flex flex-col">
            {/* Header */}
            <div className="bg-white dark:bg-gray-900 border-b border-gray-200 dark:border-gray-700 px-4 py-3 flex items-center justify-between">
                <div className="flex items-center gap-2">
                    <span className="text-xl">{sectionInfo.icon}</span>
                    <div>
                        <p className="text-sm font-bold text-gray-900 dark:text-gray-100">{sectionInfo.label}</p>
                        <p className="text-xs text-gray-500 dark:text-gray-400">Sección {sectionIndex + 1}/{totalSections}</p>
                    </div>
                </div>
                <div className="flex items-center gap-3">
                    <span className="text-xs text-gray-400 dark:text-gray-500">{answeredCount}/{totalQuestions}</span>
                    <ExamTimer totalMinutes={sectionInfo.minutes} onExpire={handleSubmit} />
                </div>
            </div>

            {/* Content */}
            <div className="flex-1 overflow-y-auto max-w-lg mx-auto w-full px-4 py-5 space-y-4">
                {submitted && sectionResult ? (
                    <div className="space-y-4 animate-in fade-in duration-300">
                        <div className="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl p-5 text-center">
                            <p className="text-4xl font-bold text-brand-600 dark:text-brand-400 tabular-nums">{sectionResult.score}</p>
                            <p className="text-sm text-gray-500 dark:text-gray-400 mt-1">Puntuación en {sectionInfo.label}</p>
                            {skill !== 'writing' && (
                                <p className="text-xs text-gray-400 dark:text-gray-500 mt-0.5">
                                    {sectionResult.correct}/{sectionResult.total} correctas
                                </p>
                            )}
                        </div>

                        {/* Show answer review */}
                        {skill !== 'writing' && sectionResult.results && exercises.slice(0, 5).map((ex, i) => {
                            const r = sectionResult.results[ex.id];
                            return (
                                <div key={i} className={`rounded-xl p-3 border ${r?.correct ? 'border-green-200 dark:border-green-800 bg-green-50 dark:bg-green-950' : 'border-red-200 dark:border-red-800 bg-red-50 dark:bg-red-950'}`}>
                                    <p className="text-xs font-medium text-gray-700 dark:text-gray-300 line-clamp-2">{ex.question}</p>
                                    {!r?.correct && r?.correct_answer && (
                                        <p className="text-xs text-green-700 dark:text-green-300 mt-1">✓ {r.correct_answer}</p>
                                    )}
                                </div>
                            );
                        })}

                        <button
                            onClick={() => onComplete(sectionResult.score)}
                            className="w-full py-3 bg-brand-600 hover:bg-brand-700 text-white font-semibold rounded-xl text-sm transition-colors"
                        >
                            {sectionIndex < totalSections - 1 ? 'Siguiente sección →' : 'Ver resultados finales →'}
                        </button>
                    </div>
                ) : (
                    <>
                        {skill === 'writing' ? (
                            <div className="space-y-3">
                                <div className="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl p-4">
                                    <p className="text-sm text-gray-800 dark:text-gray-200 leading-relaxed">
                                        {exercises[0]?.question || 'Write a formal email (140-190 words).'}
                                    </p>
                                </div>
                                <textarea
                                    value={textAnswer}
                                    onChange={(e) => setTextAnswer(e.target.value)}
                                    placeholder="Escribe tu respuesta aquí..."
                                    rows={10}
                                    className="w-full px-4 py-3 border border-gray-200 dark:border-gray-700 rounded-xl bg-white dark:bg-gray-800 text-sm text-gray-900 dark:text-gray-100 resize-none focus:outline-none focus:ring-2 focus:ring-brand-500"
                                />
                                <p className="text-xs text-gray-400 dark:text-gray-500 text-right">
                                    {textAnswer.trim() ? textAnswer.trim().split(/\s+/).length : 0} palabras
                                </p>
                            </div>
                        ) : (
                            exercises.map((ex, i) => (
                                <div key={ex.id || i} className="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl p-4 space-y-3">
                                    <p className="text-xs font-semibold text-gray-400 dark:text-gray-500 uppercase">Pregunta {i + 1}</p>
                                    <p className="text-sm text-gray-800 dark:text-gray-200 leading-relaxed">{ex.question}</p>
                                    {ex.options && (
                                        <div className="space-y-2">
                                            {ex.options.map((opt, j) => (
                                                <button
                                                    key={j}
                                                    onClick={() => setAnswers(prev => ({ ...prev, [ex.id]: opt }))}
                                                    className={`w-full text-left px-3 py-2 rounded-xl border text-sm transition-all ${
                                                        answers[ex.id] === opt
                                                            ? 'border-brand-500 bg-brand-50 dark:bg-brand-950 text-brand-700 dark:text-brand-300'
                                                            : 'border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-300 hover:border-gray-400'
                                                    }`}
                                                >
                                                    <span className="font-semibold text-gray-400 dark:text-gray-500 mr-2">{String.fromCharCode(65 + j)}.</span>
                                                    {opt}
                                                </button>
                                            ))}
                                        </div>
                                    )}
                                </div>
                            ))
                        )}

                        <button
                            onClick={handleSubmit}
                            disabled={submitMutation.isPending || answeredCount === 0}
                            className="w-full py-3 bg-brand-600 hover:bg-brand-700 disabled:bg-gray-300 dark:disabled:bg-gray-700 text-white font-semibold rounded-xl text-sm transition-colors"
                        >
                            {submitMutation.isPending ? 'Enviando...' : `Entregar ${sectionInfo.label} →`}
                        </button>
                    </>
                )}
            </div>
        </div>
    );
}
