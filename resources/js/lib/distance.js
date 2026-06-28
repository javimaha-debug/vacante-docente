// Helpers for the (composite-keyed) travel-time payloads:
//   { driving_ida, driving_tornada, transit_ida, transit_tornada, walking_ida }

export const TRAVEL_MODES = [
    { key: 'driving', label: 'Coche', icon: '🚗' },
    { key: 'transit', label: 'Público', icon: '🚆' },
    { key: 'walking', label: 'A pie', icon: '🚶' },
];

export function formatDuration(minutes) {
    if (minutes == null) return null;
    if (minutes < 60) return `${minutes} min`;
    const h = Math.floor(minutes / 60);
    const m = minutes % 60;
    return m ? `${h}h ${m}m` : `${h}h`;
}

// Outbound + return summary for a mode, e.g. { ida, tornada, km }.
export function modeSummary(distances, mode) {
    if (!distances) return null;
    const ida = distances[`${mode}_ida`];
    const tornada = distances[`${mode}_tornada`];
    if (!ida && !tornada) return null;
    return {
        ida: ida?.duration_minutes ?? null,
        tornada: tornada?.duration_minutes ?? null,
        km: ida?.distance_km ?? tornada?.distance_km ?? null,
        trafficNote: ida?.traffic_note ?? null,
    };
}

export function hasAnyDistance(distances) {
    if (!distances) return false;
    return TRAVEL_MODES.some((m) => distances[`${m.key}_ida`] || distances[`${m.key}_tornada`]);
}

// Google Maps directions deep link (opens the live route in Maps).
export function mapsRouteUrl(home, vacancy, travelmode = 'driving') {
    const dest = encodeURIComponent(
        [vacancy.centro_nombre, vacancy.localidad, vacancy.provincia, 'España'].filter(Boolean).join(', ')
    );
    const origin = home?.lat != null && home?.lng != null ? `&origin=${home.lat},${home.lng}` : '';
    return `https://www.google.com/maps/dir/?api=1${origin}&destination=${dest}&travelmode=${travelmode}`;
}
