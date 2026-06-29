import { useEffect, useRef, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import api from '../../../lib/api';
import { useDocuments } from './useAsistente';

// Chat mode: a conversation thread with RAG citations under assistant turns.
export default function ChatView({ conversationId, onConversationCreated }) {
    const qc = useQueryClient();
    const [contextType, setContextType] = useState('free');
    const [docIds, setDocIds] = useState([]);
    const [input, setInput] = useState('');
    const [localMsgs, setLocalMsgs] = useState([]);
    const scrollRef = useRef(null);
    const docs = useDocuments();

    const convo = useQuery({
        queryKey: ['ai-conversation', conversationId],
        queryFn: async () => (await api.get(`/ai/conversations/${conversationId}`)).data,
        enabled: Boolean(conversationId),
    });

    useEffect(() => {
        setLocalMsgs(convo.data?.messages ?? []);
    }, [convo.data]);

    useEffect(() => {
        scrollRef.current?.scrollTo({ top: scrollRef.current.scrollHeight, behavior: 'smooth' });
    }, [localMsgs]);

    const send = useMutation({
        mutationFn: async (text) => {
            let id = conversationId;
            if (!id) {
                const created = (await api.post('/ai/conversations', { mode: 'chat', context_type: contextType })).data;
                id = created.id;
                onConversationCreated?.(id);
                qc.invalidateQueries({ queryKey: ['ai-conversations'] });
            }
            return (await api.post(`/ai/conversations/${id}/message`, {
                message: text,
                document_ids: contextType === 'document' ? docIds : undefined,
            })).data;
        },
        onSuccess: (data) => {
            setLocalMsgs((m) => [...m, { ...data.message, _citations: data.citations }]);
        },
    });

    const submit = (e) => {
        e.preventDefault();
        const text = input.trim();
        if (!text || send.isPending) return;
        setLocalMsgs((m) => [...m, { role: 'user', content: text, id: `tmp-${Date.now()}` }]);
        setInput('');
        send.mutate(text);
    };

    const readonly = Boolean(conversationId); // context fixed once a convo exists

    return (
        <div className="flex h-full flex-col">
            {!readonly && (
                <div className="flex flex-wrap items-center gap-2 border-b border-slate-100 pb-3">
                    <span className="text-xs font-medium text-slate-400">Contexto:</span>
                    {[['free', 'Libre'], ['temario', 'Mi temario'], ['document', 'Mis apuntes']].map(([k, l]) => (
                        <button key={k} onClick={() => setContextType(k)} className={`rounded-full px-3 py-1 text-xs font-semibold ${contextType === k ? 'bg-brand-600 text-white' : 'bg-slate-100 text-slate-600'}`}>{l}</button>
                    ))}
                    {contextType === 'document' && (
                        <select multiple value={docIds.map(String)} onChange={(e) => setDocIds([...e.target.selectedOptions].map((o) => Number(o.value)))} className="ml-2 rounded-lg border border-slate-200 px-2 py-1 text-xs" size={1}>
                            {(docs.data?.data ?? []).map((d) => <option key={d.id} value={d.id}>{d.name}</option>)}
                        </select>
                    )}
                </div>
            )}

            <div ref={scrollRef} className="scroll-thin flex-1 space-y-3 overflow-y-auto py-4">
                {localMsgs.length === 0 && (
                    <p className="mt-8 text-center text-sm text-slate-400">Pregunta lo que quieras sobre tu temario o tus apuntes.</p>
                )}
                {localMsgs.map((m, i) => (
                    <div key={m.id ?? i} className={`flex ${m.role === 'user' ? 'justify-end' : 'justify-start'}`}>
                        <div className={`max-w-[80%] rounded-2xl px-4 py-2 text-sm ${m.role === 'user' ? 'bg-brand-600 text-white' : 'bg-slate-100 text-slate-800'}`}>
                            <p className="whitespace-pre-wrap">{m.content}</p>
                            {m.role === 'assistant' && (m._citations?.length || m.chunks_used?.length) ? (
                                <div className="mt-2 space-y-0.5 border-t border-slate-200 pt-1">
                                    {(m._citations ?? m.chunks_used ?? []).map((c, j) => (
                                        <p key={j} className="text-[11px] text-slate-500">📄 “{c.document_name}”{c.page_number ? `, p.${c.page_number}` : ''}</p>
                                    ))}
                                </div>
                            ) : null}
                        </div>
                    </div>
                ))}
                {send.isPending && <p className="text-center text-xs text-slate-400">Escribiendo…</p>}
                {send.isError && <p className="text-center text-xs text-rose-500">{send.error?.friendlyMessage ?? 'Error al enviar.'}</p>}
            </div>

            <form onSubmit={submit} className="flex items-center gap-2 border-t border-slate-100 pt-3">
                <input value={input} onChange={(e) => setInput(e.target.value)} placeholder="Escribe tu pregunta…" className="flex-1 rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-brand-400 focus:outline-none" />
                <button type="submit" disabled={send.isPending} className="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700 disabled:opacity-50">Enviar</button>
            </form>
        </div>
    );
}
