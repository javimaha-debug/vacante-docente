import { useEffect, useRef } from 'react';

// Traps keyboard focus inside a dialog while it's open: focuses the first
// interactive element on mount, keeps Tab/Shift+Tab cycling within the dialog,
// and restores focus to the previously-focused element on close.
// Usage: const ref = useFocusTrap(); <div ref={ref} role="dialog" aria-modal="true">…</div>
export function useFocusTrap(active = true) {
    const ref = useRef(null);

    useEffect(() => {
        if (!active || !ref.current) return undefined;

        const node = ref.current;
        const previouslyFocused = document.activeElement;
        const selector = 'a[href], button:not([disabled]), textarea:not([disabled]), input:not([disabled]), select:not([disabled]), [tabindex]:not([tabindex="-1"])';
        const focusables = () => Array.from(node.querySelectorAll(selector)).filter((el) => el.offsetParent !== null);

        focusables()[0]?.focus();

        const onKeyDown = (e) => {
            if (e.key !== 'Tab') return;
            const els = focusables();
            if (els.length === 0) return;
            const first = els[0];
            const last = els[els.length - 1];
            if (e.shiftKey && document.activeElement === first) {
                e.preventDefault();
                last.focus();
            } else if (!e.shiftKey && document.activeElement === last) {
                e.preventDefault();
                first.focus();
            }
        };

        node.addEventListener('keydown', onKeyDown);
        return () => {
            node.removeEventListener('keydown', onKeyDown);
            if (previouslyFocused instanceof HTMLElement) previouslyFocused.focus();
        };
    }, [active]);

    return ref;
}
