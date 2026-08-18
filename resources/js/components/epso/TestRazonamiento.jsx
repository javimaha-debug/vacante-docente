import { useState, useEffect, useCallback } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import api from '../../lib/api';

const CONFIG = {
    verbal:   { preguntas: 4, minutos: 7,  label: 'Verbal',   icon: '📝' },
    numerico: { preguntas: 3, minutos: 10, label: 'Numérico', icon: '📊' },
    abstracto:{ preguntas: 2, minutos: 6,  label: 'Abstracto',icon: '🔷' },
};

const LETRAS = ['A', 'B', 'C', 'D', 'E'];

function useTemporizador() {
    const [segundos, setSegundos] = useState(0);
    const [activo, setActivo] = useState(false);

    useEffect(() => {
        if (!activo) return;
        const id = setInterval(() => setSegundos(s => s + 1), 1000);
        return () => clearInterval(id);
    }, [activo]);

    const iniciar = useCallback(() => { setSegundos(0); setActivo(true); }, []);
    const parar   = useCallback(() => { setActivo(false); return segundos; }, [segundos]);
    const reset   = useCallback(() => { setSegundos(0); }, []);

    return { segundos, iniciar, parar, reset };
}

function fmt(s) {
    const m = Math.floor(s / 60);
    const ss = s % 60;
    return `${m}:${ss.toString().padStart(2, '0')}`;
}

function BarraProgreso({ actual, total }) {
    const pct = total > 0 ? ((actual) / total) * 100 : 0;
    return (
        <div className="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
            <div
                className="bg-brand-500 h-2 rounded-full transition-all duration-500"
                style={{ width: `${pct}%` }}
            />
        </div>
    );
}

function PantallaFinal({ resultados, tipo, onRepetir, onVolver }) {
    const cfg = CONFIG[tipo] || CONFIG.verbal;
    const correctas = resultados.filter(r => r.correcta).length;
    const total = resultados.length;
    const tiempoTotal = resultados.reduce((acc, r) => acc + r.segundos, 0);
    const promedio = total > 0 ? Math.round(tiempoTotal / total) : 0;
    const pct = total > 0 ? Math.round((correctas / total) * 100) : 0;

    const emoji = pct >= 80 ? '🎉' : pct >= 60 ? '💪' : '📚';

    return (
        <div className="max-w-lg mx-auto px-4 py-12 text-center space-y-8">
            <div className="space-y-2">
                <div className="text-6xl">{emoji}</div>
                <h2 className="text-2xl font-bold text-gray-900 dark:text-gray-100">
                    Test completado
                </h2>
                <p className="text-gray-500 dark:text-gray-400">
                    {cfg.label} · {total} preguntas · {fmt(tiempoTotal)}
                </p>
            </div>

            <div className="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl p-6 space-y-4">
                <div className="text-5xl font-bold text-brand-600 dark:text-brand-400">
                    {correctas}/{total}
                </div>
                <div className="text-lg text-gray-600 dark:text-gray-300">
                    {pct}% de acierto
                </div>
                <div className="w-full bg-gray-100 dark:bg-gray-700 rounded-full h-3">
                    <div
                        className="h-3 rounded-full transition-all duration-700"
                        style={{
                            width: `${pct}%`,
                            backgroundColor: pct >= 80 ? '#22c55e' : pct >= 60 ? '#f59e0b' : '#ef4444',
                        }}
                    />
                </div>
                <p className="text-sm text-gray-500 dark:text-gray-400">
                    Tiempo promedio: {promedio} seg/pregunta
                </p>
            </div>

            {/* Desglose */}
            <div className="space-y-2 text-left">
                {resultados.map((r, i) => (
                    <div
                        key={i}
                        className={`flex items-start gap-3 rounded-xl px-4 py-3 ${
                            r.correcta
                                ? 'bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800'
                                : 'bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800'
                        }`}
                    >
                        <span className="text-lg mt-0.5">{r.correcta ? '✅' : '❌'}</span>
                        <div className="flex-1 min-w-0">
                            <p className="text-sm text-gray-800 dark:text-gray-200 line-clamp-2">
                                {r.pregunta}
                            </p>
                            <p className="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                {r.segundos}s · Resp. {LETRAS[r.respuestaUsuario ?? -1] ?? '—'}
                            </p>
                        </div>
                    </div>
                ))}
            </div>

            <div className="flex gap-3">
                <button
                    onClick={onVolver}
                    className="flex-1 py-3 rounded-xl border border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-300 font-medium hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors"
                >
                    ← Volver
                </button>
                <button
                    onClick={onRepetir}
                    className="flex-1 py-3 rounded-xl bg-brand-600 text-white font-medium hover:bg-brand-700 transition-colors"
                >
                    Repetir test
                </button>
            </div>
        </div>
    );
}

