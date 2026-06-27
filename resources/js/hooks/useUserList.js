import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import api from '../lib/api';
import { getSessionToken } from '../lib/session';

// Owns the user_list entity for the active specialty, plus its preferences
// (the kanban / sortable state) and the home-address mutations.
export function useUserList(specialtyId) {
    const queryClient = useQueryClient();
    const sessionToken = getSessionToken();

    // Ensure a list exists for (session_token, specialty) and keep it in cache.
    const listQuery = useQuery({
        queryKey: ['user-list', sessionToken, specialtyId],
        enabled: Boolean(specialtyId),
        queryFn: async () => {
            const { data } = await api.post('/user-lists', {
                session_token: sessionToken,
                specialty_id: specialtyId,
            });
            return data.data;
        },
        staleTime: Infinity,
    });

    const listId = listQuery.data?.id;

    const preferencesQuery = useQuery({
        queryKey: ['preferences', listId],
        enabled: Boolean(listId),
        queryFn: async () => {
            const { data } = await api.get(`/user-lists/${listId}/preferences`);
            return data.data;
        },
    });

    const savePreferences = useMutation({
        mutationFn: async (preferences) => {
            const { data } = await api.put(`/user-lists/${listId}/preferences/bulk`, {
                preferences,
            });
            return data.data;
        },
        onSuccess: (data) => {
            queryClient.setQueryData(['preferences', listId], data);
        },
    });

    const updateAddress = useMutation({
        mutationFn: async (payload) => {
            const { data } = await api.patch(`/user-lists/${listId}`, payload);
            return data.data;
        },
        onSuccess: (data) => {
            queryClient.setQueryData(['user-list', sessionToken, specialtyId], data);
        },
    });

    const geocode = useMutation({
        mutationFn: async (address) => {
            const { data } = await api.post(`/user-lists/${listId}/geocode`, { address });
            return data;
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['user-list', sessionToken, specialtyId] });
            queryClient.invalidateQueries({ queryKey: ['vacancies'] });
        },
    });

    return {
        sessionToken,
        list: listQuery.data,
        listId,
        isLoading: listQuery.isLoading,
        preferences: preferencesQuery.data ?? [],
        preferencesLoading: preferencesQuery.isLoading,
        savePreferences,
        updateAddress,
        geocode,
    };
}
