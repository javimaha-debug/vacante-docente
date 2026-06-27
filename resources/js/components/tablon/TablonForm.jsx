import { useMemo, useState } from 'react';
import { useQuery, useMutation } from '@tanstack/react-query';
import { useNavigate } from 'react-router-dom';
import api from '../../lib/api';
import { useDebounce } from '../../hooks/useDebounce';

const CATEGORIAS = [
    { key: 'general', label: 'General' },
    { key: 'coche', label: 'Compartir coche' },
    { key: 'alojamiento', label: 'Alojamiento' },
    { key: 'centro', label: 'Sobre un centro' },
];

export default function TablonForm() {
    const navigate = useNavigate();
    const [f, setF] = useState({
        categoria: 'general', titulo: '', contenido: '',
        localidad_origen: '', localidad_destino: '', centro_id: null, centro_nombre: '', specialty_id: '',
    });
    const [preview, setPreview] = useState(false);
    const [centroSearch, setCentroSearch] = useState('');
    const debouncedCentro = useDebounce(centroSearch, 400);

    const { data: specialties } = useQuery({
        queryKey: ['specialties'],
        queryFn: async () => (await api.get('/specialties')).data,
    });
    const allSpecialties = useMemo(() => {
        if (!specialties) return [];
        return [...(specialties.maestros ?? []), ...(specialties.secundaria ?? []), ...(specialties.fp ?? [])];
    }, [specialties]);

    const { data: centros } = useQuery({
        queryKey: ['centros-search', debouncedCentro],
        enabled: f.categoria === 'centro' && debouncedCentro.length >= 3,
        queryFn: async () => (await api.get('/centros', { params: { query: debouncedCentro } })).data,
    });

    const mut = useMutation({
        mutationFn: async () => {
            const payload = {
                categoria: f.categoria,
                titulo: f.titulo,
                contenido: f.contenido,
                localidad_origen: f.localidad_origen || null,
                localidad_destino: f.localidad_destino || null,
                centro_id: f.centro_id,
                specialty_id: f.specialty_id === '' ? null : Number(f.specialty_id),
            };
            return (await api.post('/tablon', payload)).data;
        },
        onSuccess: () => navigate('/dashboard/tablon'),
    });

    const set = (k, v) => setF((s) => ({ ...s, [k]: v }));
    const isCoche = f.categoria === 'coche';
    const isAloja = f.categoria === 'alojamiento';
    const isCentro = f.categoria === 'centro';
    const valid = f.titulo.trim() && f.contenido.trim();

    return (
        <div className="mx-auto max-w-2xl">
            <h1 className="mb-4 text-lg font-bold text-slate-800">Publicar anuncio</h1>

            <div className="space-y-4 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
                <div>
                    <label className="block text-sm font-medium text-slate-700">Categoría</label>
                    <select value={f.categoria} onChange={(e) => set('categoria', e.target.value)} className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        {CATEGORIAS.map((c) => <option key={c.key} value={c.key}>{c.label}</option>)}
                    </select>
                </div>

                <div>
                    <label className="block text-sm font-medium text-slate-700">Título</label>
                    <input value={f.titulo} onChange={(e) => set('titulo', e.target.value)} maxLength={200} className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-400 focus:ring-brand-400" />
                </div>

                <div>
                    <label className="block text-sm font-medium text-slate-700">Contenido</label>
                    <textarea value={f.contenido} onChange={(e) => set('contenido', e.target.value)} rows={4} className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-400 focus:ring-brand-400" />
                </div>

                {(isCoche || isAloja) && (
                    <div>
                        <label className="block text-sm font-medium text-slate-700">Localidad de origen</label>
                        <input value={f.localidad_origen} onChange={(e) => set('localidad_origen', e.target.value)} className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                    </div>
                )}
                {isCoche && (
                    <div>
                        <label className="block text-sm font-medium text-slate-700">Localidad de destino</label>
                        <input value={f.localidad_destino} onChange={(e) => set('localidad_destino', e.target.value)} className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                    </div>
                )}

                {isCentro && (
                    <div className="relative">
                        <label className="block text-sm font-medium text-slate-700">Centro</label>
                        <input
                            value={f.centro_nombre || centroSearch}
                            onChange={(e) => { setCentroSearch(e.target.value); set('centro_id', null); set('centro_nombre', ''); }}
                            placeholder="Buscar centro…"
                            className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                        />
                        {!f.centro_id && centros?.data?.length > 0 && (
                            <ul className="absolute z-10 mt-1 max-h-48 w-full overflow-auto rounded-lg border border-slate-200 bg-white shadow-lg">
                                {centros.data.map((c) => (
                                    <li key={c.id}>
                                        <button type="button" onClick={() => { set('centro_id', c.id); set('centro_nombre', c.nombre); }} className="block w-full px-3 py-2 text-left text-sm hover:bg-brand-50">
                                            {c.nombre} <span className="text-xs text-slate-400">{c.localidad}</span>
                                        </button>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>
                )}

                <div>
                    <label className="block text-sm font-medium text-slate-700">Especialidad (opcional)</label>
                    <select value={f.specialty_id} onChange={(e) => set('specialty_id', e.target.value)} className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <option value="">—</option>
                        {allSpecialties.map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
                    </select>
                </div>
            </div>

            {preview && (
                <div className="mt-4 rounded-2xl border-2 border-dashed border-brand-200 bg-white p-4">
                    <p className="text-xs font-bold uppercase text-brand-600">Vista previa</p>
                    <p className="mt-2 text-sm font-semibold text-slate-800">{f.titulo || '(sin título)'}</p>
                    <p className="mt-1 text-sm text-slate-600">{f.contenido || '(sin contenido)'}</p>
                </div>
            )}

            <div className="mt-4 flex items-center gap-3">
                <button onClick={() => setPreview((p) => !p)} className="rounded-lg px-3 py-2 text-sm font-medium text-slate-600 ring-1 ring-slate-200 hover:bg-slate-50">
                    {preview ? 'Ocultar vista previa' : 'Vista previa'}
                </button>
                <button
                    disabled={!valid || mut.isPending}
                    onClick={() => mut.mutate()}
                    className="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700 disabled:opacity-60"
                >
                    {mut.isPending ? 'Publicando…' : 'Publicar'}
                </button>
                {mut.isError && <span className="text-sm text-red-600">{mut.error?.friendlyMessage}</span>}
            </div>
        </div>
    );
}
