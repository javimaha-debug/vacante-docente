import { useMemo, useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import clsx from 'clsx';
import api from '../../lib/api';

const CURRENT_YEAR = 2026;

const ESTADOS = ['Activat', 'Desactivat', 'Adjudicat'];
const ESTADO_STYLES = {
    Activat: 'bg-green-100 text-green-700',
    Desactivat: 'bg-slate-100 text-slate-600',
    Adjudicat: 'bg-blue-100 text-blue-700',
};

function EstadoBadge({ estado }) {
    return (
        <span className={clsx('rounded-full px-2 py-0.5 text-xs font-bold', ESTADO_STYLES[estado] ?? 'bg-slate-100 text-slate-600')}>
            {estado ?? '—'}
        </span>
    );
}

function AddSpecialtyModal({ onClose, onAdd, existingIds }) {
    const [search, setSearch] = useState('');

    const { data, isLoading } = useQuery({
        queryKey: ['specialties'],
        queryFn: async () => (await api.get('/specialties')).data,
    });

    const all = useMemo(() => {
        if (!data) return [];
        return [...(data.maestros ?? []), ...(data.secundaria ?? []), ...(data.fp ?? [])];
    }, [data]);

    const filtered = all.filter(
        (s) =>
            !existingIds.has(s.id) &&
            (s.name.toLowerCase().includes(search.toLowerCase()) || String(s.code).includes(search))
    );

    return (
        <div className="fixed inset-0 z-40 flex items-center justify-center bg-slate-900/40 p-4" onClick={onClose}>
            <div
                className="flex max-h-[80vh] w-full max-w-md flex-col rounded-2xl bg-white shadow-xl"
                onClick={(e) => e.stopPropagation()}
            >
                <div className="border-b border-slate-200 p-4">
                    <h3 className="text-sm font-bold text-slate-800">Añadir especialidad</h3>
                    <input
                        autoFocus
                        type="text"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder="Buscar por nombre o código…"
                        className="mt-2 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-400 focus:ring-brand-400"
                    />
                </div>
                <div className="scroll-thin flex-1 overflow-y-auto p-2">
                    {isLoading ? (
                        <p className="p-4 text-sm text-slate-400">Cargando…</p>
                    ) : filtered.length === 0 ? (
                        <p className="p-4 text-sm text-slate-400">Sin resultados.</p>
                    ) : (
                        filtered.map((s) => (
                            <button
                                key={s.id}
                                onClick={() => onAdd(s)}
                                className="flex w-full items-center justify-between rounded-lg px-3 py-2 text-left text-sm hover:bg-brand-50"
                            >
                                <span className="text-slate-700">{s.name}</span>
                                <span className="text-xs text-slate-400">{s.code}</span>
                            </button>
                        ))
                    )}
                </div>
                <div className="border-t border-slate-200 p-3 text-right">
                    <button onClick={onClose} className="rounded-lg px-3 py-1.5 text-sm font-medium text-slate-600 hover:bg-slate-100">
                        Cerrar
                    </button>
                </div>
            </div>
        </div>
    );
}

export default function MisEspecialidades() {
    const queryClient = useQueryClient();
    const [modalOpen, setModalOpen] = useState(false);

    const { data: profile, isLoading } = useQuery({
        queryKey: ['profile'],
        queryFn: async () => (await api.get('/user/profile')).data,
    });

    const invalidate = () => {
        queryClient.invalidateQueries({ queryKey: ['profile'] });
        queryClient.invalidateQueries({ queryKey: ['dashboard'] });
    };

    const upsert = useMutation({
        mutationFn: async (payload) => (await api.post('/user/especialidades', payload)).data,
        onSuccess: invalidate,
    });

    const remove = useMutation({
        mutationFn: async (specialtyId) => (await api.delete(`/user/especialidades/${specialtyId}`)).data,
        onSuccess: invalidate,
    });

    if (isLoading) {
        return <div className="flex h-40 items-center justify-center text-sm text-slate-400">Cargando…</div>;
    }

    const especialidades = profile?.especialidades ?? [];
    const existingIds = new Set(especialidades.map((e) => e.specialty_id));

    const handleAdd = (specialty) => {
        upsert.mutate({
            specialty_id: specialty.id,
            anyo: CURRENT_YEAR,
            estado_bolsa: 'Activat',
            posicion_bolsa: null,
        });
        setModalOpen(false);
    };

    const handleField = (esp, field, value) => {
        upsert.mutate({
            specialty_id: esp.specialty_id,
            anyo: esp.anyo,
            posicion_bolsa: field === 'posicion_bolsa' ? (value === '' ? null : Number(value)) : esp.posicion_bolsa,
            estado_bolsa: field === 'estado_bolsa' ? value : esp.estado_bolsa,
        });
    };

    return (
        <div className="mx-auto max-w-3xl">
            <div className="mb-4 flex items-center justify-between">
                <h1 className="text-lg font-bold text-slate-800">Mis Especialidades</h1>
                <button
                    onClick={() => setModalOpen(true)}
                    className="rounded-lg bg-brand-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-brand-700"
                >
                    + Añadir especialidad
                </button>
            </div>

            {especialidades.length === 0 ? (
                <div className="rounded-2xl bg-white p-8 text-center shadow-sm ring-1 ring-slate-200">
                    <p className="text-sm text-slate-400">
                        Aún no has añadido ninguna especialidad. Añade las bolsas en las que estás inscrito.
                    </p>
                </div>
            ) : (
                <ul className="space-y-3">
                    {especialidades.map((esp) => (
                        <li
                            key={`${esp.specialty_id}-${esp.anyo}`}
                            className="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200"
                        >
                            <div className="flex items-start justify-between gap-3">
                                <div>
                                    <p className="text-sm font-semibold text-slate-800">{esp.specialty_name}</p>
                                    <p className="mt-0.5 text-xs text-slate-400">Curso {esp.anyo}</p>
                                </div>
                                <div className="flex items-center gap-2">
                                    <EstadoBadge estado={esp.estado_bolsa} />
                                    <button
                                        onClick={() => remove.mutate(esp.specialty_id)}
                                        className="rounded-lg p-1.5 text-slate-400 hover:bg-red-50 hover:text-red-600"
                                        aria-label="Eliminar"
                                    >
                                        ✕
                                    </button>
                                </div>
                            </div>

                            <div className="mt-3 grid grid-cols-2 gap-3">
                                <label className="text-xs font-medium text-slate-500">
                                    Posición en bolsa
                                    <input
                                        type="number"
                                        min="1"
                                        defaultValue={esp.posicion_bolsa ?? ''}
                                        onBlur={(e) => handleField(esp, 'posicion_bolsa', e.target.value)}
                                        className="mt-1 w-full rounded-lg border border-slate-300 px-2 py-1.5 text-sm text-slate-800 focus:border-brand-400 focus:ring-brand-400"
                                    />
                                </label>
                                <label className="text-xs font-medium text-slate-500">
                                    Estado
                                    <select
                                        value={esp.estado_bolsa ?? ''}
                                        onChange={(e) => handleField(esp, 'estado_bolsa', e.target.value)}
                                        className="mt-1 w-full rounded-lg border border-slate-300 px-2 py-1.5 text-sm text-slate-800 focus:border-brand-400 focus:ring-brand-400"
                                    >
                                        <option value="">—</option>
                                        {ESTADOS.map((s) => (
                                            <option key={s} value={s}>
                                                {s}
                                            </option>
                                        ))}
                                    </select>
                                </label>
                            </div>
                        </li>
                    ))}
                </ul>
            )}

            {modalOpen && (
                <AddSpecialtyModal onClose={() => setModalOpen(false)} onAdd={handleAdd} existingIds={existingIds} />
            )}
        </div>
    );
}
