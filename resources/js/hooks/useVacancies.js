import { useInfiniteQuery } from '@tanstack/react-query';
import api from '../lib/api';
import { getSessionToken } from '../lib/session';

// Paginated vacancy list for a specialty + filters. Uses infinite query so the
// UI can "load more" while keeping all loaded pages in one flat array.
//
// When `procesoId` is provided the vacancies are scoped to that proceso via
// /procesos/{id}/vacantes; otherwise the legacy /vacancies (session) endpoint
// is used so the anonymous explorer keeps working unchanged.
export function useVacancies(specialtyId, filters, procesoId = null) {
    const sessionToken = getSessionToken();

    // All explorer filters are applied client-side (real-time, combinable with
    // accurate counts), so we fetch the full specialty set for the proceso and
    // let the SPA filter/sort it. `filters` is intentionally NOT in the query
    // key — changing a filter must not refetch.
    const query = useInfiniteQuery({
        queryKey: ['vacancies', specialtyId, procesoId],
        enabled: Boolean(specialtyId),
        initialPageParam: 1,
        queryFn: async ({ pageParam }) => {
            if (procesoId) {
                const params = { especialidad: specialtyId, session_token: sessionToken, page: pageParam, per_page: 1000 };
                const { data } = await api.get(`/procesos/${procesoId}/vacantes`, { params });
                return data;
            }

            const params = {
                specialty_id: specialtyId,
                session_token: sessionToken,
                page: pageParam,
                per_page: 1000,
            };
            const { data } = await api.get('/vacancies', { params });
            return data;
        },
        getNextPageParam: (lastPage) => {
            const { current_page, last_page } = lastPage.meta;
            return current_page < last_page ? current_page + 1 : undefined;
        },
    });

    const vacancies = query.data?.pages.flatMap((page) => page.data) ?? [];
    const total = query.data?.pages[0]?.meta.total ?? 0;

    return {
        vacancies,
        total,
        isLoading: query.isLoading,
        isError: query.isError,
        hasNextPage: query.hasNextPage,
        fetchNextPage: query.fetchNextPage,
        isFetchingNextPage: query.isFetchingNextPage,
    };
}
