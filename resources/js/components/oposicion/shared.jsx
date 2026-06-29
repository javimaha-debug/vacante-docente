import { useQuery } from '@tanstack/react-query';
import clsx from 'clsx';
import api from '../../lib/api';

// Human labels for the cuerpo enum.
export const CUERPO_LABEL = {
    maestros: 'Maestros',
    secundaria: 'Secundaria',
    fp: 'FP',
    otros: 'Otros',
};

// Maps the /specialties education_level groups to the cuerpo enum used here.
export const CUERPO_TO_GROUP = {
    maestros: 'maestros',
    secundaria: 'secundaria',
    fp: 'fp',
};

export const TEMA_STATUS = {
    pendiente: { label: 'Pendiente', tone: 'bg-slate-100 text-slate-600', dot: 'bg-slate-400' },
    en_progreso: { label: 'En progreso', tone: 'bg-amber-50 text-amber-700', dot: 'bg-amber-500' },
    dominado: { label: 'Dominado', tone: 'bg-brand-50 text-brand-700', dot: 'bg-brand-600' },
};

/**
 * Loads the public specialty catalogue and exposes a code → {name, cuerpo}
 * lookup plus the grouped lists for the selectors.
 */
export function useSpecialtyCatalogue() {
    const { data } = useQuery({
        queryKey: ['specialties', 'catalogue'],
        queryFn: async () => (await api.get('/specialties')).data,
        staleTime: 5 * 60 * 1000,
    });

    const groups = {
        maestros: data?.maestros ?? [],
        secundaria: data?.secundaria ?? [],
        fp: data?.fp ?? [],
    };

    const byCode = {};
    for (const [cuerpo, list] of Object.entries(groups)) {
        for (const s of list) byCode[s.code] = { name: s.name, cuerpo };
    }

    return { groups, byCode };
}

/** Resolve a friendly label for an especialidad code. */
export function especialidadLabel(code, byCode) {
    const hit = byCode[code];
    return hit ? `${code} · ${hit.name}` : code;
}

export function StatusBadge({ status }) {
    const meta = TEMA_STATUS[status] ?? TEMA_STATUS.pendiente;
    return (
        <span className={clsx('inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-semibold', meta.tone)}>
            <span className={clsx('h-1.5 w-1.5 rounded-full', meta.dot)} />
            {meta.label}
        </span>
    );
}

/** Section heading used across the oposición pages (Bricolage via font-heading). */
export function SectionTitle({ children, sub }) {
    return (
        <div>
            <h2 className="font-heading text-base font-bold text-slate-800">{children}</h2>
            {sub && <p className="text-xs text-slate-500">{sub}</p>}
        </div>
    );
}
