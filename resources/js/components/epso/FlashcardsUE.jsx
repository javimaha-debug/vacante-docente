import { useState, useRef } from 'react';
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

/* ── Tarjeta de ESTUDIO (solo leer/voltear) ─────────────────────────────── */

function TarjetaEstudio({ card, onSiguiente, esUltima }) {
    const [girada, setGirada] = useState(false);

    const handleSiguiente = () => {
        setGirada(false);
        onSiguiente();
    };

    return (
        <div className="space-y-4">
            <div
                onClick={() => setGirada(g => !g)}
                className="cursor-pointer bg-white dark:bg-gray-800 border-2 border-gray-200 dark:border-gray-700 rounded-2xl p-8 min-h-52 flex flex-col items-center justify-center text-center select-none hover:border-brand-300 dark:hover:border-brand-700 transition-all"
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
                <button
                    onClick={handleSiguiente}
                    className="w-full py-3 rounded-xl bg-brand-600 text-white font-semibold hover:bg-brand-700 transition-colors"
                >
                    {esUltima ? '¡Listo! Empezar repaso →' : 'Siguiente →'}
                </button>
            )}
        </div>
    );
}

/* ── Tarjeta de REPASO (escribir y comparar) ─────────────────────────────── */

function TarjetaRepaso({ card, onRespuesta }) {
    const [texto, setTexto] = useState('');
    const [revelada, setRevelada] = useState(false);
    const textareaRef = useRef(null);

    const revelar = () => {
        if (!texto.trim()) return;
        setRevelada(true);
    };

    const responder = (dificultad) => {
        onRespuesta(card.id, dificultad);
        setTexto('');
        setRevelada(false);
    };

    return (
        <div className="space-y-4">
            {/* Pregunta */}
            <div className="bg-white dark:bg-gray-800 border-2 border-gray-200 dark:border-gray-700 rounded-2xl p-6">
                <p className="text-xs font-semibold text-brand-600 dark:text-brand-400 uppercase tracking-wide mb-3">
                    Pregunta
                </p>
                <p className="text-lg font-medium text-gray-900 dark:text-gray-100 leading-relaxed">
                    {card.frente}
                </p>
            </div>

            {/* Zona de escritura */}
            {!revelada ? (
                <div className="space-y-3">
                    <textarea
                        ref={textareaRef}
                        value={texto}
                        onChange={e => setTexto(e.target.value)}
                        onKeyDown={e => { if (e.key === 'Enter' && e.ctrlKey) revelar(); }}
                        placeholder="Escribe tu respuesta aquí…"
                        rows={3}
                        className="w-full px-4 py-3 rounded-xl border-2 border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:border-brand-400 dark:focus:border-brand-500 resize-none transition-colors text-base"
                        autoFocus
                    />
                    <button
                        onClick={revelar}
                        disabled={!texto.trim()}
                        className="w-full py-3 rounded-xl bg-brand-600 disabled:bg-gray-200 dark:disabled:bg-gray-700 disabled:text-gray-400 dark:disabled:text-gray-500 disabled:cursor-not-allowed text-white font-semibold hover:bg-brand-700 disabled:hover:bg-gray-200 transition-colors"
                    >
                        Ver respuesta
                    </button>
                    <p className="text-center text-xs text-gray-400 dark:text-gray-500">Ctrl + Enter para revelar</p>
                </div>
            ) : (
                <div className="space-y-3">
                    {/* Comparación */}
                    <div className="grid grid-cols-2 gap-3">
                        <div className="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-xl p-4">
                            <p className="text-xs font-semibold text-amber-700 dark:text-amber-400 uppercase tracking-wide mb-2">
                                Tu respuesta
                            </p>
                            <p className="text-sm text-gray-800 dark:text-gray-200 leading-relaxed whitespace-pre-wrap">
                                {texto}
                            </p>
                        </div>
                        <div className="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-xl p-4">
                            <p className="text-xs font-semibold text-green-700 dark:text-green-400 uppercase tracking-wide mb-2">
                                Respuesta correcta
                            </p>
                            <p className="text-sm text-gray-800 dark:text-gray-200 leading-relaxed">
                                {card.reverso}
                            </p>
                        </div>
                    </div>

                    <p className="text-center text-sm text-gray-500 dark:text-gray-400">
                        ¿Qué tal salió?
                    </p>

                    {/* Botones de autoevaluación */}
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
                </div>
            )}
        </div>
    );
}

/* ── Pantalla de inicio ─────────────────────────────────────────────────── */

