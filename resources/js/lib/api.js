import axios from 'axios';
import { getToken, clearToken } from './auth-token';

// Axios instance pointed at the versioned API. The Google Maps key stays on
// the server; the SPA only ever talks to these endpoints.
const api = axios.create({
    baseURL: '/api/v1',
    headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    },
});

// Attach the Sanctum bearer token (stored in localStorage) when present.
api.interceptors.request.use((config) => {
    const token = getToken();
    if (token) {
        config.headers.Authorization = `Bearer ${token}`;
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
