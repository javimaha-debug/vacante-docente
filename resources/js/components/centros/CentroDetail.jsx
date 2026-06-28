import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { useParams, Link } from 'react-router-dom';
import api from '../../lib/api';
import { useAuth } from '../../hooks/useAuth';

const CURSO = '2025-2026';

// GVA web values sometimes omit the scheme (e.g. "www.centro.es"); ensure the
// link is absolute so it doesn't resolve relative to the SPA.
function normalizeUrl(url) {
    if (!url) return url;
    return /^https?:\/\//i.test(url) ? url : `https://${url}`;
}

function Stars({ value }) {
    const full = Math.round(value || 0);
    return <span className="text-amber-500">{'★'.repeat(full)}{'☆'.repeat(Math.max(0, 5 - full))}</span>;
}

function ScoreRow({ label, value }) {
    if (!value) return null;
    return (
        <div className="flex items-center justify-between text-sm">
            <span className="text-slate-600">{label}</span>
            <span className="flex items-center gap-2"><Stars value={value} /> <span className="text-xs text-slate-400">{value}</span></span>
        </div>
    );
}

export default function CentroDetail() {
    const { codigo } = useParams();
    const queryClient = useQueryClient();
    const { isAuthenticated } = useAuth();
    const [tab, setTab] = useState(null); // 'horario' | 'valoracion' | null

    const { data, isLoading } = useQuery({
        queryKey: ['centro', codigo],
        queryFn: async () => (await api.get(`/centros/${codigo}`)).data,
    });

    const invalidate = () => queryClient.invalidateQueries({ queryKey: ['centro', codigo] });

    const horarioMut = useMutation({
        mutationFn: async (body) => (await api.post(`/centros/${codigo}/horarios`, body)).data,
        onSuccess: () => { invalidate(); setTab(null); },
    });
    const valoracionMut = useMutation({
        mutationFn: async (body) => (await api.post(`/centros/${codigo}/valoraciones`, body)).data,
        onSuccess: () => { invalidate(); setTab(null); },
    });

    if (isLoading) return <div className="flex h-40 items-center justify-center text-sm text-slate-400">Cargando…</div>;
    if (!data) return null;

    const { centro, horarios, valoraciones, vacantes } = data;
    const hasCoords = centro.latitude != null && centro.longitude != null;

    return (
        <div className="mx-auto max-w-3xl space-y-4">
            <Link to="/dashboard/centros" className="text-sm text-brand-600 hover:underline">← Volver al directorio</Link>

            <div className="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
                <div className="flex items-start justify-between gap-2">
                    <div>
                        <h1 className="text-lg font-bold text-slate-800">{centro.nombre}</h1>
                        <p className="text-sm text-slate-500">{centro.localidad} · {centro.provincia}</p>
                    </div>
                    <span className="rounded-full bg-brand-100 px-2 py-0.5 text-xs font-bold text-brand-700">{centro.tipo}</span>
                </div>
                <dl className="mt-3 grid grid-cols-1 gap-1 text-sm text-slate-600 sm:grid-cols-2">
                    {(centro.direccion_oficial || centro.direccion) && <div>📍 {centro.direccion_oficial || centro.direccion}</div>}
                    {centro.telefono && <div>📞 <a href={`tel:${centro.telefono}`} className="text-brand-600 hover:underline">{centro.telefono}</a></div>}
                    {centro.email && <div>✉️ <a href={`mailto:${centro.email}`} className="text-brand-600 hover:underline">{centro.email}</a></div>}
                    {centro.web && <div>🌐 <a href={normalizeUrl(centro.web)} target="_blank" rel="noreferrer" className="text-brand-600 hover:underline">Página web</a></div>}
                </dl>

                {centro.web && (
                    <a
                        href={normalizeUrl(centro.web)}
                        target="_blank"
                        rel="noreferrer"
                        className="mt-3 inline-flex items-center gap-1.5 rounded-lg bg-brand-600 px-3 py-2 text-sm font-semibold text-white hover:bg-brand-700"
                    >
                        🌐 Ir a web oficial
                    </a>
                )}

                {hasCoords && (
                    <iframe
                        title="Mapa"
                        className="mt-3 h-56 w-full rounded-xl border border-slate-200"
                        loading="lazy"
                        src={`https://maps.google.com/maps?q=${centro.latitude},${centro.longitude}&z=15&output=embed`}
                    />
                )}
            </div>

            {/* Horarios */}
            <div className="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
                <h2 className="text-sm font-bold text-slate-700">Horario</h2>
                <p className="mt-1 text-xs italic text-amber-600">Horario indicado por docentes - no verificado oficialmente</p>
                {horarios.length === 0 ? (
                    <p className="mt-2 text-sm text-slate-400">Aún no hay horarios aportados.</p>
                ) : (
                    <ul className="mt-2 space-y-2">
                        {horarios.map((h) => (
                            <li key={h.id} className="flex items-center justify-between rounded-lg bg-slate-50 px-3 py-2 text-sm">
                                <span>
                                    {h.hora_entrada ?? '—'}–{h.hora_salida ?? '—'}
                                    {h.jornada_continua ? ' · continua' : ''}
                                    {h.dia_libre ? ` · ${h.dia_libre}` : ''}
                                    <span className="ml-1 text-xs text-slate-400">({h.curso_escolar})</span>
                                </span>
                                <span className="rounded-full bg-green-100 px-2 py-0.5 text-xs font-bold text-green-700">{h.validaciones} ✓</span>
                            </li>
                        ))}
                    </ul>
                )}
                {isAuthenticated && (
                    <button onClick={() => setTab(tab === 'horario' ? null : 'horario')} className="mt-3 text-sm font-semibold text-brand-600 hover:text-brand-700">
                        + Añadir horario
                    </button>
                )}
                {tab === 'horario' && (
                    <HorarioForm pending={horarioMut.isPending} onSubmit={(b) => horarioMut.mutate(b)} />
                )}
            </div>

            {/* Valoraciones */}
            <div className="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
                <h2 className="text-sm font-bold text-slate-700">Valoraciones <span className="text-slate-400">({valoraciones.count})</span></h2>
                {valoraciones.count === 0 ? (
                    <p className="mt-2 text-sm text-slate-400">Sin valoraciones todavía.</p>
                ) : (
                    <div className="mt-2 space-y-1">
                        <ScoreRow label="Global" value={valoraciones.puntuacion} />
                        <ScoreRow label="Ambiente de trabajo" value={valoraciones.ambiente_trabajo} />
                        <ScoreRow label="Equipo directivo" value={valoraciones.equipo_directivo} />
                        <ScoreRow label="Instalaciones" value={valoraciones.instalaciones} />
                        {valoraciones.comentarios?.length > 0 && (
                            <ul className="mt-2 space-y-2">
                                {valoraciones.comentarios.map((c, i) => (
                                    <li key={i} className="rounded-lg bg-slate-50 px-3 py-2 text-sm text-slate-600">
                                        <Stars value={c.puntuacion} /> <span className="text-xs text-slate-400">({c.curso_escolar})</span>
                                        <p className="mt-1">{c.comentario}</p>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>
                )}
                {isAuthenticated && (
                    <button onClick={() => setTab(tab === 'valoracion' ? null : 'valoracion')} className="mt-3 text-sm font-semibold text-brand-600 hover:text-brand-700">
                        + Añadir valoración
                    </button>
                )}
                {tab === 'valoracion' && (
                    <ValoracionForm pending={valoracionMut.isPending} onSubmit={(b) => valoracionMut.mutate(b)} />
                )}
            </div>

            {/* Vacantes */}
            {vacantes?.length > 0 && (
                <div className="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
                    <h2 className="text-sm font-bold text-slate-700">Vacantes en el proceso actual</h2>
                    <ul className="mt-2 space-y-1 text-sm text-slate-600">
                        {vacantes.map((v) => (
                            <li key={v.id} className="flex justify-between">
                                <span>{v.localidad} · {v.tipo_centro}</span>
                                <span className="text-xs text-slate-400">nº {v.num}</span>
                            </li>
                        ))}
                    </ul>
                </div>
            )}
        </div>
    );
}

function HorarioForm({ onSubmit, pending }) {
    const [f, setF] = useState({ hora_entrada: '08:00', hora_salida: '15:00', jornada_continua: true, dia_libre: '', curso_escolar: CURSO, notas: '' });
    return (
        <div className="mt-3 grid grid-cols-2 gap-2 rounded-xl bg-slate-50 p-3">
            <label className="text-xs text-slate-500">Entrada<input type="time" value={f.hora_entrada} onChange={(e) => setF({ ...f, hora_entrada: e.target.value })} className="mt-1 w-full rounded border border-slate-300 px-2 py-1 text-sm" /></label>
            <label className="text-xs text-slate-500">Salida<input type="time" value={f.hora_salida} onChange={(e) => setF({ ...f, hora_salida: e.target.value })} className="mt-1 w-full rounded border border-slate-300 px-2 py-1 text-sm" /></label>
            <label className="col-span-2 flex items-center gap-2 text-xs text-slate-500"><input type="checkbox" checked={f.jornada_continua} onChange={(e) => setF({ ...f, jornada_continua: e.target.checked })} /> Jornada continua</label>
            <input placeholder="Día libre (opcional)" value={f.dia_libre} onChange={(e) => setF({ ...f, dia_libre: e.target.value })} className="col-span-2 rounded border border-slate-300 px-2 py-1 text-sm" />
            <button disabled={pending} onClick={() => onSubmit(f)} className="col-span-2 rounded-lg bg-brand-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-brand-700 disabled:opacity-60">Guardar horario</button>
        </div>
    );
}

function ValoracionForm({ onSubmit, pending }) {
    const [f, setF] = useState({ puntuacion: 4, ambiente_trabajo: 4, equipo_directivo: 4, instalaciones: 4, comentario: '', es_anonima: true, curso_escolar: CURSO });
    const num = (k) => (
        <label className="text-xs text-slate-500">{k}
            <select value={f[k]} onChange={(e) => setF({ ...f, [k]: Number(e.target.value) })} className="mt-1 w-full rounded border border-slate-300 px-2 py-1 text-sm">
                {[1, 2, 3, 4, 5].map((n) => <option key={n} value={n}>{n}</option>)}
            </select>
        </label>
    );
    return (
        <div className="mt-3 grid grid-cols-2 gap-2 rounded-xl bg-slate-50 p-3">
            {num('puntuacion')}{num('ambiente_trabajo')}{num('equipo_directivo')}{num('instalaciones')}
            <textarea placeholder="Comentario (opcional)" value={f.comentario} onChange={(e) => setF({ ...f, comentario: e.target.value })} className="col-span-2 rounded border border-slate-300 px-2 py-1 text-sm" rows={2} />
            <button disabled={pending} onClick={() => onSubmit(f)} className="col-span-2 rounded-lg bg-brand-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-brand-700 disabled:opacity-60">Guardar valoración</button>
        </div>
    );
}
