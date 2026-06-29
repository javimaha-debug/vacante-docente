import { Component } from 'react';

/** Animated skeleton block for loading states. */
export function Skeleton({ className = '' }) {
    return <div className={`animate-pulse rounded bg-slate-700/40 ${className}`} />;
}

/** A grid of skeleton rows for tables/cards. */
export function SkeletonRows({ rows = 5, className = 'h-10' }) {
    return (
        <div className="space-y-2">
            {Array.from({ length: rows }).map((_, i) => (
                <Skeleton key={i} className={className} />
            ))}
        </div>
    );
}

/** Inline error panel for failed queries. */
export function ErrorState({ error, onRetry }) {
    const message = error?.friendlyMessage || error?.message || 'Ha ocurrido un error.';
    return (
        <div className="rounded-xl border border-rose-500/30 bg-rose-500/10 p-4 text-sm text-rose-200">
            <p className="font-semibold">No se pudieron cargar los datos</p>
            <p className="mt-1 text-rose-300/80">{message}</p>
            {onRetry && (
                <button
                    onClick={onRetry}
                    className="mt-3 rounded-lg bg-rose-500/20 px-3 py-1.5 text-xs font-semibold text-rose-100 hover:bg-rose-500/30"
                >
                    Reintentar
                </button>
            )}
        </div>
    );
}

/** KPI card for the dashboard. */
export function KpiCard({ label, value, sub, accent = 'text-white' }) {
    return (
        <div className="rounded-xl border border-slate-700/60 bg-slate-800/50 p-4">
            <p className="text-xs font-medium uppercase tracking-wide text-slate-400">{label}</p>
            <p className={`mt-1 text-2xl font-bold ${accent}`}>{value}</p>
            {sub && <p className="mt-0.5 text-xs text-slate-500">{sub}</p>}
        </div>
    );
}

/** Error boundary that keeps the rest of the admin shell alive. */
export class AdminErrorBoundary extends Component {
    constructor(props) {
        super(props);
        this.state = { hasError: false, error: null };
    }

    static getDerivedStateFromError(error) {
        return { hasError: true, error };
    }

    render() {
        if (this.state.hasError) {
            return (
                <div className="rounded-xl border border-rose-500/30 bg-rose-500/10 p-6 text-sm text-rose-200">
                    <p className="font-semibold">Algo ha fallado en esta sección.</p>
                    <p className="mt-1 text-rose-300/80">{String(this.state.error?.message ?? this.state.error)}</p>
                    <button
                        onClick={() => this.setState({ hasError: false, error: null })}
                        className="mt-3 rounded-lg bg-rose-500/20 px-3 py-1.5 text-xs font-semibold text-rose-100 hover:bg-rose-500/30"
                    >
                        Reintentar
                    </button>
                </div>
            );
        }
        return this.props.children;
    }
}

/** Plan / status badge with a consistent colour scheme. */
export function Badge({ children, tone = 'slate' }) {
    const tones = {
        slate: 'bg-slate-700/60 text-slate-200',
        green: 'bg-emerald-500/20 text-emerald-300',
        amber: 'bg-amber-500/20 text-amber-300',
        red: 'bg-rose-500/20 text-rose-300',
        blue: 'bg-sky-500/20 text-sky-300',
    };
    return (
        <span className={`inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ${tones[tone] ?? tones.slate}`}>
            {children}
        </span>
    );
}

/** Map a plan/status string to a badge tone. */
export function statusTone(status) {
    return { active: 'green', trialing: 'blue', past_due: 'amber', canceled: 'red', none: 'slate' }[status] ?? 'slate';
}

/** Spanish labels for the raw backend enum values shown in the admin panel. */
export const ENUM_LABELS = {
    status: { active: 'Activa', trialing: 'En prueba', past_due: 'Pago pendiente', canceled: 'Cancelada', none: 'Sin suscripción' },
    plan_status: { active: 'Activo', trialing: 'En prueba', past_due: 'Pago pendiente', canceled: 'Cancelado', none: 'Sin suscripción' },
    role: { user: 'Usuario', admin: 'Administrador', superadmin: 'Superadmin' },
    estado: { rumor: 'Rumor', anunciada: 'Anunciada', convocada: 'Convocada', en_proceso: 'En proceso', resuelta: 'Resuelta' },
    document_status: { pending: 'Pendiente', validated: 'Validado', rejected: 'Rechazado', published: 'Publicado' },
    document_type: { listado_provisional: 'Lista provisional', listado_definitivo: 'Lista definitiva', vacantes: 'Vacantes', resolucion: 'Resolución', convocatoria: 'Convocatoria', otro: 'Otro' },
};

/** Translate a raw enum value to its Spanish label (falls back to the value). */
export function enumLabel(group, value) {
    if (value == null || value === '') return '—';

    return ENUM_LABELS[group]?.[value] ?? value;
}
