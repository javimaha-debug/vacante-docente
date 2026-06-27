import { useMutation, useQueryClient } from '@tanstack/react-query';
import api from '../lib/api';

// Triggers the server-side Distance Matrix calculation for the SELECTED
// vacancies in a list. Results are cached server-side; we refresh preferences
// afterwards so cards pick up their distances.
export function useDistances(listId) {
    const queryClient = useQueryClient();

    const calculate = useMutation({
        // Accepts either a mode string (legacy) or { mode, vacancyIds } so the
        // explorer can compute distances for the full loaded/filtered list.
        mutationFn: async (arg = 'all') => {
            const { mode = 'all', vacancyIds = null } = typeof arg === 'string' ? { mode: arg } : arg;
            const body = { mode };
            if (vacancyIds?.length) body.vacancy_ids = vacancyIds;
            const { data } = await api.post(`/user-lists/${listId}/calculate-distances`, body);
            return data;
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['preferences', listId] });
            queryClient.invalidateQueries({ queryKey: ['vacancies'] });
        },
    });

    return {
        calculate,
        isCalculating: calculate.isPending,
        result: calculate.data,
        error: calculate.error,
    };
}
