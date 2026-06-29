import { useEffect, useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import clsx from 'clsx';
import api from '../../lib/api';
import { useDebounce } from '../../hooks/useDebounce';
import PushToggle from './PushToggle';
import PrivacidadCuenta from './PrivacidadCuenta';

function Toggle({ checked, onChange, label }) {
    return (
        <button type="button" role="switch" aria-checked={checked} onClick={() => onChange(!checked)} className="flex items-center gap-3">
            <span className={clsx('relative inline-flex h-6 w-11 items-center rounded-full transition', checked ? 'bg-brand-600' : 'bg-slate-300')}>
                <span className={clsx('inline-block h-5 w-5 transform rounded-full bg-white shadow transition', checked ? 'translate-x-5' : 'translate-x-0.5')} />
            </span>
            <span className="text-sm text-slate-700">{label}</span>
        </button>
    );
}

// Address field with Google-backed autocomplete + verification state.
function AddressAutocomplete({ value, onChange, onSelect, verified }) {
    const [open, setOpen] = useState(false);
    const debounced = useDebounce(value, 600);

    const { data, isFetching } = useQuery({
        queryKey: ['geocode', debounced],
        enabled: Boolean(debounced && debounced.length >= 3 && !verified),
        queryFn: async () => (await api.get('/geocode', { params: { address: debounced } })).data,
        staleTime: 60_000,
    });

    const suggestions = data?.data ?? [];

    return (
        <div className="relative">
            <label className="block text-sm font-medium text-slate-700">Dirección de origen</label>
            <input
                type="text"
                value={value}
                onChange={(e) => {
                    onChange(e.target.value);
                    setOpen(true);
                }}
                onFocus={() => setOpen(true)}
                onBlur={() => setTimeout(() => setOpen(false), 150)}
                placeholder="Carrer, número, localidad"
                className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-400 focus:ring-brand-400"
                autoComplete="off"
            />

            {open && !verified && suggestions.length > 0 && (
                <ul className="absolute z-10 mt-1 max-h-60 w-full overflow-auto rounded-lg border border-slate-200 bg-white shadow-lg">
                    {suggestions.map((s, i) => (
                        <li key={i}>
                            <button
                                type="button"
                                onMouseDown={(e) => e.preventDefault()}
                                onClick={() => {
                                    onSelect(s);
                                    setOpen(false);
                                }}
                                className="block w-full px-3 py-2 text-left text-sm text-slate-700 hover:bg-brand-50"
                            >
                                {s.formatted_address}
                            </button>
                        </li>
                    ))}
                </ul>
            )}

            {value && (
                <p className={clsx('mt-1 flex items-center gap-1 text-xs', verified ? 'text-green-600' : 'text-amber-600')}>
                    {verified ? (
                        <>✓ Dirección verificada</>
                    ) : isFetching ? (
                        <>Buscando direcciones…</>
                    ) : (
                        <>⚠ Dirección no verificada — las distancias pueden ser inexactas</>
                    )}
                </p>
            )}
        </div>
    );
}

export default function UserProfile() {
    const queryClient = useQueryClient();
    const [form, setForm] = useState(null);
    const [coords, setCoords] = useState({ lat: null, lng: null, verified: false });
    const [saved, setSaved] = useState(false);

    const { data: profile, isLoading } = useQuery({
        queryKey: ['profile'],
        queryFn: async () => (await api.get('/user/profile')).data,
    });

    const { data: colectivos } = useQuery({
        queryKey: ['colectivos'],
        queryFn: async () => (await api.get('/colectivos')).data,
    });

    useEffect(() => {
        if (profile) {
            setForm({
                nombre_gva: profile.nombre_gva ?? '',
                colectivo_id: profile.colectivo?.id ?? '',
                direccion_origen: profile.direccion_origen ?? '',
                locale: profile.locale ?? 'es',
                notificaciones_email: Boolean(profile.notificaciones_email),
            });
            // Treat an address already stored with coordinates as verified.
            setCoords({
                lat: profile.lat_origen,
                lng: profile.lng_origen,
                verified: Boolean(profile.direccion_origen && profile.lat_origen && profile.lng_origen),
            });
        }
    }, [profile]);

    const mutation = useMutation({
        mutationFn: async (payload) => (await api.put('/user/profile', payload)).data,
        onSuccess: () => {
            setSaved(true);
            queryClient.invalidateQueries({ queryKey: ['profile'] });
            queryClient.invalidateQueries({ queryKey: ['dashboard'] });
            setTimeout(() => setSaved(false), 2500);
        },
    });

    if (isLoading || !form) {
        return <div className="flex h-40 items-center justify-center text-sm text-slate-400">Cargando…</div>;
    }

    const update = (key, value) => setForm((f) => ({ ...f, [key]: value }));

    const handleSubmit = (e) => {
        e.preventDefault();
        const payload = {
            ...form,
            colectivo_id: form.colectivo_id === '' ? null : Number(form.colectivo_id),
        };
        // Send verified coordinates so the server skips re-geocoding.
        if (coords.verified && coords.lat != null && coords.lng != null) {
            payload.lat_origen = coords.lat;
            payload.lng_origen = coords.lng;
        }
        mutation.mutate(payload);
    };

    return (
        <div className="mx-auto max-w-2xl space-y-6">
        <form onSubmit={handleSubmit} className="space-y-6">
            <div className="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200 sm:p-6">
                <h1 className="font-heading text-xl font-bold text-slate-800">Mi Perfil</h1>
                <p className="mt-1 text-sm text-slate-500">Estos datos personalizan tu panel y los cálculos de distancia.</p>

                <div className="mt-5 space-y-5">
                    <div>
                        <label className="block text-sm font-medium text-slate-700">Nombre GVA</label>
                        <input
                            type="text"
                            value={form.nombre_gva}
                            onChange={(e) => update('nombre_gva', e.target.value)}
                            placeholder="APELLIDO1 APELLIDO2, NOMBRE"
                            className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-400 focus:ring-brand-400"
                        />
                        <p className="mt-1 text-xs text-slate-400">
                            Escríbelo en el formato <span className="font-semibold">APELLIDO1 APELLIDO2, NOMBRE</span> tal y como aparece en los documentos de la GVA.
                        </p>
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-slate-700">Colectivo</label>
                        <select
                            value={form.colectivo_id}
                            onChange={(e) => update('colectivo_id', e.target.value)}
                            className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-400 focus:ring-brand-400"
                        >
                            <option value="">Sin especificar</option>
                            {(colectivos?.data ?? []).map((c) => (
                                <option key={c.id} value={c.id}>{c.name} · {c.body}</option>
                            ))}
                        </select>
                    </div>

                    <AddressAutocomplete
                        value={form.direccion_origen}
                        verified={coords.verified}
                        onChange={(v) => {
                            update('direccion_origen', v);
                            setCoords((c) => ({ ...c, verified: false }));
                        }}
                        onSelect={(s) => {
                            update('direccion_origen', s.formatted_address);
                            setCoords({ lat: s.lat, lng: s.lng, verified: true });
                        }}
                    />

                    <div>
                        <span className="block text-sm font-medium text-slate-700">Idioma</span>
                        <div className="mt-1 inline-flex rounded-lg bg-slate-100 p-0.5">
                            {[{ key: 'es', label: 'Castellano' }, { key: 'ca', label: 'Valencià' }].map((opt) => (
                                <button
                                    key={opt.key}
                                    type="button"
                                    onClick={() => update('locale', opt.key)}
                                    className={clsx('rounded-md px-3 py-1.5 text-sm font-medium transition', form.locale === opt.key ? 'bg-white text-brand-700 shadow-sm' : 'text-slate-500 hover:text-slate-700')}
                                >
                                    {opt.label}
                                </button>
                            ))}
                        </div>
                    </div>

                    <Toggle checked={form.notificaciones_email} onChange={(v) => update('notificaciones_email', v)} label="Recibir notificaciones por email" />

                    <PushToggle />
                </div>
            </div>

            <div className="flex items-center gap-3">
                <button type="submit" disabled={mutation.isPending} className="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700 disabled:opacity-60">
                    {mutation.isPending ? 'Guardando…' : 'Guardar cambios'}
                </button>
                {saved && <span className="text-sm font-medium text-green-600">Guardado ✓</span>}
                {mutation.isError && <span className="text-sm text-red-600">{mutation.error?.friendlyMessage}</span>}
            </div>
        </form>

        <PrivacidadCuenta />
        </div>
    );
}
