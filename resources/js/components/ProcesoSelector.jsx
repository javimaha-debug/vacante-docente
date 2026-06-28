import { useEffect } from 'react';
import { useQuery } from '@tanstack/react-query';
import api from '../lib/api';

// Proceso picker shown above the explorer. Defaults to the most recent
// "publicado" proceso (optionally matching the user's colectivo body).
export default function ProcesoSelector({ value, onChange, colectivoBody = null }) {
    const { data } = useQuery({
        queryKey: ['procesos'],
        queryFn: async () => (await api.get('/procesos')).data,
    });

    const procesos = data?.data ?? [];

    // The explorer shows plazas (vacantes). Interim ("interins") procesos hold
    // the participant bolsa, not plazas, so only offer procesos that actually
    // have vacancies (fall back to all if none are loaded yet).
    const withPlazas = procesos.filter((p) => (p.vacancies_count ?? 0) > 0);
    const options = withPlazas.length ? withPlazas : procesos;

    useEffect(() => {
        if (options.length === 0) return;
        // Keep the current selection only if it points to a proceso with plazas;
        // otherwise (e.g. a remembered interins proceso) pick a valid default.
        if (value && options.some((p) => p.id === value)) return;
        const publicados = options.filter((p) => p.estado === 'publicado');
        const pool = publicados.length ? publicados : options;
        const preferred =
            (colectivoBody && pool.find((p) => p.colectivo?.body === colectivoBody)) || pool[0];
        if (preferred) onChange(preferred.id);
    }, [options, value, colectivoBody, onChange]);

    if (procesos.length === 0) return null;

    return (
        <div className="flex items-center gap-2">
            <label className="text-xs font-semibold text-slate-500">Proceso</label>
            <select
                value={value ?? ''}
                onChange={(e) => onChange(Number(e.target.value))}
                className="rounded-lg border border-slate-300 bg-white px-2 py-1.5 text-sm focus:border-brand-400 focus:ring-brand-400"
            >
                {procesos.map((p) => (
                    <option key={p.id} value={p.id}>
                        {p.nombre} ({p.estado})
                    </option>
                ))}
            </select>
        </div>
    );
}
