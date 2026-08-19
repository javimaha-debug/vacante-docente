import { useState, useEffect, useRef } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api from '../../lib/api';
import ImmediateScoring from './ImmediateScoring';

export default function ExerciseComponent() {
    const { skill } = useParams();
    const navigate = useNavigate();
    const queryClient = useQueryClient();
    const startTimeRef = useRef(Date.now());

    const [selectedOption, setSelectedOption] = useState(null);
    const [textAnswer, setTextAnswer] = useState('');
    const [result, setResult] = useState(null);
    const [wordCount, setWordCount] = useState(0);

    const { data: exercise, isLoading, refetch } = useQuery({
        queryKey: ['b2-exercise', skill],
        queryFn: async () => {
            const res = await api.get(`/b2/exercises/${skill}/next`);
            return res.data;
        },
        staleTime: 0,
        gcTime: 0,
    });

    useEffect(() => {
        startTimeRef.current = Date.now();
        setSelectedOption(null);
        setTextAnswer('');
        setResult(null);
    }, [exercise?.id]);

    const submitMutation = useMutation({
        mutationFn: async (answer) => {
            const timeSpent = Math.round((Date.now() - startTimeRef.current) / 1000);
            const endpoint = exercise.skill === 'writing'
                ? `/b2/writing/${exercise.id}/submit`
                : exercise.skill === 'speaking'
                ? `/b2/speaking/${exercise.id}/submit`
                : `/b2/exercise/${exercise.id}/submit`;

            const payload = exercise.skill === 'writing'
                ? { text: answer, time_spent_seconds: timeSpent }
                : exercise.skill === 'speaking'
                ? { transcription: answer, time_spent_seconds: timeSpent }
                : { answer, time_spent_seconds: timeSpent };

            const res = await api.post(endpoint, payload);
            return res.data;
        },
        onSuccess: (data) => {
            setResult(data);
            queryClient.invalidateQueries({ queryKey: ['b2-progress', skill] });
            queryClient.invalidateQueries({ queryKey: ['b2-dashboard'] });
        },
    });

    const handleSubmit = () => {
        const answer = exercise?.options ? selectedOption : textAnswer;
        if (!answer?.trim()) return;
        submitMutation.mutate(answer);
    };

    const handleNext = () => {
        setResult(null);
        setSelectedOption(null);
        setTextAnswer('');
        startTimeRef.current = Date.now();
        refetch();
    };

    if (isLoading) {
        return (
            <div className="max-w-lg mx-auto px-4 py-12 text-center">
                <div className="animate-pulse space-y-4">
                    <div className="h-4 bg-gray-200 dark:bg-gray-700 rounded w-3/4 mx-auto" />
                    <div className="h-32 bg-gray-200 dark:bg-gray-700 rounded-xl" />
                </div>
            </div>
        );
    }

    if (!exercise) {
        return (
            <div className="max-w-lg mx-auto px-4 py-12 text-center">
                <p className="text-gray-500 dark:text-gray-400">No hay ejercicios disponibles para este skill.</p>
                <button onClick={() => navigate('/dashboard/b2')} className="mt-4 text-brand-600 hover:underline text-sm">
                    ← Volver al dashboard
                </button>
            </div>
        );
    }

    const SKILL_ICONS = { reading: '📖', grammar: '📝', writing: '✍️', listening: '🎧', speaking: '🗣️' };
    const isOpenEnded = ['writing', 'speaking', 'listening'].includes(exercise.skill) && !exercise.options?.length;

    return (
        <div className="max-w-lg mx-auto px-4 py-6 space-y-5">
            {/* Nav */}
            <div className="flex items-center justify-between">
                <button
                    onClick={() => navigate(`/dashboard/b2/skill/${skill}`)}
                    className="flex items-center gap-1.5 text-sm text-gray-500 hover:text-gray-800 dark:hover:text-gray-200 transition-colors"
                >
                    <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" />
                    </svg>
                    {SKILL_ICONS[skill]} {skill.charAt(0).toUpperCase() + skill.slice(1)}
                </button>
                <div className="flex items-center gap-2">
                    <span className="text-xs text-gray-400 dark:text-gray-500">
                        Dificultad {exercise.difficulty}/5
                    </span>
                    <span className="text-xs bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400 px-2 py-0.5 rounded-full">
                        +{exercise.points_reward}pts
                    </span>
                </div>
            </div>

            {/* Question */}
            <div className="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl p-5">
                {exercise.category && (
                    <p className="text-xs font-semibold text-brand-600 dark:text-brand-400 uppercase tracking-wide mb-3">
                        {exercise.category}
                    </p>
                )}
                <p className="text-gray-900 dark:text-gray-100 leading-relaxed whitespace-pre-wrap text-base">
                    {exercise.question_text || exercise.user_input_instruction}
                </p>
            </div>

            {/* Answer area */}
            {!result && (
                <>
                    {exercise.options?.length ? (
                        <div className="space-y-2.5">
                            {exercise.options.map((option, i) => (
                                <button
                                    key={i}
                                    onClick={() => setSelectedOption(option)}
                                    className={`w-full text-left px-4 py-3 rounded-xl border text-sm font-medium transition-all ${
                                        selectedOption === option
                                            ? 'border-brand-500 bg-brand-50 dark:bg-brand-950 text-brand-700 dark:text-brand-300'
                                            : 'border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:border-gray-400'
                                    }`}
                                >
                                    <span className="font-semibold text-gray-400 dark:text-gray-500 mr-2">
                                        {String.fromCharCode(65 + i)}.
                                    </span>
                                    {option}
                                </button>
                            ))}
                        </div>
                    ) : (
                        <div className="space-y-2">
                            <textarea
                                value={textAnswer}
                                onChange={(e) => {
                                    setTextAnswer(e.target.value);
                                    setWordCount(e.target.value.trim() ? e.target.value.trim().split(/\s+/).length : 0);
                                }}
                                placeholder={exercise.skill === 'writing' ? 'Escribe tu respuesta aquí...' : exercise.skill === 'speaking' ? 'Escribe tu transcripción / notas...' : 'Tu respuesta...'}
                                rows={exercise.skill === 'writing' ? 8 : 3}
                                className="w-full px-4 py-3 border border-gray-200 dark:border-gray-700 rounded-xl bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 text-sm resize-none focus:outline-none focus:ring-2 focus:ring-brand-500 transition-all"
                            />
                            {exercise.skill === 'writing' && (
                                <p className="text-xs text-gray-400 dark:text-gray-500 text-right">
                                    {wordCount} palabras · Objetivo: 100-150 palabras
                                </p>
                            )}
                        </div>
                    )}

                    <button
                        onClick={handleSubmit}
                        disabled={submitMutation.isPending || (!selectedOption && !textAnswer.trim())}
                        className="w-full py-3 bg-brand-600 hover:bg-brand-700 disabled:bg-gray-300 dark:disabled:bg-gray-700 text-white font-semibold rounded-xl transition-colors"
                    >
                        {submitMutation.isPending ? 'Comprobando...' : 'Confirmar respuesta →'}
                    </button>
                </>
            )}

            {/* Result */}
            {result && (
                <ImmediateScoring
                    result={result}
                    skill={skill}
                    exercise={exercise}
                    onNext={handleNext}
                    onBack={() => navigate(`/dashboard/b2/skill/${skill}`)}
                />
            )}
        </div>
    );
}
