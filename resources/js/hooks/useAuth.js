import { createContext, useCallback, useContext, useEffect, useState } from 'react';
import axios from 'axios';
import api from '../lib/api';
import { getToken, setToken, clearToken, captureTokenFromUrl } from '../lib/auth-token';

// Shared auth state. Provided by <AuthProvider> (see app.jsx) and consumed by
// components via useAuth().
export const AuthContext = createContext(null);

export function useAuth() {
    const ctx = useContext(AuthContext);
    if (!ctx) {
        throw new Error('useAuth must be used within an AuthProvider');
    }
    return ctx;
}

/**
 * Stateful auth logic. Returns the value to feed into AuthContext.Provider.
 *
 * - Captures a ?token= handed over by the OAuth callback.
 * - Loads the authenticated user from /user/me.
 * - On a missing/expired token, exposes user=null so guards redirect to login.
 */
export function useProvideAuth() {
    // Grab a token handed over by the OAuth redirect (?token=...) once, before
    // reading from storage, then strip it from the URL.
    const [token, setTokenState] = useState(() => {
        captureTokenFromUrl();
        return getToken();
    });
    const [user, setUser] = useState(null);
    const [loading, setLoading] = useState(Boolean(getToken()));

    const fetchUser = useCallback(async () => {
        if (!getToken()) {
            setUser(null);
            setLoading(false);
            return null;
        }
        setLoading(true);
        try {
            const { data } = await api.get('/user/me');
            setUser(data);
            return data;
        } catch {
            // 401 is handled by the api interceptor (token cleared); reflect it.
            clearToken();
            setTokenState(null);
            setUser(null);
            return null;
        } finally {
            setLoading(false);
        }
    }, []);

    useEffect(() => {
        fetchUser();
    }, [fetchUser]);

    const login = useCallback(
        (newToken) => {
            setToken(newToken);
            setTokenState(newToken);
            return fetchUser();
        },
        [fetchUser]
    );

    const logout = useCallback(async () => {
        const current = getToken();
        try {
            // /auth/logout is a web route (outside the /api/v1 baseURL).
            await axios.post(
                '/auth/logout',
                {},
                { headers: { Authorization: `Bearer ${current}`, Accept: 'application/json' } }
            );
        } catch {
            /* token may already be invalid; clear locally regardless */
        }
        clearToken();
        setTokenState(null);
        setUser(null);
        window.location.assign('/');
    }, []);

    return {
        token,
        user,
        loading,
        isAuthenticated: Boolean(token),
        login,
        logout,
        refresh: fetchUser,
    };
}
