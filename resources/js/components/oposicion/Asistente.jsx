import { useEffect, useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import { useQueryClient } from '@tanstack/react-query';
import api from '../../lib/api';
import { useConversations } from './asistente/useAsistente';
import ChatView from './asistente/ChatView';
import FlashcardsView from './asistente/FlashcardsView';
import Scorecard from './asistente/Scorecard';

const MODES = [
    { key: 'chat', label: 'Chat', icon: '💬' },
    { key: 'flashcards', label: 'Flashcards', icon: '🃏' },
    { key: 'simulacro', label: 'Simulacro', icon: '📝' },
    { key: 'progreso', label: 'Progreso', icon: '📊' },
];

function fecha(d) {
    if (!d) return '';
    try { return new Date(d).toLocaleDateString('es-ES', { day: '2-digit', month: 'short' }); } catch { return ''; }
}

export default function Asistente() {
    const qc = useQueryClient();
    const [mode, setMode] = useState('chat');
    const [conversationId, setConversationId] = useState(null);
    const conversations = useConversations();
    const [searchParams, setSearchParams] = useSearchParams();

    // Deep link from "Estudiar con IA" (Mi Preparación): ?c={conversationId}.
    useEffect(() => {
        const c = searchParams.get('c');
        if (c) {
            setMode('chat');
            setConversationId(Number(c));
            setSearchParams({}, { replace: true });
        }
    }, [searchParams, setSearchParams]);

    const newChat = () => { setMode('chat'); setConversationId(null); };

    const openConversation = (id) => { setMode('chat'); setConversationId(id); };

    const del = async (id, e) => {
        e.stopPropagation();
        await api.delete(`/ai/conversations/${id}`);
        if (id === conversationId) setConversationId(null);
        qc.invalidateQueries({ queryKey: ['ai-conversations'] });
    };

    return (
        <div className="mx-auto flex h-[calc(100vh-8rem)] max-w-6xl flex-col gap-4 lg:flex-row">
            {/* Left panel */}
            <aside className="flex w-full shrink-0 flex-col rounded-2xl bg-white p-3 shadow-sm ring-1 ring-slate-200 lg:w-72">
                <button onClick={newChat} className="rounded-lg bg-brand-600 px-3 py-2 text-sm font-semibold text-white hover:bg-brand-700">+ Nueva conversación</button>

                <div className="mt-3 grid grid-cols-2 gap-1">
                    {MODES.map((m) => (
                        <button key={m.key} onClick={() => { setMode(m.key); if (m.key !== 'chat') setConversationId(null); }}
                            className={`flex items-center justify-center gap-1 rounded-lg px-2 py-1.5 text-xs font-semibold ${mode === m.key ? 'bg-brand-100 text-brand-700' : 'text-slate-500 hover:bg-slate-50'}`}>
                            <span>{m.icon}</span>{m.label}
                        </button>
                    ))}
                </div>

                <div className="mt-3 flex-1 overflow-y-auto">
                    <p className="px-1 text-[11px] font-semibold uppercase tracking-wide text-slate-400">Conversaciones</p>
                    <div className="mt-1 space-y-0.5">
                        {(conversations.data?.data ?? []).map((c) => (
                            <div key={c.id} onClick={() => openConversation(c.id)} className={`group flex cursor-pointer items-center gap-1 rounded-lg px-2 py-1.5 text-sm ${conversationId === c.id ? 'bg-brand-50 text-brand-700' : 'text-slate-600 hover:bg-slate-50'}`}>
                                <span className="flex-1 truncate">{c.title ?? 'Sin título'}</span>
                                <span className="text-[10px] text-slate-400">{fecha(c.updated_at)}</span>
                                <button onClick={(e) => del(c.id, e)} className="opacity-0 transition group-hover:opacity-100" title="Eliminar">🗑️</button>
                            </div>
                        ))}
                        {(conversations.data?.data ?? []).length === 0 && <p className="px-2 py-1 text-xs text-slate-400">Sin conversaciones aún.</p>}
                    </div>
                </div>
            </aside>

            {/* Main */}
            <main className="min-w-0 flex-1 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
                {mode === 'chat' && <ChatView conversationId={conversationId} onConversationCreated={setConversationId} />}
                {mode === 'flashcards' && <FlashcardsView mode="flashcards" />}
                {mode === 'simulacro' && <FlashcardsView mode="simulacro" />}
                {mode === 'progreso' && <Scorecard />}
            </main>
        </div>
    );
}
