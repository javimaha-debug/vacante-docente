// Single source of truth for the Sanctum personal-access token in localStorage.
const TOKEN_KEY = 'vd_token';

export function getToken() {
    try {
        return window.localStorage.getItem(TOKEN_KEY);
    } catch {
        return null;
    }
}

export function setToken(token) {
    try {
        window.localStorage.setItem(TOKEN_KEY, token);
    } catch {
        /* ignore storage failures (private mode, etc.) */
    }
}

export function clearToken() {
    try {
        window.localStorage.removeItem(TOKEN_KEY);
    } catch {
        /* ignore */
    }
}

/**
 * If the OAuth callback bounced us to /dashboard?token=..., capture the token
 * into localStorage and strip it from the URL. Returns true if one was found.
 */
export function captureTokenFromUrl() {
    const params = new URLSearchParams(window.location.search);
    const token = params.get('token');
    if (!token) {
        return false;
    }
    setToken(token);
    params.delete('token');
    const query = params.toString();
    const clean = window.location.pathname + (query ? `?${query}` : '');
    window.history.replaceState({}, '', clean);
    return true;
}
