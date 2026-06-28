import { getToken, setToken } from './auth-token';

// While impersonating, the admin's own token is parked here so it can be
// restored when the session ends.
const ADMIN_TOKEN_KEY = 'vd_admin_token';

/**
 * Swap the active token for an impersonation token, parking the admin token.
 * Then send the admin into the regular app as the impersonated user.
 */
export function startImpersonation(impersonationToken) {
    try {
        const current = getToken();
        if (current) {
            window.localStorage.setItem(ADMIN_TOKEN_KEY, current);
        }
    } catch {
        /* ignore storage failures */
    }
    setToken(impersonationToken);
    window.location.assign('/dashboard');
}

/** Whether an impersonation session is in progress (admin token parked). */
export function isImpersonating() {
    try {
        return Boolean(window.localStorage.getItem(ADMIN_TOKEN_KEY));
    } catch {
        return false;
    }
}

/** Restore the parked admin token and return to the admin panel. */
export function restoreAdminToken() {
    try {
        const admin = window.localStorage.getItem(ADMIN_TOKEN_KEY);
        window.localStorage.removeItem(ADMIN_TOKEN_KEY);
        if (admin) {
            setToken(admin);
        }
    } catch {
        /* ignore */
    }
    window.location.assign('/superadmin');
}
