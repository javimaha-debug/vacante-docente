// Cookie / tracking consent stored in localStorage (LSSI-CE art. 22.2 + AEPD
// guidance). We persist the user's granular choice plus the policy version, so
// a future policy change can re-prompt for consent.

const CONSENT_KEY = 'vd.cookie_consent';
export const CONSENT_VERSION = 1;

// Categories. "necesarias" are always on (strictly necessary / technical, no
// consent required); the rest are opt-in and default to false.
export const DEFAULT_CONSENT = {
    necesarias: true,
    analiticas: false,
};

export function getConsent() {
    try {
        const raw = localStorage.getItem(CONSENT_KEY);
        if (!raw) return null;
        const parsed = JSON.parse(raw);
        // A bumped policy version invalidates the stored choice.
        if (parsed.version !== CONSENT_VERSION) return null;
        return parsed;
    } catch {
        return null;
    }
}

export function hasDecided() {
    return getConsent() !== null;
}

export function saveConsent(choices) {
    const payload = {
        version: CONSENT_VERSION,
        decided_at: new Date().toISOString(),
        ...DEFAULT_CONSENT,
        ...choices,
        necesarias: true, // can never be switched off
    };
    try {
        localStorage.setItem(CONSENT_KEY, JSON.stringify(payload));
    } catch {
        /* storage unavailable — non-fatal */
    }
    // Let listeners (e.g. Sentry init) react to the new choice.
    window.dispatchEvent(new CustomEvent('vd:consent-changed', { detail: payload }));
    return payload;
}

export function acceptAll() {
    return saveConsent({ analiticas: true });
}

export function rejectAll() {
    return saveConsent({ analiticas: false });
}

/** Whether the user has opted in to a given non-essential category. */
export function consentGranted(category) {
    const c = getConsent();
    return Boolean(c && c[category]);
}
