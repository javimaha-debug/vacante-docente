import { useMemo, useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import api from '../../lib/api';
import { useAuth } from '../../hooks/useAuth';
import { useDebounce } from '../../hooks/useDebounce';

const MODOS = [
    { value: 'bolsa', label: 'Bolsa de interinidades', desc: 'Gestiono mis vacantes y mi posición en la bolsa.', icon: '🗂️' },
    { value: 'oposicion', label: 'Preparo oposición', desc: 'Estudio y me preparo para las oposiciones.', icon: '📚' },
    { value: 'docente', label: 'Docente en activo', desc: 'Busco recursos y herramientas para el aula.', icon: '🍎' },
];

/**
 * Full-screen onboarding wizard. Shown when onboarding_completed === false and
 * cannot be dismissed. Persists everything via PUT /user/onboarding.
 */
export default function OnboardingWizard() {
    const { refresh } = useAuth();
    const [step, setStep] = useState(0);
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState(null);

    const [modo, setModo] = useState('bolsa');
    const [especialidades, setEspecialidades] = useState([]);
    const [direccion, setDireccion] = useState('');
    const [coords, setCoords] = useState(null);
    const [nombreGva, setNombreGva] = useState('');

    // The steps shown depend on the chosen mode (nombre GVA only for bolsa).
    const steps = useMemo(() => {
        const base = ['modo', 'especialidades', 'ccaa', 'direccion'];
        if (modo === 'bolsa') base.push('nombre_gva');
        base.push('resumen');
        return base;
    }, [modo]);

    const current = steps[step];
    const isLast = step === steps.length - 1;

    const canAdvance = () => {
        if (current === 'especialidades') return especialidades.length >= 1;
        return true;
    };

    const next = () => setStep((s) => Math.min(s + 1, steps.length - 1));
    const back = () => setStep((s) => Math.max(s - 1, 0));

    const finish = async () => {
        setSaving(true);
        setError(null);
        try {
            await api.put('/user/onboarding', {
                modo_activo: modo,
                especialidades,
                direccion_origen: direccion || null,
                lat_origen: coords?.lat ?? null,
                lng_origen: coords?.lng ?? null,
                nombre_gva: modo === 'bolsa' ? (nombreGva || null) : null,
            });
            await refresh();
            // refresh() flips onboarding_completed; the wrapper unmounts this.
        } catch (e) {
            setError(e?.friendlyMessage || 'No se pudo guardar. Inténtalo de nuevo.');
            setSaving(false);
        }
    };

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/95 p-4">
            <div className="flex max-h-full w-full max-w-lg flex-col overflow-hidden rounded-2xl bg-white shadow-2xl">
                {/* Progress */}
                <div className="flex gap-1 p-4">
                    {steps.map((s, i) => (
                        <div key={s} className={`h-1.5 flex-1 rounded-full ${i <= step ? 'bg-brand-600' : 'bg-slate-200'}`} />
                    ))}
                </div>

                <div className="scroll-thin min-h-0 flex-1 overflow-y-auto px-6 pb-2">
                    {current === 'modo' && <StepModo modo={modo} setModo={setModo} />}
                    {current === 'especialidades' && <StepEspecialidades selected={especialidades} setSelected={setEspecialidades} />}
                    {current === 'ccaa' && <StepCcaa />}
                    {current === 'direccion' && <StepDireccion direccion={direccion} setDireccion={setDireccion} coords={coords} setCoords={setCoords} />}
                    {current === 'nombre_gva' && <StepNombreGva value={nombreGva} setValue={setNombreGva} />}
                    {current === 'resumen' && (
                        <StepResumen modo={modo} especialidades={especialidades} direccion={direccion} nombreGva={nombreGva} />
                    )}
                </div>

                {error && <p className="px-6 text-sm text-rose-600">{error}</p>}

                <div className="flex items-center justify-between border-t border-slate-100 p-4">
                    <button onClick={back} disabled={step === 0 || saving} className="rounded-lg px-3 py-2 text-sm font-medium text-slate-500 hover:bg-slate-100 disabled:opacity-0">
                        ← Atrás
                    </button>
                    {isLast ? (
                        <button onClick={finish} disabled={saving} className="rounded-lg bg-brand-600 px-5 py-2 text-sm font-semibold text-white hover:bg-brand-700 disabled:opacity-50">
                            {saving ? 'Guardando…' : 'Empezar'}
                        </button>
                    ) : (
                        <button onClick={next} disabled={!canAdvance()} className="rounded-lg bg-brand-600 px-5 py-2 text-sm font-semibold text-white hover:bg-brand-700 disabled:opacity-40">
                            Continuar →
                        </button>
                    )}
                </div>
            </div>
        </div>
    );
}

function StepModo({ modo, setModo }) {
    return (
        <div>
            <h2 className="text-lg font-bold text-slate-800">¿Cómo usarás VacanteDocente?</h2>
            <p className="mt-1 text-sm text-slate-500">Puedes cambiarlo en cualquier momento.</p>
            <div className="mt-4 space-y-2">
                {MODOS.map((m) => (
                    <button
                        key={m.value}
                        onClick={() => setModo(m.value)}
                        className={`flex w-full items-start gap-3 rounded-xl border p-3 text-left transition ${modo === m.value ? 'border-brand-500 bg-brand-50' : 'border-slate-200 hover:border-slate-300'}`}
                    >
                        <span className="text-2xl" aria-hidden="true">{m.icon}</span>
                        <span>
                            <span className="block font-semibold text-slate-800">{m.label}</span>
                            <span className="block text-xs text-slate-500">{m.desc}</span>
                        </span>
                    </button>
                ))}
            </div>
        </div>
    );
}

