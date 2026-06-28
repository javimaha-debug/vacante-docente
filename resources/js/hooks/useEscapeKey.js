import { useEffect } from 'react';

// Calls `handler` when Escape is pressed (for dismissible dialogs/menus).
export function useEscapeKey(handler) {
    useEffect(() => {
        const onKey = (e) => {
            if (e.key === 'Escape') handler();
        };
        document.addEventListener('keydown', onKey);
        return () => document.removeEventListener('keydown', onKey);
    }, [handler]);
}