export default function TestRazonamiento() {
    const { tipo } = useParams();
    const navigate = useNavigate();
    const cfg = CONFIG[tipo] || CONFIG.verbal;

    const [fase, setFase]             = useState('cargando'); // cargando | en_curso | fin
    const [preguntas, setPreguntas]   = useState([]);
    const [indice, setIndice]         = useState(0);
    const [seleccion, setSeleccion]   = useState(null);
    const [resultados, setResultados] = useState([]);
    const { segundos, iniciar, parar, reset } = useTemporizador();

    const cargarPreguntas = useCallback(async () => {
        setFase('cargando');
        setResultados([]);
        setIndice(0);
        setSeleccion(null);

        try {
            const peticiones = Array.from({ length: cfg.preguntas }, () =>
                api.get(`/epso/test/${tipo}`).then(r => r.data)
            );
            const datos = await Promise.all(peticiones);
            setPreguntas(datos);
            setFase('en_curso');
            iniciar();
        } catch {
            setFase('error');
        }
    }, [tipo, cfg.preguntas, iniciar]);

    useEffect(() => { cargarPreguntas(); }, [cargarPreguntas]);

    const preguntaActual = preguntas[indice];

    const confirmarRespuesta = () => {
        if (seleccion === null || !preguntaActual) return;
        const tiempoRespuesta = parar();
        const correcta = seleccion === preguntaActual.respuesta_correcta;
        setResultados(prev => [...prev, {
            pregunta: preguntaActual.pregunta,
            correcta,
            segundos: tiempoRespuesta,
            respuestaUsuario: seleccion,
        }]);

        if (indice + 1 < preguntas.length) {
            setIndice(i => i + 1);
            setSeleccion(null);
            reset();
            iniciar();
        } else {
            setFase('fin');
        }
    };

    if (fase === 'cargando') {
        return (
            <div className="flex items-center justify-center min-h-64">
                <div className="text-center space-y-3">
                    <div className="text-4xl animate-pulse">{cfg.icon}</div>
                    <p className="text-gray-500 dark:text-gray-400">Cargando preguntas…</p>
                </div>
            </div>
        );
    }

    if (fase === 'error') {
        return (
            <div className="max-w-md mx-auto px-4 py-12 text-center space-y-4">
                <div className="text-4xl">⚠️</div>
                <p className="text-gray-700 dark:text-gray-300">No se pudieron cargar las preguntas de tipo <strong>{tipo}</strong>.</p>
                <button
                    onClick={() => navigate('/dashboard/epso')}
                    className="px-6 py-2 rounded-xl bg-brand-600 text-white font-medium hover:bg-brand-700 transition-colors"
                >
                    Volver al inicio
                </button>
            </div>
        );
    }

    if (fase === 'fin') {
        return (
            <PantallaFinal
                resultados={resultados}
                tipo={tipo}
                onRepetir={cargarPreguntas}
                onVolver={() => navigate('/dashboard/epso')}
            />
        );
    }

    if (!preguntaActual) return null;

    const opciones = Array.isArray(preguntaActual.opciones) ? preguntaActual.opciones : [];
    const superado = segundos > (preguntaActual.tiempo_esperado_segundos || 90);

    return (
        <div className="max-w-2xl mx-auto px-4 py-6 space-y-6">
            {/* Cabecera */}
            <div className="space-y-3">
                <div className="flex items-center justify-between">
                    <button
                        onClick={() => navigate('/dashboard/epso')}
                        className="text-sm text-gray-500 hover:text-gray-700 dark:hover:text-gray-300 flex items-center gap-1 transition-colors"
                    >
                        <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" />
                        </svg>
                        Cancelar
                    </button>
                    <div className="flex items-center gap-2">
                        <span className="text-sm font-medium text-gray-600 dark:text-gray-400">
                            {indice + 1} / {preguntas.length}
                        </span>
                        <span
                            className={`text-lg font-mono font-bold tabular-nums ${
                                superado ? 'text-red-500 dark:text-red-400' : 'text-brand-600 dark:text-brand-400'
                            }`}
                        >
                            {fmt(segundos)}
                        </span>
                    </div>
                </div>
                <BarraProgreso actual={indice} total={preguntas.length} />
                <div className="flex items-center gap-2">
                    <span className="text-lg">{cfg.icon}</span>
                    <span className="text-sm font-medium text-gray-500 dark:text-gray-400">{cfg.label}</span>
                    {superado && (
                        <span className="ml-auto text-xs text-red-500 dark:text-red-400 font-medium">
                            Tiempo recomendado superado
                        </span>
                    )}
                </div>
            </div>

            {/* Pregunta */}
            <div className="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl p-6">
                <p className="text-[1.125rem] leading-relaxed text-gray-900 dark:text-gray-100 font-medium">
                    {preguntaActual.pregunta}
                </p>
            </div>

            {/* Opciones */}
            <div className="space-y-3">
                {opciones.map((opcion, i) => {
                    const seleccionada = seleccion === i;
                    return (
                        <button
                            key={i}
                            onClick={() => setSeleccion(i)}
                            className={`w-full flex items-start gap-4 rounded-xl border-2 p-4 text-left transition-all ${
                                seleccionada
                                    ? 'border-brand-500 bg-brand-50 dark:bg-brand-900/20'
                                    : 'border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 hover:border-brand-300 dark:hover:border-brand-700'
                            }`}
                        >
                            <span
                                className={`flex-shrink-0 w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold transition-colors ${
                                    seleccionada
                                        ? 'bg-brand-500 text-white'
                                        : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300'
                                }`}
                            >
                                {LETRAS[i]}
                            </span>
                            <span className={`text-base leading-snug pt-0.5 ${
                                seleccionada
                                    ? 'text-brand-800 dark:text-brand-200 font-medium'
                                    : 'text-gray-700 dark:text-gray-200'
                            }`}>
                                {opcion}
                            </span>
                        </button>
                    );
                })}
            </div>

            {/* Botón siguiente */}
            <button
                onClick={confirmarRespuesta}
                disabled={seleccion === null}
                className="w-full py-4 rounded-2xl bg-brand-600 disabled:bg-gray-300 dark:disabled:bg-gray-700 disabled:cursor-not-allowed text-white disabled:text-gray-500 dark:disabled:text-gray-400 text-lg font-semibold hover:bg-brand-700 disabled:hover:bg-gray-300 dark:disabled:hover:bg-gray-700 transition-colors"
            >
                {indice + 1 < preguntas.length ? 'Siguiente →' : 'Ver resultados'}
            </button>
        </div>
    );
}
