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
    const token = stripParam('token');
    if (!token) {
        return false;
    }
    setToken(token);
    return true;
}

function stripParam(key) {
    const params = new URLSearchParams(window.location.search);
    if (!params.has(key)) {
        return null;
    }
    const value = params.get(key);
    params.delete(key);
    const query = params.toString();
    window.history.replaceState({}, '', window.location.pathname + (query ? `?${query}` : ''));
    return value;
}

/**
 * Pop the one-time OAuth code from the URL (?code=), stripping it. The SPA
 * exchanges it for the real Sanctum token via POST /auth/exchange.
 */
export function popAuthCodeFromUrl() {
    return stripParam('code');
}
