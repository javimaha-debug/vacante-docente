import api from './api';

// Web Push helpers: feature detection, service-worker registration and
// subscribe / unsubscribe against the backend.

export function pushSupported() {
    return typeof window !== 'undefined' && 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;
}

function urlBase64ToUint8Array(base64String) {
    const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
    const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
    const raw = window.atob(base64);
    const output = new Uint8Array(raw.length);
    for (let i = 0; i < raw.length; i += 1) output[i] = raw.charCodeAt(i);
    return output;
}

async function registration() {
    return navigator.serviceWorker.register('/sw.js');
}

// Returns the server's VAPID config: { enabled, public_key }.
export async function fetchVapid() {
    const { data } = await api.get('/push/vapid-key');
    return data;
}

export async function currentSubscription() {
    if (!pushSupported()) return null;
    const reg = await registration();
    return reg.pushManager.getSubscription();
}

// Subscribe this browser and persist the subscription on the server.
export async function subscribe(publicKey) {
    const reg = await registration();
    const permission = await Notification.requestPermission();
    if (permission !== 'granted') {
        throw new Error('permission-denied');
    }

    const sub = await reg.pushManager.subscribe({
        userVisibleOnly: true,
        applicationServerKey: urlBase64ToUint8Array(publicKey),
    });

    const json = sub.toJSON();
    await api.post('/push/subscribe', {
        endpoint: sub.endpoint,
        keys: json.keys,
        contentEncoding: (PushManager.supportedContentEncodings || ['aesgcm'])[0],
    });

    return sub;
}

export async function unsubscribe() {
    const sub = await currentSubscription();
    if (!sub) return;
    try {
        await api.post('/push/unsubscribe', { endpoint: sub.endpoint });
    } finally {
        await sub.unsubscribe();
    }
}
