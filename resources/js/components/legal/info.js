// Centralised legal/identity data referenced by every legal page.
//
// ⚠️  Los textos legales son una plantilla de partida conforme a RGPD, LOPDGDD
// y LSSI-CE. Conviene que un asesor legal / DPO revise la redacción vinculante
// (base jurídica, contratos de encargado del tratamiento, registro de
// actividades) antes de su publicación definitiva.

export const TITULAR = {
    marca: 'Doccentia',
    razonSocial: 'Haba Leasing Services S.L.',
    nif: 'B75939678',
    domicilio: 'Calle Trafalgar 19, 5º, 46930 Quart de Poblet, Valencia, España',
    email: 'javimaha@gmail.com',
    emailSoporte: 'javimaha@gmail.com',
    web: 'https://doccentia.com',
};

export const ACTUALIZADO = '29 de junio de 2026';

// Encargados y subencargados del tratamiento (RGPD art. 28). Varios prestan el
// servicio desde fuera del EEE; la transferencia se ampara en las cláusulas
// contractuales tipo (SCC) y/o el Data Privacy Framework (DPF) UE-EE. UU.
export const ENCARGADOS = [
    { nombre: 'Stripe Payments Europe, Ltd.', finalidad: 'Procesamiento de pagos y suscripciones', ubicacion: 'UE / EE. UU. (DPF + SCC)' },
    { nombre: 'Anthropic PBC', finalidad: 'Asistente de IA (chat, generación de contenido)', ubicacion: 'EE. UU. (SCC)' },
    { nombre: 'Voyage AI', finalidad: 'Generación de embeddings para la búsqueda en tus documentos', ubicacion: 'EE. UU. (SCC)' },
    { nombre: 'Google LLC', finalidad: 'Inicio de sesión, Google Maps y Google Drive (opcional)', ubicacion: 'EE. UU. (DPF + SCC)' },
    { nombre: 'Microsoft Corporation', finalidad: 'Inicio de sesión y Microsoft 365 (opcional)', ubicacion: 'EE. UU. (DPF + SCC)' },
    { nombre: 'Functional Software, Inc. (Sentry)', finalidad: 'Monitorización técnica de errores', ubicacion: 'EE. UU. (DPF + SCC)' },
    { nombre: 'DigitalOcean, LLC (101 Avenue of the Americas, Nueva York, NY 10013, EE. UU.)', finalidad: 'Alojamiento de la aplicación y la base de datos', ubicacion: 'EE. UU. (SCC)' },
];