function PantallaInicio({ numCards, onEmpezar }) {
    return (
        <div className="text-center space-y-6 py-4">
            <div className="text-5xl">🎴</div>
            <div className="space-y-1">
                <h2 className="text-xl font-bold text-gray-900 dark:text-gray-100">
                    {numCards} fichas para repasar
                </h2>
                <p className="text-sm text-gray-500 dark:text-gray-400">
                    ¿Cómo quieres empezar?
                </p>
            </div>
            <div className="flex flex-col gap-3">
                <button
                    onClick={() => onEmpezar('estudiar')}
                    className="w-full py-4 rounded-2xl bg-brand-600 text-white font-semibold hover:bg-brand-700 transition-colors text-left px-6 flex items-start gap-4"
                >
                    <span className="text-2xl mt-0.5">📖</span>
                    <div>
                        <div className="font-semibold">Estudiar primero</div>
                        <div className="text-sm text-brand-100 font-normal mt-0.5">
                            Lee todas las fichas y luego escribe las respuestas
                        </div>
                    </div>
                </button>
                <button
                    onClick={() => onEmpezar('repasar')}
                    className="w-full py-4 rounded-2xl border-2 border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 font-semibold hover:border-brand-400 transition-colors text-left px-6 flex items-start gap-4"
                >
                    <span className="text-2xl mt-0.5">✍️</span>
                    <div>
                        <div className="font-semibold text-gray-900 dark:text-gray-100">Solo repasar</div>
                        <div className="text-sm text-gray-500 dark:text-gray-400 font-normal mt-0.5">
                            Escribe la respuesta directamente sin estudiar antes
                        </div>
                    </div>
                </button>
            </div>
        </div>
    );
}

/* ── Componente principal ────────────────────────────────────────────────── */

export default function FlashcardsUE() {
    const navigate = useNavigate();
    const qc = useQueryClient();
    const [categoria, setCategoria] = useState('');
    const [fase, setFase] = useState('inicio'); // 'inicio' | 'estudio' | 'repaso' | 'completado'
    const [indice, setIndice] = useState(0);
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

    const resetSesion = () => {
        setFase('inicio');
        setIndice(0);
        setRespondidas(0);
        qc.invalidateQueries({ queryKey: ['epso-flashcards-proximas'] });
    };

    const onEmpezar = (modo) => {
        setIndice(0);
        setFase(modo === 'estudiar' ? 'estudio' : 'repaso');
    };

    const onSiguienteEstudio = () => {
        if (indice + 1 < cards.length) {
            setIndice(i => i + 1);
        } else {
            setIndice(0);
            setFase('repaso');
        }
    };

    const onRespuesta = (id, dificultad) => {
        repasarMutation.mutate({ id, dificultad });
        setRespondidas(r => r + 1);
        if (indice + 1 < cards.length) {
            setIndice(i => i + 1);
        } else {
            setFase('completado');
        }
    };

    if (fase === 'completado') {
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
                        onClick={resetSesion}
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
                    {fase !== 'inicio' && (
                        <span className={`text-xs font-semibold px-2 py-0.5 rounded-full ${
                            fase === 'estudio'
                                ? 'bg-brand-100 dark:bg-brand-900/40 text-brand-700 dark:text-brand-300'
                                : 'bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-300'
                        }`}>
                            {fase === 'estudio' ? '📖 Estudio' : '✍️ Repaso'}
                        </span>
                    )}
                </div>
                {fase !== 'inicio' && cards.length > 0 && (
                    <span className="text-sm text-gray-500 dark:text-gray-400">
                        {indice + 1}/{cards.length}
                    </span>
                )}
                {fase === 'inicio' && <div className="w-12" />}
            </div>

            {/* Filtros de categoría */}
            <div className="flex flex-wrap gap-2">
                {CATEGORIAS.map(c => (
                    <button
                        key={c.key}
                        onClick={() => { setCategoria(c.key); resetSesion(); }}
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
                    {fase !== 'inicio' && (
                        <div className="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-1.5">
                            <div
                                className={`h-1.5 rounded-full transition-all duration-500 ${fase === 'estudio' ? 'bg-brand-500' : 'bg-amber-500'}`}
                                style={{ width: `${(indice / cards.length) * 100}%` }}
                            />
                        </div>
                    )}

                    {fase === 'inicio' && (
                        <PantallaInicio numCards={cards.length} onEmpezar={onEmpezar} />
                    )}
                    {fase === 'estudio' && (
                        <TarjetaEstudio
                            key={`estudio-${indice}`}
                            card={cards[indice]}
                            onSiguiente={onSiguienteEstudio}
                            esUltima={indice + 1 === cards.length}
                        />
                    )}
                    {fase === 'repaso' && (
                        <TarjetaRepaso
                            key={`repaso-${indice}`}
                            card={cards[indice]}
                            onRespuesta={onRespuesta}
                        />
                    )}
                </>
            )}
        </div>
    );
}
