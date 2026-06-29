import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import clsx from 'clsx';
import api from '../../lib/api';

const TIPO_LABELS = {
    rubrica: 'Rúbrica',
    situacion_aprendizaje: 'Sit. Aprendizaje',
    actividad: 'Actividad',
    examen: 'Examen',
};
const TIPO_OPTS = ['', 'rubrica', 'situacion_aprendizaje', 'actividad', 'examen'];

function StarRating({ value, onChange }) {
    return (
        <div className="flex gap-0.5">
            {[1, 2, 3, 4, 5].map((n) => (
                <button
                    key={n}
                    onClick={() => onChange(n)}
                    className={clsx('text-lg leading-none', n <= value ? 'text-amber-400' : 'text-slate-200 hover:text-amber-300')}
                >
                    ★
                </button>
            ))}
        </div>
    );
}

function RecursoCard({ recurso, onCopied }) {
    const qc = useQueryClient();
    const [rating, setRating] = useState(0);
    const [voted, setVoted] = useState(false);

    const valorarMutation = useMutation({
        mutationFn: (puntuacion) => api.post(`/docente/banco/${recurso.id}/valorar`, { puntuacion }),
        onSuccess: () => { setVoted(true); qc.invalidateQueries({ queryKey: ['docente-banco'] }); },
    });

    const copiarMutation = useMutation({
        mutationFn: () => api.post(`/docente/banco/${recurso.id}/copiar`),
        onSuccess: () => { onCopied?.(); },
    });

    const handleRating = (n) => {
        if (voted) return;
        setRating(n);
        valorarMutation.mutate(n);
    };

    return (
        <div className="rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
            <div className="flex items-start gap-3">
                <div className="flex-1 min-w-0">
                    <div className="flex items-center gap-2 mb-1">
                        <span className="rounded-full bg-brand-50 px-2 py-0.5 text-[10px] font-semibold text-brand-700">
                            {TIPO_LABELS[recurso.tipo] ?? recurso.tipo}
                        </span>
                        {recurso.etapa && (
                            <span className="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] text-slate-500">{recurso.etapa}</span>
                        )}
                    </div>
                    <p className="truncate text-sm font-semibold text-slate-800">{recurso.titulo}</p>
                    <p className="mt-0.5 text-xs text-slate-400">
                        {recurso.autor ?? 'Docente anónimo'}
                        {recurso.asignatura && ` · ${recurso.asignatura}`}
                    </p>
                </div>
                <div className="shrink-0 text-right">
                    <div className="flex items-center gap-1 justify-end">
                        <span className="text-amber-400 text-sm">★</span>
                        <span className="text-sm font-semibold text-slate-700">{Number(recurso.valoracion_media ?? 0).toFixed(1)}</span>
                        <span className="text-xs text-slate-400">({recurso.num_valoraciones ?? 0})</span>
                    </div>
                    <p className="text-[11px] text-slate-400">{recurso.descargas ?? 0} copias</p>
                </div>
            </div>

            <div className="mt-3 flex items-center justify-between gap-2">
                <div>
                    {!voted ? (
                        <div className="flex items-center gap-1">
                            <span className="text-xs text-slate-400">Valorar:</span>
                            <StarRating value={rating} onChange={handleRating} />
                        </div>
                    ) : (
                        <span className="text-xs text-emerald-600">✓ Valorado</span>
                    )}
                </div>
                <button
                    onClick={() => copiarMutation.mutate()}
                    disabled={copiarMutation.isPending}
                    className="btn-ghost text-xs"
                >
                    {copiarMutation.isPending ? 'Copiando…' : 'Copiar a mis recursos'}
                </button>
            </div>
        </div>
    );
}

export default function BancoPage() {
    const qc = useQueryClient();
    const [tipo, setTipo] = useState('');
    const [search, setSearch] = useState('');

    const { data: banco = [], isLoading } = useQuery({
        queryKey: ['docente-banco', tipo],
        queryFn: async () => {
            const params = tipo ? { tipo } : {};
            return (await api.get('/docente/banco', { params })).data.data ?? [];
        },
    });

    const filtrados = search
        ? banco.filter((r) => r.titulo?.toLowerCase().includes(search.toLowerCase()))
        : banco;

    const handleCopied = () => {
        qc.invalidateQueries({ queryKey: ['docente-rubricas'] });
        qc.invalidateQueries({ queryKey: ['docente-situaciones'] });
        qc.invalidateQueries({ queryKey: ['docente-examenes'] });
    };

    return (
        <div className="space-y-4">
            <div className="flex items-center justify-between">
                <h1 className="text-lg font-bold text-slate-800">Banco compartido</h1>
                <p className="text-xs text-slate-400">Recursos validados por la comunidad</p>
            </div>

            <div className="flex flex-wrap gap-2">
                <input
                    className="input max-w-xs"
                    placeholder="Buscar…"
                    value={search}
                    onChange={(e) => setSearch(e.target.value)}
                />
                <div className="flex gap-1">
                    {TIPO_OPTS.map((t) => (
                        <button
                            key={t}
                            onClick={() => setTipo(t)}
                            className={clsx(
                                'rounded-lg px-3 py-1.5 text-xs font-medium transition',
                                tipo === t ? 'bg-brand-600 text-white' : 'bg-white text-slate-600 ring-1 ring-slate-200 hover:bg-slate-50'
                            )}
                        >
                            {t ? TIPO_LABELS[t] : 'Todos'}
                        </button>
                    ))}
                </div>
            </div>

            {isLoading ? (
                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    {[1, 2, 3, 4, 5, 6].map((i) => (
                        <div key={i} className="h-32 animate-pulse rounded-xl bg-slate-200" />
                    ))}
                </div>
            ) : filtrados.length === 0 ? (
                <div className="rounded-2xl bg-white p-10 text-center shadow-sm ring-1 ring-slate-200">
                    <p className="text-sm text-slate-400">
                        {banco.length === 0
                            ? 'El banco está vacío. ¡Sé el primero en compartir un recurso desde Mis recursos!'
                            : 'Sin resultados para ese filtro.'}
                    </p>
                </div>
            ) : (
                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    {filtrados.map((r) => (
                        <RecursoCard key={r.id} recurso={r} onCopied={handleCopied} />
                    ))}
                </div>
            )}
        </div>
    );
}
