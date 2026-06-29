import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import api from '../lib/api';
import { getSessionToken } from '../lib/session';

// Find a vacancy object across any cached vacancies (infinite) query, so an
// optimistically-selected card can render before the server responds.
function findVacancyInCache(queryClient, vacancyId) {
    for (const [, data] of queryClient.getQueriesData({ queryKey: ['vacancies'] })) {
        for (const page of data?.pages ?? []) {
            const hit = (page.data ?? []).find((v) => v.id === vacancyId);
            if (hit) return hit;
        }
    }
    return null;
}

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
        // Optimistic UI: reflect the move/reorder immediately, then let the
        // server response reconcile. Rolls back on error.
        onMutate: async (rows) => {
            await queryClient.cancelQueries({ queryKey: ['preferences', listId] });
            const snapshot = queryClient.getQueryData(['preferences', listId]) ?? [];

            const byVacancy = new Map(snapshot.map((p) => [p.vacancy_id, p]));
            for (const row of rows) {
                const existing = byVacancy.get(row.vacancy_id);
                if (existing) {
                    byVacancy.set(row.vacancy_id, { ...existing, ...row });
                } else {
                    // Newly selected from the explorer: recover its vacancy
                    // object from the vacancies cache so it renders at once.
                    const vacancy = findVacancyInCache(queryClient, row.vacancy_id);
                    byVacancy.set(row.vacancy_id, { ...row, vacancy });
                }
            }
            queryClient.setQueryData(['preferences', listId], Array.from(byVacancy.values()));

            return { snapshot };
        },
        onError: (_err, _rows, context) => {
            if (context?.snapshot) {
                queryClient.setQueryData(['preferences', listId], context.snapshot);
            }
        },
        onSuccess: (data) => {
            // Server response is authoritative (adds real ids + distances).
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
