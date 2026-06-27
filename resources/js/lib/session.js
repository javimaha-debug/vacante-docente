// Session + specialty persistence in localStorage.
// Phase 1 has no auth: a random session_token identifies the user's lists.

const TOKEN_KEY = 'vd.session_token';
const SPECIALTY_KEY = 'vd.specialty_id';

function randomToken() {
    // 32 hex chars — fits the varchar(64) column and the min:8 rule.
    if (window.crypto?.randomUUID) {
        return window.crypto.randomUUID().replace(/-/g, '');
    }
    return Math.random().toString(36).slice(2) + Date.now().toString(36);
}

export function getSessionToken() {
    let token = localStorage.getItem(TOKEN_KEY);
    if (!token) {
        token = randomToken();
        localStorage.setItem(TOKEN_KEY, token);
    }
    return token;
}

export function getSpecialtyId() {
    const raw = localStorage.getItem(SPECIALTY_KEY);
    return raw ? Number(raw) : null;
}

export function setSpecialtyId(id) {
    localStorage.setItem(SPECIALTY_KEY, String(id));
}

export function clearSpecialty() {
    localStorage.removeItem(SPECIALTY_KEY);
}
