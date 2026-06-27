import { useInfiniteQuery } from '@tanstack/react-query';
import api from '../lib/api';
import { getSessionToken } from '../lib/session';

// Paginated vacancy list for a specialty + filters. Uses infinite query so the
// UI can "load more" while keeping all loaded pages in one flat array.
export function useVacancies(specialtyId, filters) {
    const sessionToken = getSessionToken();

    const query = useInfiniteQuery({
        queryKey: ['vacancies', specialtyId, filters],
        enabled: Boolean(specialtyId),
        initialPageParam: 1,
        queryFn: async ({ pageParam }) => {
            const params = {
                specialty_id: specialtyId,
                session_token: sessionToken,
                page: pageParam,
                per_page: 1000,
            };
            if (filters.provincia) params.provincia = filters.provincia;
            if (filters.tiposCentro?.length) params.tipo_centro = filters.tiposCentro;
            if (filters.search) params.search = filters.search;
            if (filters.tags?.length) params.tags = filters.tags;

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
