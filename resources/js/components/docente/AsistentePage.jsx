import Asistente from '../oposicion/Asistente';

// The docente AI assistant reuses the oposicion Asistente component.
// The context_type is set per-conversation from the chat UI.
export default function AsistentePage() {
    return <Asistente />;
}
