import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import api from '../../lib/api';
import { useAuth } from '../../hooks/useAuth';
import { LogoHorizontalTeal } from '../brand/DoccentiaLogo';
import Footer from '../legal/Footer';

const PROVIDER_META = {
    google: { label: 'Google', icon: GoogleIcon },
    microsoft: { label: 'Microsoft', icon: MicrosoftIcon },
};

export default function LoginPage() {
    const { login } = useAuth();
    const [mode, setMode] = useState('login'); // 'login' | 'register'
    const [form, setForm] = useState({ name: '', email: '', password: '', password_confirmation: '' });
    const [acepto, setAcepto] = useState(false);
    const [providers, setProviders] = useState([]);
    const [error, setError] = useState(null);
    const [busy, setBusy] = useState(false);

    useEffect(() => {
        api.get('/auth/providers')
            .then(({ data }) => setProviders(data.providers ?? []))
            .catch(() => setProviders(['google']));
    }, []);

    // Surface OAuth redirect errors (?error=oauth...).
    useEffect(() => {
        const params = new URLSearchParams(window.location.search);
        if (params.get('error')?.startsWith('oauth')) {
            setError('No se pudo iniciar sesión con ese proveedor. Inténtalo de nuevo.');
        }
    }, []);

    const set = (k, v) => setForm((f) => ({ ...f, [k]: v }));

    const submit = async (e) => {
        e.preventDefault();
        setError(null);
        setBusy(true);
        try {
            const path = mode === 'register' ? '/auth/register' : '/auth/login';
            const payload = mode === 'register'
                ? { ...form, acepto_condiciones: acepto }
                : { email: form.email, password: form.password };
            const { data } = await api.post(path, payload);
            await login(data.token);
            window.location.assign('/dashboard');
        } catch (err) {
            const resp = err?.response?.data;
            setError(
                resp?.errors ? Object.values(resp.errors).flat()[0]
                : resp?.message ?? 'No se pudo completar la operación.'
            );
        } finally {
            setBusy(false);
        }
    };

    return (
        <div className="flex min-h-full flex-col bg-gradient-to-b from-brand-50 to-slate-100">
            <div className="flex flex-1 items-center justify-center px-4 py-12">
            <div className="w-full max-w-md rounded-2xl bg-white p-8 shadow-sm ring-1 ring-slate-200 sm:p-10">
                <div className="text-center">
                    <LogoHorizontalTeal className="mx-auto h-10 w-auto" />
                    <h1 className="sr-only">Doccentia</h1>
                    <p className="mx-auto mt-3 max-w-xs text-sm text-slate-500">
                        Organiza tu petición de vacantes docentes
                    </p>
                </div>

                {/* Tabs */}
                <div className="mt-6 inline-flex w-full rounded-xl bg-slate-100 p-1">
                    {[{ k: 'login', t: 'Iniciar sesión' }, { k: 'register', t: 'Crear cuenta' }].map((o) => (
                        <button
                            key={o.k}
                            type="button"
                            onClick={() => { setMode(o.k); setError(null); }}
                            className={`flex-1 rounded-lg px-3 py-2 text-sm font-semibold transition ${mode === o.k ? 'bg-white text-brand-700 shadow-sm' : 'text-slate-500 hover:text-slate-700'}`}
                        >
                            {o.t}
                        </button>
                    ))}
                </div>

                <form onSubmit={submit} className="mt-5 space-y-3">
                    {mode === 'register' && (
                        <Field label="Nombre" type="text" autoComplete="name" value={form.name} onChange={(v) => set('name', v)} required />
                    )}
                    <Field label="Correo electrónico" type="email" autoComplete="email" value={form.email} onChange={(v) => set('email', v)} required />
                    <Field
                        label="Contraseña"
                        type="password"
                        autoComplete={mode === 'register' ? 'new-password' : 'current-password'}
                        value={form.password}
                        onChange={(v) => set('password', v)}
                        required
                    />
                    {mode === 'register' && (
                        <Field label="Repite la contraseña" type="password" autoComplete="new-password" value={form.password_confirmation} onChange={(v) => set('password_confirmation', v)} required />
                    )}

                    {mode === 'register' && (
                        <label className="flex items-start gap-2 pt-1 text-xs text-slate-600">
                            <input
                                type="checkbox"
                                checked={acepto}
                                onChange={(e) => setAcepto(e.target.checked)}
                                required
                                className="mt-0.5 h-4 w-4 rounded text-brand-600 focus:ring-brand-500"
                            />
                            <span>
                                He leído y acepto los{' '}
                                <Link to="/legal/terminos" target="_blank" className="font-semibold text-brand-700 underline">Términos y condiciones</Link>{' '}
                                y la{' '}
                                <Link to="/legal/privacidad" target="_blank" className="font-semibold text-brand-700 underline">Política de privacidad</Link>.
                            </span>
                        </label>
                    )}

                    {error && <p className="rounded-lg bg-rose-50 px-3 py-2 text-sm text-rose-600">{error}</p>}

                    <button
                        type="submit"
                        disabled={busy || (mode === 'register' && !acepto)}
                        className="w-full rounded-xl bg-brand-600 px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700 disabled:opacity-60"
                    >
                        {busy ? 'Un momento…' : mode === 'register' ? 'Crear cuenta' : 'Entrar'}
                    </button>
                </form>

                {providers.length > 0 && (
                    <>
                        <div className="my-6 flex items-center gap-3">
                            <span className="h-px flex-1 bg-slate-200" />
                            <span className="text-xs font-medium text-slate-400">o continúa con</span>
                            <span className="h-px flex-1 bg-slate-200" />
                        </div>
                        <div className="space-y-2">
                            {providers.map((p) => {
                                const meta = PROVIDER_META[p];
                                if (!meta) return null;
                                const Icon = meta.icon;
                                return (
                                    <a
                                        key={p}
                                        href={`/auth/${p}`}
                                        className="flex w-full items-center justify-center gap-3 rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
                                    >
                                        <Icon />
                                        Continuar con {meta.label}
                                    </a>
                                );
                            })}
                        </div>
                    </>
                )}

                <p className="mt-6 text-center text-xs text-slate-400">
                    Tratamos tus datos conforme a nuestra{' '}
                    <Link to="/legal/privacidad" className="font-medium text-brand-600 underline">Política de privacidad</Link>.
                </p>
            </div>
            </div>
            <Footer />
        </div>
    );
}

