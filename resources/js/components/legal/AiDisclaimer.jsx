// Transparency notice for AI features (Reglamento UE 2024/1689 — AI Act,
// art. 50: el usuario debe saber que interactúa con una IA y que el contenido
// puede contener errores).
export default function AiDisclaimer({ className = '' }) {
    return (
        <p className={`rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-800 ${className}`}>
            ✨ Estás interactuando con un <strong>asistente de inteligencia artificial</strong>. Sus respuestas se generan
            automáticamente y pueden contener errores: verifica siempre la información con las fuentes oficiales.
        </p>
    );
}
