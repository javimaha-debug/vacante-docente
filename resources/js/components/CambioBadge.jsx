// Highlights a vacancy that changed since the previous listing import.
export default function CambioBadge({ cambio }) {
    if (cambio !== 'nueva' && cambio !== 'modificada') return null;

    const style =
        cambio === 'nueva'
            ? 'bg-emerald-100 text-emerald-700 ring-emerald-200'
            : 'bg-amber-100 text-amber-700 ring-amber-200';

    return (
        <span className={`shrink-0 rounded-full px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide ring-1 ${style}`}>
            {cambio === 'nueva' ? 'Nueva' : 'Modificada'}
        </span>
    );
}
