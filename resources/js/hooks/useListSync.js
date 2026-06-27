import { useCallback, useRef, useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import api from '../lib/api';

// Syncs the kanban "selected" list to the authenticated user's account.
//
// - Loads the saved list from /user/lista (for hydration into the kanban).
// - Exposes sync(items) that debounces (500ms) a PUT to /user/lista/sync.
// - Exposes a status: 'idle' | 'saving' | 'saved' for the UI indicator.
//
// When `enabled` is false (anonymous user) it is inert and the explorer keeps
// its existing session_token behavior.
export function useListSync({ specialtyId, procesoId, enabled }) {
    const [status, setStatus] = useState('idle');
    const timer = useRef(null);
    const savedTimer = useRef(null);

    const savedQuery = useQuery({
        queryKey: ['user-lista', specialtyId, procesoId],
        enabled: Boolean(enabled && specialtyId),
        queryFn: async () => {
            const params = { specialty_id: specialtyId };
            if (procesoId) params.proceso_id = procesoId;
            const { data } = await api.get('/user/lista', { params });
            return data;
        },
        staleTime: Infinity,
    });

    const sync = useCallback(
        (items) => {
            if (!enabled || !specialtyId) return;

            clearTimeout(timer.current);
            setStatus('saving');
            timer.current = setTimeout(async () => {
                try {
                    await api.put('/user/lista/sync', {
                        specialty_id: specialtyId,
                        proceso_id: procesoId ?? null,
                        items,
                    });
                    setStatus('saved');
                    clearTimeout(savedTimer.current);
                    savedTimer.current = setTimeout(() => setStatus('idle'), 2000);
                } catch {
                    setStatus('idle');
                }
            }, 500);
        },
        [enabled, specialtyId, procesoId]
    );

    return {
        status,
        savedItems: savedQuery.data?.items ?? [],
        isHydrating: savedQuery.isLoading,
        sync,
    };
}
