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

    useEffect(() => {
        if (value || procesos.length === 0) return;
        const publicados = procesos.filter((p) => p.estado === 'publicado');
        const preferred =
            (colectivoBody && publicados.find((p) => p.colectivo?.body === colectivoBody)) ||
            publicados[0] ||
            procesos[0];
        if (preferred) onChange(preferred.id);
    }, [procesos, value, colectivoBody, onChange]);

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
