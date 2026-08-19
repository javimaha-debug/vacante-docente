import { useState, useRef, useEffect } from 'react';
import { useMutation, useQuery } from '@tanstack/react-query';
import api from '../../lib/api';

export default function ChatModal({ onClose, exerciseHistoryId = null, contextType = 'general' }) {
    const [input, setInput] = useState('');
    const [messages, setMessages] = useState([]);
    const bottomRef = useRef(null);

    // Load chat history
    const { data } = useQuery({
        queryKey: ['b2-chat-history', exerciseHistoryId],
        queryFn: async () => {
            const res = await api.get('/b2/chat/history', { params: { exercise_history_id: exerciseHistoryId } });
            return res.data;
        },
        staleTime: 0,
    });

    useEffect(() => {
        if (data?.messages?.length) {
            setMessages(data.messages);
        } else if (messages.length === 0) {
            setMessages([{
                role: 'assistant',
                content: exerciseHistoryId
                    ? '¡Hola! Soy tu tutor de inglés B2. ¿Quieres que te explique por qué fallaste este ejercicio?'
                    : '¡Hola! Soy tu tutor de inglés B2. ¿En qué puedo ayudarte hoy?',
            }]);
        }
    }, [data]);

    useEffect(() => {
        bottomRef.current?.scrollIntoView({ behavior: 'smooth' });
    }, [messages]);

    const sendMutation = useMutation({
        mutationFn: async (message) => {
            const res = await api.post('/b2/chat', {
                message,
                exercise_history_id: exerciseHistoryId,
                context_type: contextType,
            });
            return res.data;
        },
        onSuccess: (data) => {
            setMessages(prev => [...prev, { role: 'assistant', content: data.reply }]);
        },
    });

    const handleSend = () => {
        const msg = input.trim();
        if (!msg || sendMutation.isPending) return;
        setMessages(prev => [...prev, { role: 'user', content: msg }]);
        setInput('');
        sendMutation.mutate(msg);
    };

    const handleKeyDown = (e) => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            handleSend();
        }
    };

    const QUICK_QUESTIONS = exerciseHistoryId
        ? ['¿Por qué fallé?', 'Explícame la regla', '¿Cómo memorizar esto?']
        : ['¿Cómo mejorar en Grammar?', '¿Consejos para Writing?', '¿Cómo practicar Speaking?'];

    return (
        <div className="fixed inset-0 bg-black/50 z-50 flex items-end sm:items-center justify-center p-0 sm:p-4">
            <div className="bg-white dark:bg-gray-900 w-full sm:max-w-lg sm:rounded-2xl flex flex-col h-[85vh] sm:h-[600px] shadow-2xl">
                {/* Header */}
                <div className="flex items-center justify-between px-4 py-3 border-b border-gray-200 dark:border-gray-700">
                    <div className="flex items-center gap-2">
                        <div className="w-8 h-8 rounded-full bg-brand-600 flex items-center justify-center text-white text-sm font-bold">AI</div>
                        <div>
                            <p className="text-sm font-semibold text-gray-900 dark:text-gray-100">Tutor B2</p>
                            <p className="text-xs text-green-500">En línea</p>
                        </div>
                    </div>
                    <button onClick={onClose} className="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 p-1">
                        <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                {/* Messages */}
                <div className="flex-1 overflow-y-auto p-4 space-y-3">
                    {messages.map((msg, i) => (
                        <div key={i} className={`flex ${msg.role === 'user' ? 'justify-end' : 'justify-start'}`}>
                            <div className={`max-w-[80%] rounded-2xl px-4 py-2.5 text-sm leading-relaxed ${
                                msg.role === 'user'
                                    ? 'bg-brand-600 text-white rounded-br-sm'
                                    : 'bg-gray-100 dark:bg-gray-800 text-gray-800 dark:text-gray-200 rounded-bl-sm'
                            }`}>
                                {msg.content}
                            </div>
                        </div>
                    ))}
                    {sendMutation.isPending && (
                        <div className="flex justify-start">
                            <div className="bg-gray-100 dark:bg-gray-800 rounded-2xl rounded-bl-sm px-4 py-3">
                                <div className="flex gap-1">
                                    <span className="w-2 h-2 bg-gray-400 rounded-full animate-bounce" style={{ animationDelay: '0ms' }} />
                                    <span className="w-2 h-2 bg-gray-400 rounded-full animate-bounce" style={{ animationDelay: '150ms' }} />
                                    <span className="w-2 h-2 bg-gray-400 rounded-full animate-bounce" style={{ animationDelay: '300ms' }} />
                                </div>
                            </div>
                        </div>
                    )}
                    <div ref={bottomRef} />
                </div>

                {/* Quick questions */}
                {messages.length <= 2 && (
                    <div className="px-4 pb-2 flex gap-2 flex-wrap">
                        {QUICK_QUESTIONS.map((q) => (
                            <button
                                key={q}
                                onClick={() => {
                                    setMessages(prev => [...prev, { role: 'user', content: q }]);
                                    sendMutation.mutate(q);
                                }}
                                className="text-xs px-3 py-1.5 rounded-full border border-brand-300 dark:border-brand-700 text-brand-700 dark:text-brand-300 hover:bg-brand-50 dark:hover:bg-brand-950 transition-colors"
                            >
                                {q}
                            </button>
                        ))}
                    </div>
                )}

                {/* Input */}
                <div className="px-4 py-3 border-t border-gray-200 dark:border-gray-700 flex gap-2">
                    <textarea
                        value={input}
                        onChange={(e) => setInput(e.target.value)}
                        onKeyDown={handleKeyDown}
                        placeholder="Pregunta algo a tu tutor..."
                        rows={1}
                        className="flex-1 resize-none px-3 py-2 border border-gray-200 dark:border-gray-700 rounded-xl bg-white dark:bg-gray-800 text-sm text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-brand-500"
                    />
                    <button
                        onClick={handleSend}
                        disabled={!input.trim() || sendMutation.isPending}
                        className="w-9 h-9 self-end bg-brand-600 hover:bg-brand-700 disabled:bg-gray-300 dark:disabled:bg-gray-700 text-white rounded-xl flex items-center justify-center transition-colors flex-shrink-0"
                    >
                        <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    );
}
