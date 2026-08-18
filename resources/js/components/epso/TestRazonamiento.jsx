import { useState, useEffect, useCallback, useRef } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import api from '../../lib/api';

const CONFIG = {
    verbal:    { preguntas: 4, minutos: 7,  label: 'Verbal',    icon: '📝' },
    numerico:  { preguntas: 3, minutos: 10, label: 'Numérico',  icon: '📊' },
    abstracto: { preguntas: 2, minutos: 6,  label: 'Abstracto', icon: '🔷' },
};

const LETRAS = ['A', 'B', 'C', 'D', 'E'];

function useTemporizador() {
    const [segundos, setSegundos] = useState(0);
    const [activo, setActivo] = useState(false);
    const ref = useRef(0);

    useEffect(() => {
        if (!activo) return;
        const id = setInterval(() => {
            ref.current += 1;
            setSegundos(ref.current);
        }, 1000);
        return () => clearInterval(id);
    }, [activo]);

    const iniciar = useCallback(() => { ref.current = 0; setSegundos(0); setActivo(true); }, []);
    const parar   = useCallback(() => { setActivo(false); return ref.current; }, []);

    return { segundos, iniciar, parar };
}

function fmt(s) {
    const m = Math.floor(s / 60);
    const ss = s % 60;
    return `${m}:${ss.toString().padStart(2, '0')}`;
}

function BarraProgreso({ actual, total }) {
    return (
        <div className="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
            <div
                className="bg-brand-500 h-2 rounded-full transition-all duration-500"
                style={{ width: total > 0 ? `${(actual / total) * 100}%` : '0%' }}
            />
        </div>
    );
}

/* ── Pantalla de resultados ─────────────────────────────────────────────── */

function DetalleRespuesta({ r, i }) {
    const [mostrarExp, setMostrarExp] = useState(false);
    const correctaLetra = LETRAS[r.respuesta_correcta] ?? '—';
    const usuarioLetra  = LETRAS[r.respuesta_usuario] ?? '—';

    return (
        <div className={`rounded-xl border-2 overflow-hidden ${r.correcta ? 'border-green-200 dark:border-green-800' : 'border-red-200 dark:border-red-800'}`}>
            <div className={`flex items-start gap-3 px-4 py-3 ${r.correcta ? 'bg-green-50 dark:bg-green-900/20' : 'bg-red-50 dark:bg-red-900/20'}`}>
                <span className="text-xl mt-0.5 flex-shrink-0">{r.correcta ? '✅' : '❌'}</span>
                <div className="flex-1 min-w-0 space-y-1">
                    <p className="text-sm font-medium text-gray-800 dark:text-gray-200 leading-snug">
                        {i + 1}. {r.pregunta}
                    </p>
                    <div className="flex flex-wrap gap-3 text-xs text-gray-500 dark:text-gray-400">
                        <span>⏱ {r.segundos}s</span>
                        {!r.correcta && (
                            <>
                                <span>Tu resp: <strong className="text-red-600 dark:text-red-400">{usuarioLetra}</strong></span>
                                <span>Correcta: <strong className="text-green-600 dark:text-green-400">{correctaLetra}</strong></span>
                            </>
                        )}
                        {!r.correcta && r.tipo_error && (
                            <span className="bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-300 px-2 py-0.5 rounded-full font-medium">
                                {r.tipo_error}
                            </span>
                        )}
                    </div>
                </div>
            </div>

            {!r.correcta && r.explicacion && (
                <div className="px-4 py-2 bg-white dark:bg-gray-800 border-t border-gray-100 dark:border-gray-700">
                    {mostrarExp ? (
                        <div className="text-sm text-gray-700 dark:text-gray-300 leading-relaxed">
                            {r.explicacion}
                            <button
                                onClick={() => setMostrarExp(false)}
                                className="block mt-1 text-xs text-brand-600 dark:text-brand-400 hover:underline"
                            >
                                Ocultar explicación ▲
                            </button>
                        </div>
                    ) : (
                        <button
                            onClick={() => setMostrarExp(true)}
                            className="text-xs text-brand-600 dark:text-brand-400 hover:underline py-0.5"
                        >
                            Ver explicación ▼
                        </button>
                    )}
                </div>
            )}
        </div>
    );
}

