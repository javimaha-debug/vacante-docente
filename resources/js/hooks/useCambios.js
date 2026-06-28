import { useQuery } from '@tanstack/react-query';
import api from '../lib/api';

// Summary of the latest listing import for a proceso: whether it changed and
// how many vacancies were added / modified / removed. Powers the "listado
// actualizado" banner in the explorer.
export function useCambios(procesoId) {
    const query = useQuery({
        queryKey: ['cambios', procesoId],
        enabled: Boolean(procesoId),
        staleTime: 5 * 60 * 1000,
        queryFn: async () => {
            const { data } = await api.get(`/procesos/${procesoId}/cambios`);
            return data;
        },
    });

    return query.data ?? null;
}
