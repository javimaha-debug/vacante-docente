import { useState } from 'react';

export default function HomeAddressPanel({ list, geocode, distances, vacancyIds = [] }) {
    const [address, setAddress] = useState(list?.home_address ?? '');
    const [status, setStatus] = useState('idle'); // idle | geocoding | calculating | done | error
    const [message, setMessage] = useState(null);
    const [resultCount, setResultCount] = useState(null);

    const busy = status === 'geocoding' || status === 'calculating';

    const handleCalculate = async () => {
        if (!address.trim()) {
            setStatus('error');
            setMessage('Introduce una dirección de origen.');
            return;
        }
        if (vacancyIds.length === 0) {
            setStatus('error');
            setMessage('No hay vacantes cargadas para calcular distancias.');
            return;
        }

        try {
            setStatus('geocoding');
            setMessage('Localizando dirección…');
            await geocode.mutateAsync(address.trim());

            setStatus('calculating');
            // Driving time/distance for every loaded vacancy — no need to
            // select them first, so the list can be organized by distance.
            // The server computes in capped chunks (cache-first); we re-call
            // with the same ids until nothing remains, showing progress.
            const total = vacancyIds.length;
            let res = null;
            let remaining = total;
            let lastRemaining = Infinity;
            let guard = 0;

            do {
                setMessage(`Calculando distancias… ${total - remaining}/${total}`);
                res = await distances.calculate.mutateAsync({ mode: 'driving', vacancyIds });
                remaining = res.remaining ?? 0;
                // Stop if a round makes no progress (e.g. API error) to avoid looping.
                if (remaining >= lastRemaining) break;
                lastRemaining = remaining;
                guard += 1;
            } while (remaining > 0 && guard < 50);

            setResultCount(res?.count ?? 0);
            const failed = res?.error || remaining > 0;
            setStatus(failed ? 'error' : 'done');
            setMessage(
                failed
                    ? 'Algunas distancias no se pudieron calcular. Revisa la clave de Google Maps e inténtalo de nuevo.'
                    : null
            );
        } catch (err) {
            setStatus('error');
            setMessage(err.friendlyMessage ?? 'No se pudo completar el cálculo.');
        }
    };

    return (
        <div className="rounded-xl border border-slate-200 bg-white p-3">
            <div className="mb-2 flex items-center gap-2">
                <span aria-hidden>📍</span>
                <h2 className="text-sm font-semibold text-slate-800">Dirección de origen</h2>
            </div>

            <input
                type="text"
                value={address}
                onChange={(e) => setAddress(e.target.value)}
                placeholder="Calle, número, localidad…"
                className="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-brand-400 focus:outline-none focus:ring-1 focus:ring-brand-200"
            />
            <p className="mt-1 text-[11px] text-slate-400">
                Escribe tu dirección completa. La geolocalización se realiza de forma segura en el servidor.
            </p>

            <button
                type="button"
                onClick={handleCalculate}
                disabled={busy}
                className="mt-2 w-full rounded-lg bg-brand-600 px-3 py-2 text-sm font-semibold text-white transition hover:bg-brand-700 disabled:cursor-not-allowed disabled:opacity-60"
            >
                {busy ? 'Calculando…' : 'Calcular distancias'}
            </button>

            {status !== 'idle' && (
                <div className="mt-2 text-xs">
                    {busy && (
                        <div className="flex items-center gap-2 text-slate-500">
                            <span className="h-3 w-3 animate-spin rounded-full border-2 border-brand-300 border-t-brand-600" />
                            {message}
                        </div>
                    )}
                    {status === 'done' && (
                        <p className="rounded-md bg-emerald-50 px-2 py-1.5 font-medium text-emerald-700">
                            ✓ Distancias calculadas para {resultCount} vacantes. Ordena por distancia para organizar.
                        </p>
                    )}
                    {status === 'error' && (
                        <p className="rounded-md bg-rose-50 px-2 py-1.5 font-medium text-rose-600">{message}</p>
                    )}
                </div>
            )}

            {list?.has_home && status === 'idle' && (
                <p className="mt-2 text-[11px] text-slate-400">
                    Origen guardado: <span className="font-medium text-slate-600">{list.home_address}</span>
                </p>
            )}
        </div>
    );
}
