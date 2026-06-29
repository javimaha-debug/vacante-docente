// Centralised legal/identity data referenced by every legal page.
//
// ⚠️  COMPLETAR ANTES DE PRODUCCIÓN: los valores entre [corchetes] deben ser
// rellenados por el titular con la información real (razón social, NIF,
// domicilio) y revisados por un asesor legal / DPO. El resto del texto es una
// plantilla de partida conforme a RGPD, LOPDGDD y LSSI-CE.

export const TITULAR = {
    marca: 'Doccentia',
    razonSocial: '[RAZÓN SOCIAL O NOMBRE DEL TITULAR]',
    nif: '[NIF / DNI]',
    domicilio: '[DOMICILIO FISCAL COMPLETO]',
    email: 'privacidad@doccentia.com',
    emailSoporte: 'soporte@doccentia.com',
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
    { nombre: '[PROVEEDOR DE HOSTING]', finalidad: 'Alojamiento de la aplicación y la base de datos', ubicacion: '[UBICACIÓN DEL CENTRO DE DATOS]' },
];
