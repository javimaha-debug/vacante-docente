import { useMutation, useQueryClient } from '@tanstack/react-query';
import api from '../lib/api';

// Triggers the server-side Distance Matrix calculation for the SELECTED
// vacancies in a list. Results are cached server-side; we refresh preferences
// afterwards so cards pick up their distances.
export function useDistances(listId) {
    const queryClient = useQueryClient();

    const calculate = useMutation({
        mutationFn: async (mode = 'all') => {
            const { data } = await api.post(`/user-lists/${listId}/calculate-distances`, { mode });
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
