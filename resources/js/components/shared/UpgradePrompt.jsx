import { useNavigate } from 'react-router-dom';

/**
 * Shown in place of (or over) a plan-gated feature. Never hide gated features
 * silently — surface them locked with a clear path to upgrade.
 *
 * Variants:
 *   - block (default): a full card explaining the locked feature.
 *   - inline: a compact pill, e.g. next to a disabled button.
 */
export default function UpgradePrompt({
    title = 'Función premium',
    message = 'Mejora tu plan para desbloquear esta función.',
    variant = 'block',
}) {
    const navigate = useNavigate();

    if (variant === 'inline') {
        return (
            <button
                type="button"
                onClick={() => navigate('/dashboard/planes')}
                className="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-800 ring-1 ring-amber-200 hover:bg-amber-200"
                title={message}
            >
                <span aria-hidden="true">🔒</span> Ver planes
            </button>
        );
    }

    return (
        <div className="mx-auto max-w-md rounded-2xl border border-amber-200 bg-amber-50 p-6 text-center">
            <div className="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-amber-100 text-2xl" aria-hidden="true">
                🔒
            </div>
            <h3 className="mt-3 text-base font-bold text-amber-900">{title}</h3>
            <p className="mt-1 text-sm text-amber-800">{message}</p>
            <button
                type="button"
                onClick={() => navigate('/dashboard/planes')}
                className="mt-4 rounded-lg bg-amber-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-amber-700"
            >
                Ver planes
            </button>
        </div>
    );
}