function PantallaFinal({ resultados, tipo, guardado, onRepetir, onVolver }) {
    const cfg = CONFIG[tipo] || CONFIG.verbal;
    const correctas  = resultados.filter(r => r.correcta).length;
    const total      = resultados.length;
    const tiempoTotal = resultados.reduce((a, r) => a + r.segundos, 0);
    const promedio   = total > 0 ? Math.round(tiempoTotal / total) : 0;
    const pct        = total > 0 ? Math.round((correctas / total) * 100) : 0;
    const emoji      = pct >= 80 ? '🎉' : pct >= 60 ? '💪' : '📚';
    const colorBarra = pct >= 80 ? '#22c55e' : pct >= 60 ? '#f59e0b' : '#ef4444';

    return (
        <div className="max-w-lg mx-auto px-4 py-10 space-y-6">
            {/* Cabecera */}
            <div className="text-center space-y-1">
                <div className="text-5xl">{emoji}</div>
                <h2 className="text-2xl font-bold text-gray-900 dark:text-gray-100">
                    Test completado
                </h2>
                <p className="text-sm text-gray-500 dark:text-gray-400">
                    {cfg.label} · {total} preguntas · {fmt(tiempoTotal)}
                    {guardado && <span className="ml-2 text-green-600 dark:text-green-400">✓ Guardado</span>}
                </p>
            </div>

            {/* Score */}
            <div className="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl p-6 text-center space-y-3">
                <div className="text-5xl font-bold text-brand-600 dark:text-brand-400">
                    {correctas}/{total}
                </div>
                <div className="text-lg text-gray-600 dark:text-gray-300">{pct}% de acierto</div>
                <div className="w-full bg-gray-100 dark:bg-gray-700 rounded-full h-3">
                    <div className="h-3 rounded-full transition-all duration-700" style={{ width: `${pct}%`, backgroundColor: colorBarra }} />
                </div>
                <p className="text-sm text-gray-500 dark:text-gray-400">
                    Tiempo promedio: <strong>{promedio} seg</strong>/pregunta
                </p>
            </div>

            {/* Desglose por pregunta */}
            <div className="space-y-3">
                <h3 className="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">
                    Análisis de respuestas
                </h3>
                {resultados.map((r, i) => <DetalleRespuesta key={i} r={r} i={i} />)}
            </div>

            {/* Acciones */}
            <div className="flex gap-3 pt-2">
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

/* ── Componente principal ────────────────────────────────────────────────── */

export default function TestRazonamiento() {
    const { tipo } = useParams();
    const navigate = useNavigate();
    const qc = useQueryClient();
    const cfg = CONFIG[tipo] || CONFIG.verbal;

    const [fase, setFase]             = useState('cargando');
    const [preguntas, setPreguntas]   = useState([]);
    const [indice, setIndice]         = useState(0);
    const [seleccion, setSeleccion]   = useState(null);
    const [resultados, setResultados] = useState([]);
    const [guardado, setGuardado]     = useState(false);
    const fechaInicioRef              = useRef(null);
    const { segundos, iniciar, parar } = useTemporizador();

    const guardarSesion = useMutation({
        mutationFn: (payload) => api.post('/epso/sesion/guardar', payload),
        onSuccess: () => {
            setGuardado(true);
            qc.invalidateQueries({ queryKey: ['epso-progreso'] });
        },
    });

    const cargarPreguntas = useCallback(async () => {
        setFase('cargando');
        setResultados([]);
        setIndice(0);
        setSeleccion(null);
        setGuardado(false);

        try {
            const datos = await Promise.all(
                Array.from({ length: cfg.preguntas }, () =>
                    api.get(`/epso/test/${tipo}`).then(r => r.data)
                )
            );
            setPreguntas(datos);
            fechaInicioRef.current = new Date().toISOString();
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

        const nuevoResultado = {
            pregunta          : preguntaActual.pregunta,
            opciones          : preguntaActual.opciones,
            respuesta_correcta: preguntaActual.respuesta_correcta,
            respuesta_usuario : seleccion,
            correcta,
            segundos          : tiempoRespuesta,
            tipo_error        : preguntaActual.tipo_error ?? null,
            explicacion       : preguntaActual.explicacion ?? null,
            pregunta_id       : preguntaActual.id,
        };

        const nuevosResultados = [...resultados, nuevoResultado];

        if (indice + 1 < preguntas.length) {
            setResultados(nuevosResultados);
            setIndice(i => i + 1);
            setSeleccion(null);
            iniciar();
        } else {
            // Test completado — guardar sesión
            setResultados(nuevosResultados);
            setFase('fin');

            const tiempoTotal = nuevosResultados.reduce((a, r) => a + r.segundos, 0);
            const errores = nuevosResultados
                .filter(r => !r.correcta)
                .map(r => ({ pregunta_id: r.pregunta_id, tipo_error: r.tipo_error }));

            guardarSesion.mutate({
                tipo_razonamiento      : tipo,
                fecha_inicio           : fechaInicioRef.current,
                fecha_fin              : new Date().toISOString(),
                preguntas_respondidas  : nuevosResultados.length,
                correctas              : nuevosResultados.filter(r => r.correcta).length,
                tiempo_total_segundos  : tiempoTotal,
                errores,
            });
        }
    };

    /* ── Pantallas ─────────────────────────────────────────────────────── */

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
                <p className="text-gray-700 dark:text-gray-300">
                    No se pudieron cargar preguntas de tipo <strong>{tipo}</strong>.
                </p>
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
                guardado={guardado}
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
                    <div className="flex items-center gap-3">
                        <span className="text-sm font-medium text-gray-600 dark:text-gray-400">
                            {indice + 1} / {preguntas.length}
                        </span>
                        <span className={`text-lg font-mono font-bold tabular-nums min-w-[3rem] text-right ${superado ? 'text-red-500 dark:text-red-400' : 'text-brand-600 dark:text-brand-400'}`}>
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
                    const sel = seleccion === i;
                    return (
                        <button
                            key={i}
                            onClick={() => setSeleccion(i)}
                            className={`w-full flex items-start gap-4 rounded-xl border-2 p-4 text-left transition-all ${
                                sel
                                    ? 'border-brand-500 bg-brand-50 dark:bg-brand-900/20'
                                    : 'border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 hover:border-brand-300 dark:hover:border-brand-700'
                            }`}
                        >
                            <span className={`flex-shrink-0 w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold transition-colors ${sel ? 'bg-brand-500 text-white' : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300'}`}>
                                {LETRAS[i]}
                            </span>
                            <span className={`text-base leading-snug pt-0.5 ${sel ? 'text-brand-800 dark:text-brand-200 font-medium' : 'text-gray-700 dark:text-gray-200'}`}>
                                {opcion}
                            </span>
                        </button>
                    );
                })}
            </div>

            {/* Siguiente */}
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
