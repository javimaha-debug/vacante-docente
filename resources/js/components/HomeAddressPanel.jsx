import { useEffect, useRef, useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import clsx from 'clsx';
import api from '../lib/api';
import { useDebounce } from '../hooks/useDebounce';

const MODE_OPTS = [
    { key: 'driving', label: 'Coche', icon: '🚗' },
    { key: 'transit', label: 'Público', icon: '🚆' },
    { key: 'walking', label: 'A pie', icon: '🚶' },
];

const ls = {
    get: (k, d) => {
        try {
            return window.localStorage.getItem(k) ?? d;
        } catch {
            return d;
        }
    },
    set: (k, v) => {
        try {
            window.localStorage.setItem(k, v);
        } catch {
            /* ignore */
        }
    },
};

// Home address + travel preferences. Picking a suggestion geocodes and then
// automatically computes outbound + return travel times for every loaded
// vacancy (no need to select them). Changing the times or modes recomputes.
export default function HomeAddressPanel({ list, geocode, distances, vacancyIds = [] }) {
    const [address, setAddress] = useState(list?.home_address ?? '');
    const [open, setOpen] = useState(false);
    const [picked, setPicked] = useState(Boolean(list?.home_address));
    const [depTime, setDepTime] = useState(() => ls.get('vd.dep_time', '07:30'));
    const [retTime, setRetTime] = useState(() => ls.get('vd.ret_time', '14:30'));
    const [modes, setModes] = useState(() => {
        const raw = ls.get('vd.modes', 'driving');
        return new Set(raw.split(',').filter(Boolean));
    });
    const [status, setStatus] = useState('idle'); // idle | geocoding | calculating | done | error
    const [message, setMessage] = useState(null);

    const debounced = useDebounce(address, 500);
    const vacancyIdsRef = useRef(vacancyIds);
    vacancyIdsRef.current = vacancyIds;

    // Reflect the address once it arrives on the list (e.g. preloaded from the
    // user's profile) without clobbering what the user is typing.
    useEffect(() => {
        if (list?.home_address && !address) {
            setAddress(list.home_address);
            setPicked(true);
        }
    }, [list?.home_address]); // eslint-disable-line react-hooks/exhaustive-deps

    const { data: suggest } = useQuery({
        queryKey: ['geocode', debounced],
        enabled: Boolean(debounced && debounced.length >= 3 && !picked),
        queryFn: async () => (await api.get('/geocode', { params: { address: debounced } })).data,
        staleTime: 60_000,
    });
    const suggestions = suggest?.data ?? [];

    const busy = status === 'geocoding' || status === 'calculating';

    // Run the chunked calculation loop until the server reports nothing left.
    const runCalc = async () => {
        const ids = vacancyIdsRef.current;
        if (!ids.length || modes.size === 0) return;
        try {
            setStatus('calculating');
            const total = ids.length;
            let remaining = total;
            let last = Infinity;
            let guard = 0;
            let apiError = null;
            do {
                setMessage(`Calculando tiempos… ${total - remaining}/${total}`);
                const res = await distances.calculate.mutateAsync({
                    modes: [...modes],
                    vacancyIds: ids,
                    depTime,
                    retTime,
                    // Force a fresh recompute only on the first pass; later passes
                    // rely on the cache so the per-request budget advances to the
                    // legs that are still pending instead of redoing the first chunk.
                    force: guard === 0,
                });
                if (res.error) apiError = res.error;
                remaining = res.remaining ?? 0;
                if (remaining >= last) break;
                last = remaining;
            } while (remaining > 0 && ++guard < 60);

            // Report honestly: a green "done" only when Google actually answered.
            if (apiError) {
                setStatus('error');
                setMessage(
                    'No se pudieron calcular los tiempos. Revisa que la Distance Matrix API esté habilitada en Google Cloud (' +
                        apiError + ').'
                );
            } else {
                setStatus('done');
                setMessage(null);
            }
        } catch (err) {
            setStatus('error');
            setMessage(err.friendlyMessage ?? 'No se pudieron calcular los tiempos.');
        }
    };

    const handlePick = async (s) => {
        setAddress(s.formatted_address);
        setPicked(true);
        setOpen(false);
        try {
            setStatus('geocoding');
            setMessage('Guardando dirección…');
            await geocode.mutateAsync(s.formatted_address);
            await runCalc();
        } catch (err) {
            setStatus('error');
            setMessage(err.friendlyMessage ?? 'No se pudo geolocalizar la dirección.');
        }
    };

    // Recompute when times or modes change (only if a home is already set).
    const settingsRef = useRef({ depTime, retTime, modesKey: [...modes].sort().join(',') });
    useEffect(() => {
        const modesKey = [...modes].sort().join(',');
        ls.set('vd.dep_time', depTime);
        ls.set('vd.ret_time', retTime);
        ls.set('vd.modes', modesKey);

        const prev = settingsRef.current;
        const changed = prev.depTime !== depTime || prev.retTime !== retTime || prev.modesKey !== modesKey;
        settingsRef.current = { depTime, retTime, modesKey };

        if (changed && list?.has_home) {
            const id = setTimeout(runCalc, 600);
            return () => clearTimeout(id);
        }
    }, [depTime, retTime, modes]); // eslint-disable-line react-hooks/exhaustive-deps

    const toggleMode = (key) =>
        setModes((prev) => {
            const next = new Set(prev);
            next.has(key) ? next.delete(key) : next.add(key);
            return next.size ? next : prev; // keep at least one mode
        });

    return (
        <div className="rounded-xl border border-slate-200 bg-white p-3">
            <div className="mb-2 flex items-center gap-2">
                <span aria-hidden>📍</span>
                <h2 className="text-sm font-semibold text-slate-800">Dirección de origen</h2>
            </div>

            <div className="relative">
                <input
                    type="text"
                    value={address}
                    onChange={(e) => {
                        setAddress(e.target.value);
                        setPicked(false);
                        setOpen(true);
                    }}
                    onFocus={() => setOpen(true)}
                    onBlur={() => setTimeout(() => setOpen(false), 150)}
                    placeholder="Empieza a escribir tu dirección…"
                    autoComplete="off"
                    className="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-brand-400 focus:outline-none focus:ring-1 focus:ring-brand-200"
                />
                {open && !picked && suggestions.length > 0 && (
                    <ul className="absolute z-20 mt-1 max-h-56 w-full overflow-auto rounded-lg border border-slate-200 bg-white shadow-lg">
                        {suggestions.map((s, i) => (
                            <li key={i}>
                                <button
                                    type="button"
                                    onMouseDown={(e) => e.preventDefault()}
                                    onClick={() => handlePick(s)}
                                    className="block w-full px-3 py-2 text-left text-xs text-slate-700 hover:bg-brand-50"
                                >
                                    {s.formatted_address}
                                </button>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
            <p className="mt-1 text-[11px] text-slate-400">
                Elige una sugerencia de Google para fijar la ubicación; las distancias se calculan para las
                vacantes cargadas.
            </p>

            {/* Times */}
            <div className="mt-3 grid grid-cols-2 gap-2">
                <label className="text-[11px] font-medium text-slate-500">
                    Hora ida
                    <input
                        type="time"
                        value={depTime}
                        onChange={(e) => setDepTime(e.target.value)}
                        className="mt-1 w-full rounded-lg border border-slate-200 px-2 py-1.5 text-sm focus:border-brand-400 focus:ring-brand-200"
                    />
                </label>
                <label className="text-[11px] font-medium text-slate-500">
                    Hora retorno
                    <input
                        type="time"
                        value={retTime}
                        onChange={(e) => setRetTime(e.target.value)}
                        className="mt-1 w-full rounded-lg border border-slate-200 px-2 py-1.5 text-sm focus:border-brand-400 focus:ring-brand-200"
                    />
                </label>
            </div>

            {/* Modes */}
            <div className="mt-2 flex gap-1">
                {MODE_OPTS.map((m) => (
                    <button
                        key={m.key}
                        type="button"
                        onClick={() => toggleMode(m.key)}
                        className={clsx(
                            'flex-1 rounded-lg px-2 py-1.5 text-xs font-medium transition',
                            modes.has(m.key) ? 'bg-brand-600 text-white' : 'bg-slate-100 text-slate-500 hover:bg-slate-200'
                        )}
                        title={m.label}
                    >
                        {m.icon} {m.label}
                    </button>
                ))}
            </div>

            <button
                type="button"
                onClick={runCalc}
                disabled={busy || !list?.has_home}
                className="mt-2 w-full rounded-lg bg-brand-600 px-3 py-2 text-sm font-semibold text-white transition hover:bg-brand-700 disabled:cursor-not-allowed disabled:opacity-60"
            >
                {busy ? 'Calculando…' : 'Recalcular tiempos'}
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
                            ✓ Tiempos calculados. Ordena/filtra por distancia para organizar.
                        </p>
                    )}
                    {status === 'error' && (
                        <p className="rounded-md bg-rose-50 px-2 py-1.5 font-medium text-rose-600">{message}</p>
                    )}
                </div>
            )}

            {list?.has_home && status === 'idle' && (
                <p className="mt-2 text-[11px] text-slate-400">
                    Origen: <span className="font-medium text-slate-600">{list.home_address}</span>
                </p>
            )}
        </div>
    );
}
