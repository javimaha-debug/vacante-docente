import { useEffect, useState } from 'react';
import clsx from 'clsx';
import { pushSupported, fetchVapid, currentSubscription, subscribe, unsubscribe } from '../../lib/push';

// Per-device opt-in for browser/mobile push notifications. Hidden entirely when
// the browser can't do push or the server has no VAPID keys configured.
export default function PushToggle() {
    const [available, setAvailable] = useState(false);
    const [publicKey, setPublicKey] = useState(null);
    const [on, setOn] = useState(false);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState(null);

    useEffect(() => {
        let cancelled = false;
        (async () => {
            if (!pushSupported()) return;
            try {
                const vapid = await fetchVapid();
                if (cancelled || !vapid.enabled) return;
                setPublicKey(vapid.public_key);
                setAvailable(true);
                const sub = await currentSubscription();
                if (!cancelled) setOn(Boolean(sub));
            } catch {
                /* push simply stays unavailable */
            }
        })();
        return () => {
            cancelled = true;
        };
    }, []);

    if (!available) return null;

    const toggle = async (next) => {
        setBusy(true);
        setError(null);
        try {
            if (next) {
                await subscribe(publicKey);
                setOn(true);
            } else {
                await unsubscribe();
                setOn(false);
            }
        } catch (e) {
            setError(e?.message === 'permission-denied'
                ? 'Has bloqueado las notificaciones en el navegador.'
                : 'No se pudo cambiar la suscripción.');
        } finally {
            setBusy(false);
        }
    };

    return (
        <div>
            <button type="button" role="switch" aria-checked={on} disabled={busy} onClick={() => toggle(!on)} className="flex items-center gap-3 disabled:opacity-60">
                <span className={clsx('relative inline-flex h-6 w-11 items-center rounded-full transition', on ? 'bg-brand-600' : 'bg-slate-300')}>
                    <span className={clsx('inline-block h-5 w-5 transform rounded-full bg-white shadow transition', on ? 'translate-x-5' : 'translate-x-0.5')} />
                </span>
                <span className="text-sm text-slate-700">Notificaciones push en este dispositivo</span>
            </button>
            {error && <p className="mt-1 text-xs text-amber-600">{error}</p>}
        </div>
    );
}
