// Banner shown at the top of the explorer when the latest listing import for
// the active proceso introduced changes (new / modified / removed vacancies).
// Individual changed vacancies are flagged inline with <CambioBadge>.
function formatDate(iso) {
    if (!iso) return null;
    try {
        return new Date(iso).toLocaleDateString('es-ES', { day: 'numeric', month: 'long', year: 'numeric' });
    } catch {
        return null;
    }
}

export default function CambiosBanner({ cambios }) {
    if (!cambios?.has_changes) return null;

    const { nuevas = 0, modificadas = 0, eliminadas = 0, importado_en } = cambios;
    const fecha = formatDate(importado_en);

    const parts = [];
    if (nuevas > 0) parts.push(`${nuevas} ${nuevas === 1 ? 'nueva' : 'nuevas'}`);
    if (modificadas > 0) parts.push(`${modificadas} ${modificadas === 1 ? 'modificada' : 'modificadas'}`);
    if (eliminadas > 0) parts.push(`${eliminadas} ${eliminadas === 1 ? 'eliminada' : 'eliminadas'}`);

    return (
        <div className="mb-3 flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 px-3.5 py-3 text-sm shadow-sm">
            <span aria-hidden className="mt-0.5 text-lg">📣</span>
            <div className="min-w-0">
                <p className="font-semibold text-amber-800">El listado se ha actualizado</p>
                <p className="mt-0.5 text-amber-700">
                    {parts.join(' · ')} respecto al listado anterior
                    {fecha && <span className="text-amber-600"> · {fecha}</span>}.
                    {(nuevas > 0 || modificadas > 0) && (
                        <span className="text-amber-600"> Las plazas resaltadas con «Nueva» o «Modificada» son las que han cambiado.</span>
                    )}
                </p>
            </div>
        </div>
    );
}
