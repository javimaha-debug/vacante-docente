import { modeSummary } from './distance';

// Single source of truth for the explorer's combinable client-side filters.
// Every field-based filter is applied with AND logic; the result feeds both the
// rendered lists and the "X coinciden" counter so they never drift apart.

export const DEFAULT_FILTERS = {
    search: '',
    provincias: [],          // [] = todas
    tiposCentro: [],         // Secundaria | Primaria/Infantil | Otro
    caracteristicas: [],     // CRA | Centre singular | CEE | FPA | CIPFP | UECO | Penitenciari | Jornada contínua
    reqLing: '',             // '' indiferente | 'si' | 'no'
    itinerante: '',          // '' indiferente | 'si' | 'no'
    observaciones: '',
    distMin: '',             // km
    distMax: '',             // km
    timeMax: '',             // minutos (ida) según modo
    timeMode: 'driving',     // driving | transit | walking
    estados: [],             // [] = todos | neutral | selected | revisar | discarded
    sort: 'priority',
};

export const PROVINCIAS = ['Alacant', 'Castelló', 'València'];
export const TIPOS_CENTRO = ['Secundaria', 'Primaria/Infantil', 'Otro'];
export const CARACTERISTICAS = ['CRA', 'Centre singular', 'CEE', 'FPA', 'CIPFP', 'UECO', 'Penitenciari', 'Jornada contínua'];
export const ESTADOS = [
    { key: 'neutral', label: 'Sin revisar' },
    { key: 'selected', label: 'Mi lista' },
    { key: 'revisar', label: 'A revisar' },
    { key: 'discarded', label: 'Descartada' },
];

const normalize = (s) => (s ?? '').toString().normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();

// Driving distance in km (closest of ida/tornada), or null when not computed.
export function vacancyKm(v) {
    return v.distances?.driving_ida?.distance_km ?? v.distances?.driving_tornada?.distance_km ?? null;
}

function vacancyMinutes(v, mode) {
    return modeSummary(v.distances, mode)?.ida ?? null;
}

function matchesText(v, q) {
    const hay = normalize(
        [
            v.centro_nombre,
            v.localidad,
            v.centro_codigo,
            v.provincia,
            v.tipo_centro,
            v.observ,
            `#${v.num}`,
            v.num,
            (v.observ_tags ?? []).join(' '),
        ].join(' ')
    );
    return hay.includes(q);
}

// True when a vacancy passes every active FIELD filter (status handled apart).
export function matchesFilters(v, filters) {
    const f = filters ?? DEFAULT_FILTERS;

    const q = normalize(f.search);
    if (q && !matchesText(v, q)) return false;

    if (f.provincias?.length && !f.provincias.includes(v.provincia)) return false;

    if (f.tiposCentro?.length && !f.tiposCentro.includes(v.tipo_centro)) return false;

    if (f.caracteristicas?.length) {
        const tags = v.observ_tags ?? [];
        // OR within the characteristics group (like provincia/tipo): the vacancy
        // must carry at least one of the selected characteristics.
        if (!f.caracteristicas.some((c) => tags.includes(c))) return false;
    }

    if (f.reqLing === 'si' && !v.req_ling) return false;
    if (f.reqLing === 'no' && v.req_ling) return false;

    if (f.itinerante === 'si' && !v.itinerante) return false;
    if (f.itinerante === 'no' && v.itinerante) return false;

    if (f.observaciones) {
        const needle = normalize(f.observaciones);
        if (!normalize(v.observ).includes(needle)) return false;
    }

    // Distance range (km). Vacancies without a computed distance stay visible
    // so the list isn't emptied before distances are calculated.
    const km = vacancyKm(v);
    const min = parseFloat(f.distMin);
    const max = parseFloat(f.distMax);
    if (km != null) {
        if (!Number.isNaN(min) && km < min) return false;
        if (!Number.isNaN(max) && km > max) return false;
    }

    // Travel-time ceiling (minutes, outbound) for the chosen mode.
    const tMax = parseFloat(f.timeMax);
    if (!Number.isNaN(tMax)) {
        const mins = vacancyMinutes(v, f.timeMode || 'driving');
        if (mins != null && mins > tMax) return false;
    }

    return true;
}

export function statusEnabled(filters, status) {
    const estados = filters?.estados ?? [];
    return estados.length === 0 || estados.includes(status);
}

// Comparators for the "Ordenar por" control.
export function sortVacancies(list, sort) {
    if (!sort || sort === 'priority') return list;
    const cmp = {
        distance: (a, b) => (vacancyKm(a) ?? Infinity) - (vacancyKm(b) ?? Infinity),
        num: (a, b) => (a.num ?? 0) - (b.num ?? 0),
        localidad: (a, b) => (a.localidad ?? '').localeCompare(b.localidad ?? ''),
        centro: (a, b) => (a.centro_nombre ?? '').localeCompare(b.centro_nombre ?? ''),
    }[sort];
    return cmp ? [...list].sort(cmp) : list;
}

// How many filter groups are active — drives the mobile "Filtrar (N)" badge.
export function countActiveFilters(filters) {
    const f = filters ?? DEFAULT_FILTERS;
    let n = 0;
    if (f.search?.trim()) n += 1;
    if (f.provincias?.length) n += 1;
    if (f.tiposCentro?.length) n += 1;
    if (f.caracteristicas?.length) n += 1;
    if (f.reqLing) n += 1;
    if (f.itinerante) n += 1;
    if (f.observaciones?.trim()) n += 1;
    if (f.distMin !== '' || f.distMax !== '') n += 1;
    if (f.timeMax !== '') n += 1;
    if (f.estados?.length) n += 1;
    return n;
}
