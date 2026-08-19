import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { useNavigate } from 'react-router-dom';
import api from '../../lib/api';

const CATEGORIAS = [
    { key: '', label: 'Todas' },
    { key: 'instituciones', label: '🏛️ Instituciones' },
    { key: 'historia', label: '📜 Historia' },
    { key: 'politicas', label: '📋 Políticas' },
    { key: 'derecho', label: '⚖️ Derecho' },
    { key: 'otras', label: '🌐 Otras' },
];

function Flashcard({ card, onRespuesta }) {
    const [girada, setGirada] = useState(false);

    const responder = (dificultad) => {
        onRespuesta(card.id, dificultad);
        setGirada(false);
    };

    return (
        <div className="space-y-4">
            <div
                onClick={() => setGirada(g => !g)}
                className="cursor-pointer bg-white dark:bg-gray-800 border-2 border-gray-200 dark:border-gray-700 rounded-2xl p-8 min-h-48 flex flex-col items-center justify-center text-center select-none hover:border-brand-300 dark:hover:border-brand-700 transition-all"
            >
                {!girada ? (
                    <>
                        <p className="text-xs font-semibold text-brand-600 dark:text-brand-400 uppercase tracking-wide mb-4">
                            Pregunta
                        </p>
                        <p className="text-lg font-medium text-gray-900 dark:text-gray-100 leading-relaxed">
                            {card.frente}
                        </p>
                        <p className="text-xs text-gray-400 dark:text-gray-500 mt-6">Toca para ver la respuesta</p>
                    </>
                ) : (
                    <>
                        <p className="text-xs font-semibold text-green-600 dark:text-green-400 uppercase tracking-wide mb-4">
                            Respuesta
                        </p>
                        <p className="text-lg text-gray-800 dark:text-gray-200 leading-relaxed">
                            {card.reverso}
                        </p>
                    </>
                )}
            </div>

            {girada && (
                <div className="flex gap-3">
                    <button
                        onClick={() => responder('dificil')}
                        className="flex-1 py-3 rounded-xl bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-300 font-medium hover:bg-red-100 dark:hover:bg-red-900/40 transition-colors"
                    >
                        😓 Difícil
                    </button>
                    <button
                        onClick={() => responder('normal')}
                        className="flex-1 py-3 rounded-xl bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 text-amber-700 dark:text-amber-300 font-medium hover:bg-amber-100 dark:hover:bg-amber-900/40 transition-colors"
                    >
                        🤔 Normal
                    </button>
                    <button
                        onClick={() => responder('facil')}
                        className="flex-1 py-3 rounded-xl bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 text-green-700 dark:text-green-300 font-medium hover:bg-green-100 dark:hover:bg-green-900/40 transition-colors"
                    >
                        😊 Fácil
                    </button>
                </div>
            )}
        </div>
    );
}

export default function FlashcardsUE() {
    const navigate = useNavigate();
    const qc = useQueryClient();
    const [categoria, setCategoria] = useState('');
    const [indice, setIndice] = useState(0);
    const [sesionCompletada, setSesionCompletada] = useState(false);
    const [respondidas, setRespondidas] = useState(0);

    const { data: cards = [], isLoading } = useQuery({
        queryKey: ['epso-flashcards-proximas', categoria],
        queryFn: async () => {
            const params = new URLSearchParams();
            if (categoria) params.set('categoria', categoria);
            return (await api.get(`/epso/flashcards/proximas?${params}`)).data;
        },
        staleTime: 30_000,
    });

    const repasarMutation = useMutation({
        mutationFn: ({ id, dificultad }) => api.post(`/epso/flashcards/${id}/repasar`, { dificultad }),
        onSuccess: () => {
            qc.invalidateQueries({ queryKey: ['epso-flashcards-proximas'] });
        },
    });

    const onRespuesta = (id, dificultad) => {
        repasarMutation.mutate({ id, dificultad });
        setRespondidas(r => r + 1);
        if (indice + 1 < cards.length) {
            setIndice(i => i + 1);
        } else {
            setSesionCompletada(true);
        }
    };

    if (sesionCompletada) {
        return (
            <div className="max-w-lg mx-auto px-4 py-12 text-center space-y-6">
                <div className="text-5xl">🎉</div>
                <h2 className="text-2xl font-bold text-gray-900 dark:text-gray-100">¡Sesión completada!</h2>
                <p className="text-gray-500 dark:text-gray-400">Repasaste {respondidas} flashcards de hoy.</p>
                <div className="flex gap-3">
                    <button
                        onClick={() => navigate('/dashboard/epso')}
                        className="flex-1 py-3 rounded-xl border border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-300 font-medium hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors"
                    >
                        Volver
                    </button>
                    <button
                        onClick={() => { setIndice(0); setSesionCompletada(false); setRespondidas(0); qc.invalidateQueries({ queryKey: ['epso-flashcards-proximas'] }); }}
                        className="flex-1 py-3 rounded-xl bg-brand-600 text-white font-medium hover:bg-brand-700 transition-colors"
                    >
                        Otra sesión
                    </button>
                </div>
            </div>
        );
    }

    return (
        <div className="max-w-2xl mx-auto px-4 py-6 space-y-6">
            {/* Header */}
            <div className="flex items-center justify-between">
                <button
                    onClick={() => navigate('/dashboard/epso')}
                    className="text-sm text-gray-500 hover:text-gray-700 dark:hover:text-gray-300 flex items-center gap-1 transition-colors"
                >
                    <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" />
                    </svg>
                    Volver
                </button>
                <div className="flex items-center gap-2">
                    <span className="text-2xl">🎴</span>
                    <span className="font-semibold text-gray-900 dark:text-gray-100">Flashcards UE</span>
                </div>
                {cards.length > 0 && (
                    <span className="text-sm text-gray-500 dark:text-gray-400">
                        {indice + 1}/{cards.length}
                    </span>
                )}
            </div>

            {/* Filtros de categoría */}
            <div className="flex flex-wrap gap-2">
                {CATEGORIAS.map(c => (
                    <button
                        key={c.key}
                        onClick={() => { setCategoria(c.key); setIndice(0); setSesionCompletada(false); }}
                        className={`px-3 py-1.5 rounded-full text-sm font-medium transition-colors ${
                            categoria === c.key
                                ? 'bg-brand-600 text-white'
                                : 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700'
                        }`}
                    >
                        {c.label}
                    </button>
                ))}
            </div>

            {isLoading ? (
                <div className="text-center py-12">
                    <div className="text-3xl animate-pulse">🎴</div>
                    <p className="text-gray-500 dark:text-gray-400 mt-2">Cargando flashcards…</p>
                </div>
            ) : cards.length === 0 ? (
                <div className="text-center py-12 space-y-3">
                    <div className="text-4xl">✅</div>
                    <p className="text-gray-600 dark:text-gray-300 font-medium">
                        No hay flashcards pendientes de repaso
                    </p>
                    <p className="text-sm text-gray-500 dark:text-gray-400">
                        ¡Vuelve mañana para el siguiente repaso!
                    </p>
                </div>
            ) : (
                <>
                    {/* Barra de progreso */}
                    <div className="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-1.5">
                        <div
                            className="bg-brand-500 h-1.5 rounded-full transition-all duration-500"
                            style={{ width: `${((indice) / cards.length) * 100}%` }}
                        />
                    </div>

                    <Flashcard card={cards[indice]} onRespuesta={onRespuesta} />
                </>
            )}
        </div>
    );
}
