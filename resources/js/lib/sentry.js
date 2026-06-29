// Frontend error monitoring (Sentry). Error capture runs as a technical /
// security measure (no PII sent); performance tracing is gated behind the
// user's "analíticas" cookie consent so we don't profile anyone who opted out.

import * as Sentry from '@sentry/react';
import { consentGranted } from './consent';

const DSN = import.meta.env.VITE_SENTRY_DSN;
const TRACES_RATE = Number(import.meta.env.VITE_SENTRY_TRACES_SAMPLE_RATE ?? 0);

let started = false;

export function initSentry() {
    // No DSN configured (local/dev) → do nothing.
    if (!DSN || started) return;
    started = true;

    Sentry.init({
        dsn: DSN,
        environment: import.meta.env.MODE,
        // Never attach IP / cookies / request bodies that could carry PII.
        sendDefaultPii: false,
        // Performance tracing only when the user accepted analytics cookies.
        tracesSampleRate: consentGranted('analiticas') ? TRACES_RATE : 0,
        integrations: consentGranted('analiticas') ? [Sentry.browserTracingIntegration()] : [],
    });

    // Re-evaluate tracing when the user changes their cookie choice.
    window.addEventListener('vd:consent-changed', () => {
        Sentry.getClient()?.getOptions &&
            (Sentry.getClient().getOptions().tracesSampleRate = consentGranted('analiticas') ? TRACES_RATE : 0);
    });
}
