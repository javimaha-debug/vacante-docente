import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import clsx from 'clsx';
import api from '../lib/api';
import { LogoHorizontalTeal } from './brand/DoccentiaLogo';

const TABS = [
    { key: 'maestros', label: 'Maestros' },
    { key: 'secundaria', label: 'Secundaria' },
    { key: 'fp', label: 'FP' },
];

export default function SpecialtySelector({ onSelect, isSelecting }) {
    const [tab, setTab] = useState('secundaria');

    const { data, isLoading, isError } = useQuery({
        queryKey: ['specialties'],
        queryFn: async () => {
            const { data } = await api.get('/specialties');
            return data;
        },
    });

    const specialties = data?.[tab] ?? [];

    return (
        <div className="min-h-full bg-gradient-to-b from-brand-50 to-slate-100">
            <div className="mx-auto max-w-5xl px-4 py-12 sm:py-16">
                <header className="text-center">
                    <div className="mb-3 inline-flex items-center gap-2 rounded-full bg-brand-600 px-3 py-1 text-xs font-semibold text-white">
                        Comunitat Valenciana · Curso 2025
                    </div>
                    <LogoHorizontalTeal className="mx-auto h-10 w-auto sm:h-12" />
                    <h1 className="sr-only">Doccentia</h1>
                    <p className="mx-auto mt-3 max-w-xl text-sm text-slate-500">
                        Organiza, filtra y prioriza las vacantes de la adjudicación docente. Empieza eligiendo tu
                        especialidad.
                    </p>
                </header>

                <div className="mt-8 flex justify-center">
                    <div className="inline-flex rounded-xl bg-white p-1 shadow-sm ring-1 ring-slate-200">
                        {TABS.map((t) => (
                            <button
                                key={t.key}
                                onClick={() => setTab(t.key)}
                                className={clsx(
                                    'rounded-lg px-5 py-2 text-sm font-semibold transition',
                                    tab === t.key ? 'bg-brand-600 text-white shadow' : 'text-slate-500 hover:text-slate-800'
                                )}
                            >
                                {t.label}
                            </button>
                        ))}
                    </div>
                </div>

                <div className="mt-8">
                    {isLoading && <p className="text-center text-sm text-slate-400">Cargando especialidades…</p>}
                    {isError && (
                        <p className="text-center text-sm text-rose-500">No se pudieron cargar las especialidades.</p>
                    )}

                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        {specialties.map((s) => {
                            const disabled = s.vacancy_count === 0 || isSelecting;
                            return (
                                <button
                                    key={s.id}
                                    disabled={disabled}
                                    onClick={() => onSelect(s)}
                                    className={clsx(
                                        'group relative flex flex-col rounded-xl border bg-white p-4 text-left shadow-sm transition',
                                        s.vacancy_count > 0
                                            ? 'border-slate-200 hover:-translate-y-0.5 hover:border-brand-300 hover:shadow-md'
                                            : 'cursor-not-allowed border-slate-100 opacity-60'
                                    )}
                                >
                                    <div className="flex items-start justify-between gap-2">
                                        <span className="rounded-md bg-slate-100 px-1.5 py-0.5 font-mono text-[11px] font-semibold text-slate-500">
                                            {s.code}
                                        </span>
                                        <span
                                            className={clsx(
                                                'rounded-full px-2 py-0.5 text-[11px] font-bold',
                                                s.vacancy_count > 0
                                                    ? 'bg-brand-100 text-brand-700'
                                                    : 'bg-slate-100 text-slate-400'
                                            )}
                                        >
                                            {s.vacancy_count} {s.vacancy_count === 1 ? 'vacante' : 'vacantes'}
                                        </span>
                                    </div>
                                    <h3 className="mt-2 text-sm font-semibold leading-snug text-slate-900">{s.name}</h3>
                                    <p className="mt-1 text-[11px] text-slate-400">{s.body}</p>
                                </button>
                            );
                        })}
                    </div>

                    {!isLoading && specialties.length === 0 && (
                        <p className="text-center text-sm text-slate-400">No hay especialidades en esta categoría.</p>
                    )}
                </div>
            </div>
        </div>
    );
}
