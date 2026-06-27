import axios from 'axios';

// Axios instance pointed at the versioned API. The Google Maps key stays on
// the server; the SPA only ever talks to these endpoints.
const api = axios.create({
    baseURL: '/api/v1',
    headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    },
});

// Surface a friendly message on the error object for the UI.
api.interceptors.response.use(
    (response) => response,
    (error) => {
        const data = error.response?.data;
        error.friendlyMessage =
            data?.message ||
            (data?.errors ? Object.values(data.errors).flat().join(' ') : null) ||
            'Ha ocurrido un error de conexión. Inténtalo de nuevo.';
        return Promise.reject(error);
    }
);

export default api;