function StepEspecialidades({ selected, setSelected }) {
    const { data, isLoading } = useQuery({
        queryKey: ['specialties'],
        queryFn: async () => (await api.get('/specialties')).data,
    });

    const groups = data ? [
        ['Maestros', data.maestros], ['Secundaria', data.secundaria], ['FP', data.fp],
    ] : [];

    const toggle = (id) => setSelected((prev) => (prev.includes(id) ? prev.filter((x) => x !== id) : [...prev, id]));

    return (
        <div>
            <h2 className="text-lg font-bold text-slate-800">Tus especialidades</h2>
            <p className="mt-1 text-sm text-slate-500">Selecciona al menos una. ({selected.length} seleccionada{selected.length === 1 ? '' : 's'})</p>
            {isLoading ? (
                <p className="mt-4 text-sm text-slate-400">Cargando especialidades…</p>
            ) : (
                <div className="mt-3 space-y-4">
                    {groups.map(([label, items]) => (items?.length ? (
                        <div key={label}>
                            <h3 className="text-xs font-semibold uppercase tracking-wide text-slate-400">{label}</h3>
                            <div className="mt-1 flex flex-wrap gap-1.5">
                                {items.map((s) => (
                                    <button
                                        key={s.id}
                                        onClick={() => toggle(s.id)}
                                        className={`rounded-full px-3 py-1 text-xs font-medium transition ${selected.includes(s.id) ? 'bg-brand-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'}`}
                                    >
                                        {s.name}
                                    </button>
                                ))}
                            </div>
                        </div>
                    ) : null))}
                </div>
            )}
        </div>
    );
}

function StepCcaa() {
    return (
        <div>
            <h2 className="text-lg font-bold text-slate-800">Comunidad autónoma</h2>
            <p className="mt-1 text-sm text-slate-500">VacanteDocente está disponible para la Comunitat Valenciana.</p>
            <div className="mt-4 flex items-center gap-3 rounded-xl border border-brand-500 bg-brand-50 p-3">
                <span className="text-2xl" aria-hidden="true">🟠</span>
                <span className="font-semibold text-slate-800">Comunitat Valenciana</span>
            </div>
            <p className="mt-2 text-xs text-slate-400">Pronto añadiremos más comunidades.</p>
        </div>
    );
}

function StepDireccion({ direccion, setDireccion, coords, setCoords }) {
    const debounced = useDebounce(direccion, 350);
    const { data } = useQuery({
        queryKey: ['geocode-suggest', debounced],
        queryFn: async () => (await api.get('/geocode', { params: { address: debounced } })).data,
        enabled: debounced.length >= 3 && !coords,
    });

    return (
        <div>
            <h2 className="text-lg font-bold text-slate-800">Tu dirección</h2>
            <p className="mt-1 text-sm text-slate-500">Para calcular distancias a los centros. Puedes omitirlo.</p>
            <input
                value={direccion}
                onChange={(e) => { setDireccion(e.target.value); setCoords(null); }}
                placeholder="Calle, número, localidad…"
                className="mt-3 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
            />
            {!coords && data?.data?.length > 0 && (
                <ul className="mt-1 divide-y divide-slate-100 rounded-lg border border-slate-200">
                    {data.data.slice(0, 5).map((s, i) => (
                        <li key={i}>
                            <button
                                onClick={() => { setDireccion(s.description ?? s.formatted_address ?? direccion); setCoords({ lat: s.lat, lng: s.lng }); }}
                                className="block w-full px-3 py-2 text-left text-sm text-slate-600 hover:bg-slate-50"
                            >
                                {s.description ?? s.formatted_address}
                            </button>
                        </li>
                    ))}
                </ul>
            )}
            {coords && <p className="mt-2 text-xs text-green-600">Dirección verificada ✓</p>}
        </div>
    );
}

function StepNombreGva({ value, setValue }) {
    return (
        <div>
            <h2 className="text-lg font-bold text-slate-800">Tu nombre en la GVA</h2>
            <p className="mt-1 text-sm text-slate-500">
                Tal y como aparece en los listados oficiales (p. ej. «GARCÍA LÓPEZ, ANA»). Sirve para localizar tu posición y adjudicaciones.
            </p>
            <input
                value={value}
                onChange={(e) => setValue(e.target.value)}
                placeholder="APELLIDOS, NOMBRE"
                className="mt-3 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm uppercase"
            />
            <p className="mt-2 text-xs text-slate-400">Puedes dejarlo en blanco y configurarlo más tarde.</p>
        </div>
    );
}

function StepResumen({ modo, especialidades, direccion, nombreGva }) {
    const modoLabel = MODOS.find((m) => m.value === modo)?.label ?? modo;
    return (
        <div>
            <h2 className="text-lg font-bold text-slate-800">¡Todo listo!</h2>
            <p className="mt-1 text-sm text-slate-500">Revisa tus datos y empieza a usar VacanteDocente.</p>
            <dl className="mt-4 space-y-2 text-sm">
                <Row label="Modo" value={modoLabel} />
                <Row label="Especialidades" value={`${especialidades.length} seleccionada${especialidades.length === 1 ? '' : 's'}`} />
                <Row label="Comunidad" value="Comunitat Valenciana" />
                <Row label="Dirección" value={direccion || 'Sin especificar'} />
                {modo === 'bolsa' && <Row label="Nombre GVA" value={nombreGva || 'Sin especificar'} />}
            </dl>
        </div>
    );
}

function Row({ label, value }) {
    return (
        <div className="flex items-center justify-between rounded-lg bg-slate-50 px-3 py-2">
            <dt className="text-slate-500">{label}</dt>
            <dd className="font-medium text-slate-800">{value}</dd>
        </div>
    );
}