function Field({ label, type, value, onChange, autoComplete, required }) {
    return (
        <label className="block">
            <span className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-400">{label}</span>
            <input
                type={type}
                value={value}
                autoComplete={autoComplete}
                required={required}
                onChange={(e) => onChange(e.target.value)}
                className="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm focus:border-brand-400 focus:outline-none focus:ring-1 focus:ring-brand-200"
            />
        </label>
    );
}

function GoogleIcon() {
    return (
        <svg className="h-5 w-5" viewBox="0 0 48 48" aria-hidden="true">
            <path fill="#FFC107" d="M43.611 20.083H42V20H24v8h11.303c-1.649 4.657-6.08 8-11.303 8-6.627 0-12-5.373-12-12s5.373-12 12-12c3.059 0 5.842 1.154 7.961 3.039l5.657-5.657C34.046 6.053 29.268 4 24 4 12.955 4 4 12.955 4 24s8.955 20 20 20 20-8.955 20-20c0-1.341-.138-2.65-.389-3.917z" />
            <path fill="#FF3D00" d="M6.306 14.691l6.571 4.819C14.655 15.108 18.961 12 24 12c3.059 0 5.842 1.154 7.961 3.039l5.657-5.657C34.046 6.053 29.268 4 24 4 16.318 4 9.656 8.337 6.306 14.691z" />
            <path fill="#4CAF50" d="M24 44c5.166 0 9.86-1.977 13.409-5.192l-6.19-5.238C29.211 35.091 26.715 36 24 36c-5.202 0-9.619-3.317-11.283-7.946l-6.522 5.025C9.505 39.556 16.227 44 24 44z" />
            <path fill="#1976D2" d="M43.611 20.083H42V20H24v8h11.303a12.04 12.04 0 0 1-4.087 5.571l.003-.002 6.19 5.238C36.971 39.205 44 34 44 24c0-1.341-.138-2.65-.389-3.917z" />
        </svg>
    );
}

function MicrosoftIcon() {
    return (
        <svg className="h-5 w-5" viewBox="0 0 23 23" aria-hidden="true">
            <path fill="#f25022" d="M1 1h10v10H1z" />
            <path fill="#7fba00" d="M12 1h10v10H12z" />
            <path fill="#00a4ef" d="M1 12h10v10H1z" />
            <path fill="#ffb900" d="M12 12h10v10H12z" />
        </svg>
    );
}

