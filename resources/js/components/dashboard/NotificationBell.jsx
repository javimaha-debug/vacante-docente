import { useEffect, useRef, useState } from 'react';
import { useNotifications } from '../../hooks/useNotifications';

function timeAgo(iso) {
    if (!iso) return '';
    try {
        return new Date(iso).toLocaleDateString('es-ES', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' });
    } catch {
        return '';
    }
}

export default function NotificationBell() {
    const { items, unread, markRead } = useNotifications();
    const [open, setOpen] = useState(false);
    const ref = useRef(null);

    useEffect(() => {
        if (!open) return undefined;
        const onClick = (e) => {
            if (ref.current && !ref.current.contains(e.target)) setOpen(false);
        };
        document.addEventListener('mousedown', onClick);
        return () => document.removeEventListener('mousedown', onClick);
    }, [open]);

    return (
        <div className="relative" ref={ref}>
            <button
                type="button"
                onClick={() => setOpen((v) => !v)}
                className="relative rounded-lg p-2 text-slate-500 transition hover:bg-slate-100"
                aria-label="Notificaciones"
                title="Notificaciones"
            >
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={2} className="h-5 w-5">
                    <path strokeLinecap="round" strokeLinejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" />
                </svg>
                {unread > 0 && (
                    <span className="absolute -right-0.5 -top-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-rose-600 px-1 text-[10px] font-bold text-white">
                        {unread > 9 ? '9+' : unread}
                    </span>
                )}
            </button>

            {open && (
                <div className="absolute right-0 z-40 mt-2 w-80 max-w-[90vw] overflow-hidden rounded-xl border border-slate-200 bg-white shadow-lg">
                    <div className="flex items-center justify-between border-b border-slate-100 px-3 py-2">
                        <span className="text-sm font-bold text-slate-700">Notificaciones</span>
                        {unread > 0 && (
                            <button
                                type="button"
                                onClick={() => markRead.mutate(undefined)}
                                className="text-[11px] font-semibold text-brand-600 hover:text-brand-700"
                            >
                                Marcar todas como leídas
                            </button>
                        )}
                    </div>

                    <div className="max-h-96 overflow-y-auto">
                        {items.length === 0 ? (
                            <p className="px-3 py-6 text-center text-sm text-slate-400">No tienes notificaciones.</p>
                        ) : (
                            items.map((n) => (
                                <button
                                    key={n.id}
                                    type="button"
                                    onClick={() => !n.read_at && markRead.mutate(n.id)}
                                    className={`block w-full border-b border-slate-50 px-3 py-2.5 text-left transition hover:bg-slate-50 ${n.read_at ? '' : 'bg-amber-50/60'}`}
                                >
                                    <div className="flex items-start gap-2">
                                        {!n.read_at && <span className="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-rose-500" />}
                                        <div className="min-w-0">
                                            <p className="text-sm font-semibold text-slate-800">{n.data?.titulo ?? 'Aviso'}</p>
                                            {n.data?.descripcion && <p className="mt-0.5 text-xs text-slate-500">{n.data.descripcion}</p>}
                                            <p className="mt-0.5 text-[11px] text-slate-400">{timeAgo(n.created_at)}</p>
                                        </div>
                                    </div>
                                </button>
                            ))
                        )}
                    </div>
                </div>
            )}
        </div>
    );
}
