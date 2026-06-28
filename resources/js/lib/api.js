import axios from 'axios';
import { getToken, clearToken } from './auth-token';
import { getSessionToken } from './session';

// Axios instance pointed at the versioned API. The Google Maps key stays on
// the server; the SPA only ever talks to these endpoints.
const api = axios.create({
    baseURL: '/api/v1',
    headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    },
});

// Attach the Sanctum bearer token (stored in localStorage) when present, plus
// the anonymous session token so list ownership can be verified server-side.
api.interceptors.request.use((config) => {
    const token = getToken();
    if (token) {
        config.headers.Authorization = `Bearer ${token}`;
    }
    const sessionToken = getSessionToken();
    if (sessionToken) {
        config.headers['X-Session-Token'] = sessionToken;
    }
    return config;
});

// Surface a friendly message on the error object for the UI, and bounce to the
// login screen when the token is missing/expired (401 on a protected route).
api.interceptors.response.use(
    (response) => response,
    (error) => {
        const data = error.response?.data;
        error.friendlyMessage =
            data?.message ||
            (data?.errors ? Object.values(data.errors).flat().join(' ') : null) ||
            'Ha ocurrido un error de conexión. Inténtalo de nuevo.';

        if (error.response?.status === 401 && getToken()) {
            clearToken();
            if (window.location.pathname !== '/') {
                window.location.assign('/');
            }
        }
        return Promise.reject(error);
    }
);

export default api;
