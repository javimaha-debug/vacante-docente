import { useQuery } from '@tanstack/react-query';
import api from '../../../lib/api';

// Shared data hooks for the assistant: temas (for flashcards/simulacro/scoring)
// and the user's documents (for the "Mis apuntes" RAG context).
export function useTemas() {
    return useQuery({
        queryKey: ['oposicion-temas'],
        queryFn: async () => (await api.get('/oposicion/temas')).data,
    });
}

export function useDocuments() {
    return useQuery({
        queryKey: ['documents'],
        queryFn: async () => (await api.get('/documents')).data,
    });
}

export function useConversations() {
    return useQuery({
        queryKey: ['ai-conversations'],
        queryFn: async () => (await api.get('/ai/conversations')).data,
    });
}

export function useScores() {
    return useQuery({
        queryKey: ['ai-scores'],
        queryFn: async () => (await api.get('/ai/scores')).data,
    });
}
