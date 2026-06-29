import { useState } from 'react';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import api from '../../../lib/api';
import { useTemas } from './useAsistente';

const DIFF = {
    basica: 'bg-emerald-100 text-emerald-700',
    media: 'bg-amber-100 text-amber-700',
    avanzada: 'bg-rose-100 text-rose-700',
};

// Flashcards mode (also reused for "Simulacro" via the self-graded flow).
export default function FlashcardsView({ mode = 'flashcards' }) {
    const qc = useQueryClient();
    const temas = useTemas();
    const [temaId, setTemaId] = useState('');
    const [count, setCount] = useState(10);
    const [cards, setCards] = useState(null);
    const [idx, setIdx] = useState(0);
    const [flipped, setFlipped] = useState(false);
    const [results, setResults] = useState([]); // booleans

    const generate = useMutation({
        mutationFn: async () => (await api.post('/ai/flashcards/from-tema', { tema_id: Number(temaId), count: Number(count) })).data,
        onSuccess: (data) => { setCards(data.data); setIdx(0); setFlipped(false); setResults([]); },
    });

    const saveScore = useMutation({
        mutationFn: async ({ correct, total }) => {
            if (mode === 'simulacro') {
                return api.post(`/ai/scores/${temaId}/simulacro`, { correct, total });
            }
            return api.post('/ai/flashcards/result', { tema_id: Number(temaId), correct, total });
        },
        onSuccess: () => qc.invalidateQueries({ queryKey: ['ai-scores'] }),
    });

    const answer = (ok) => {
        const next = [...results, ok];
        setResults(next);
        if (idx + 1 < cards.length) {
            setIdx(idx + 1);
            setFlipped(false);
        } else {
            const correct = next.filter(Boolean).length;
            saveScore.mutate({ correct, total: next.length });
        }
    };

    const reset = () => { setCards(null); setResults([]); setIdx(0); setFlipped(false); };

    // Setup screen.
    if (!cards) {
        return (
            <div className="mx-auto max-w-md space-y-4 py-6">
                <h2 className="text-lg font-bold text-slate-800">{mode === 'simulacro' ? 'Nuevo simulacro' : 'Generar flashcards'}</h2>
                <label className="block">
                    <span className="text-xs font-medium text-slate-400">Tema</span>
                    <select value={temaId} onChange={(e) => setTemaId(e.target.value)} className="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                        <option value="">Elige un tema…</option>
                        {(temas.data?.data ?? []).map((t) => <option key={t.id} value={t.id}>T{t.numero} · {t.titulo}</option>)}
                    </select>
                </label>
                <label className="block">
                    <span className="text-xs font-medium text-slate-400">{mode === 'simulacro' ? 'Preguntas' : 'Nº de tarjetas'}</span>
                    <select value={count} onChange={(e) => setCount(e.target.value)} className="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                        {[5, 10, 20].map((n) => <option key={n} value={n}>{n}</option>)}
                    </select>
                </label>
                <button onClick={() => generate.mutate()} disabled={!temaId || generate.isPending} className="w-full rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700 disabled:opacity-50">
                    {generate.isPending ? 'Generando…' : mode === 'simulacro' ? 'Empezar simulacro' : 'Generar'}
                </button>
                {generate.isError && <p className="text-sm text-rose-600">{generate.error?.friendlyMessage}</p>}
                {(temas.data?.data ?? []).length === 0 && <p className="text-xs text-slate-400">Añade temas en “Mi preparación” para generar tarjetas.</p>}
            </div>
        );
    }

    // Final summary.
    if (results.length === cards.length) {
        const correct = results.filter(Boolean).length;
        const pct = Math.round((correct / cards.length) * 100);
        const failedIdx = results.map((r, i) => (r ? null : i)).filter((i) => i !== null);
        return (
            <div className="mx-auto max-w-md space-y-4 py-8 text-center">
                <div className="text-5xl">{pct >= 70 ? '🎉' : '💪'}</div>
                <h2 className="text-2xl font-bold text-slate-800">{pct}%</h2>
                <p className="text-sm text-slate-500">{correct} de {cards.length} correctas · puntuación guardada</p>
                <div className="flex justify-center gap-2">
                    {failedIdx.length > 0 && (
                        <button onClick={() => { setCards(failedIdx.map((i) => cards[i])); setResults([]); setIdx(0); setFlipped(false); }} className="rounded-lg bg-amber-500 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-600">Repetir falladas ({failedIdx.length})</button>
                    )}
                    <button onClick={reset} className="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50">Terminar</button>
                </div>
            </div>
        );
    }

    // Active card.
    const card = cards[idx];
    return (
        <div className="mx-auto flex max-w-lg flex-col items-center gap-4 py-6">
            <p className="text-xs text-slate-400">{idx + 1} / {cards.length}</p>
            <button onClick={() => setFlipped((f) => !f)} className="relative h-56 w-full [perspective:1000px]">
                <div className={`relative h-full w-full rounded-2xl shadow-sm ring-1 ring-slate-200 transition-transform duration-500 [transform-style:preserve-3d] ${flipped ? '[transform:rotateY(180deg)]' : ''}`}>
                    <div className="absolute inset-0 flex flex-col items-center justify-center gap-3 rounded-2xl bg-white p-6 [backface-visibility:hidden]">
                        {card.dificultad && <span className={`rounded-full px-2 py-0.5 text-[11px] font-semibold ${DIFF[card.dificultad] ?? DIFF.media}`}>{card.dificultad}</span>}
                        <p className="text-center text-base font-medium text-slate-800">{card.pregunta}</p>
                        <span className="text-xs text-slate-400">Toca para ver la respuesta</span>
                    </div>
                    <div className="absolute inset-0 flex flex-col items-center justify-center gap-2 rounded-2xl bg-brand-50 p-6 [backface-visibility:hidden] [transform:rotateY(180deg)]">
                        <p className="text-center text-sm text-slate-700">{card.respuesta}</p>
                        {card.fuente && <span className="text-[11px] text-slate-400">📄 {card.fuente}</span>}
                    </div>
                </div>
            </button>
            <div className="flex gap-3">
                <button onClick={() => answer(false)} className="rounded-lg bg-rose-100 px-5 py-2 text-sm font-semibold text-rose-700 hover:bg-rose-200">❌ Fallé</button>
                <button onClick={() => answer(true)} className="rounded-lg bg-emerald-100 px-5 py-2 text-sm font-semibold text-emerald-700 hover:bg-emerald-200">✅ Acerté</button>
            </div>
        </div>
    );
}
