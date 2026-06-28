import { useAuth } from '../../hooks/useAuth';
import api from '../../lib/api';
import { restoreAdminToken } from '../../lib/impersonation';

/**
 * Fixed yellow banner shown while an admin is impersonating a user. Clicking
 * "Salir" ends the impersonation session server-side and restores the admin's
 * own token.
 */
export default function ImpersonationBanner() {
    const { user } = useAuth();

    if (!user?.is_impersonated) {
        return null;
    }

    const stop = async () => {
        try {
            await api.post('/user/stop-impersonate');
        } catch {
            /* the token may already be gone; restore regardless */
        }
        restoreAdminToken();
    };

    return (
        <div
            className="fixed inset-x-0 top-0 z-[60] flex items-center justify-center gap-3 px-4 py-2 text-sm font-medium text-amber-900"
            style={{ backgroundColor: '#FEF3C7' }}
            role="alert"
        >
            <span aria-hidden="true">👤</span>
            <span>
                Estás viendo la cuenta de <strong>{user.name}</strong>
                {user.impersonated_by ? <> · suplantando como <strong>{user.impersonated_by}</strong></> : null}
            </span>
            <button
                onClick={stop}
                className="rounded-full bg-amber-900 px-3 py-1 text-xs font-semibold text-amber-50 hover:bg-amber-800"
            >
                Salir de la suplantación
            </button>
        </div>
    );
}
