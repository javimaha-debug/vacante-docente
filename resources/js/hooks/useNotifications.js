import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import api from '../lib/api';
import { useAuth } from './useAuth';

// In-app notifications inbox for the authenticated user. Polls periodically so
// the unread badge updates without a manual refresh.
export function useNotifications() {
    const { isAuthenticated } = useAuth();
    const queryClient = useQueryClient();

    const query = useQuery({
        queryKey: ['notificaciones'],
        enabled: isAuthenticated,
        refetchInterval: 60 * 1000,
        queryFn: async () => (await api.get('/notificaciones')).data,
    });

    const markRead = useMutation({
        mutationFn: async (id) => (await api.post(`/notificaciones/leer${id ? `/${id}` : ''}`)).data,
        onSuccess: () => queryClient.invalidateQueries({ queryKey: ['notificaciones'] }),
    });

    return {
        items: query.data?.data ?? [],
        unread: query.data?.unread ?? 0,
        isLoading: query.isLoading,
        markRead,
    };
}
