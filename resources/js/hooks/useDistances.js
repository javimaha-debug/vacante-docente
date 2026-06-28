import { useMutation, useQueryClient } from '@tanstack/react-query';
import api from '../lib/api';

// Triggers the server-side Distance Matrix calculation for the SELECTED
// vacancies in a list. Results are cached server-side; we refresh preferences
// afterwards so cards pick up their distances.
export function useDistances(listId) {
    const queryClient = useQueryClient();

    const calculate = useMutation({
        // Accepts { modes, vacancyIds, depTime, retTime, force }. Computes
        // outbound + return for each travel mode at the given times.
        mutationFn: async (arg = {}) => {
            const opts = typeof arg === 'string' ? { mode: arg } : arg;
            const body = {};
            if (opts.modes?.length) body.modes = opts.modes;
            else body.mode = opts.mode ?? 'driving';
            if (opts.vacancyIds?.length) body.vacancy_ids = opts.vacancyIds;
            if (opts.depTime) body.dep_time = opts.depTime;
            if (opts.retTime) body.ret_time = opts.retTime;
            if (opts.force) body.force = true;
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
