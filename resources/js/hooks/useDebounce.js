import { useEffect, useState } from 'react';

// Returns a debounced copy of `value` that only updates after `delay` ms of
// no changes. Used for type-ahead inputs (address autocomplete, search).
export function useDebounce(value, delay = 500) {
    const [debounced, setDebounced] = useState(value);

    useEffect(() => {
        const id = setTimeout(() => setDebounced(value), delay);
        return () => clearTimeout(id);
    }, [value, delay]);

    return debounced;
}
