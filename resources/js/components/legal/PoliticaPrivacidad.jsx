import { Link } from 'react-router-dom';
import LegalLayout, { Section } from './LegalLayout';
import { TITULAR, ENCARGADOS } from './info';

export default function PoliticaPrivacidad() {
    return (
        <LegalLayout
            title="Política de privacidad"
            subtitle="Cómo tratamos tus datos personales conforme al RGPD (UE 2016/679) y la LOPDGDD (LO 3/2018)."
        >
            <Section title="1. Responsable del tratamiento">
                <p>
                    El responsable del tratamiento de tus datos es <strong>{TITULAR.razonSocial}</strong> (marca{' '}
                    {TITULAR.marca}), NIF {TITULAR.nif}, con domicilio en {TITULAR.domicilio}. Puedes contactar en materia
                    de protección de datos en <strong>{TITULAR.email}</strong>.
                </p>
            </Section>

            <Section title="2. Datos que tratamos">
                <ul className="ml-5 list-disc space-y-1">
                    <li><strong>Identificativos y de cuenta:</strong> nombre, correo electrónico, contraseña (cifrada), avatar.</li>
                    <li><strong>De perfil docente:</strong> nombre GVA, cuerpo/colectivo, comunidad autónoma, especialidades, historial.</li>
                    <li><strong>De ubicación:</strong> dirección de origen y coordenadas, si decides usar el cálculo de distancias.</li>
                    <li><strong>Contenidos que subes:</strong> documentos, apuntes e imágenes para estudiar o consultar con la IA.</li>
                    <li><strong>De pago:</strong> gestionados por Stripe; no almacenamos los datos completos de tu tarjeta.</li>
                    <li><strong>Técnicos:</strong> datos de uso, identificadores de sesión y registros de errores.</li>
                </ul>
            </Section>

            <Section title="3. Finalidades y base jurídica">
                <ul className="ml-5 list-disc space-y-1">
                    <li><strong>Prestar el servicio</strong> (gestión de cuenta, listas, oposición): ejecución del contrato (art. 6.1.b RGPD).</li>
                    <li><strong>Procesar pagos y suscripciones:</strong> ejecución del contrato.</li>
                    <li><strong>Funciones de IA</strong> (asistente, OCR, búsqueda): ejecución del contrato y tu consentimiento al subir contenidos.</li>
                    <li><strong>Comunicaciones y notificaciones</strong> del servicio: interés legítimo y/o consentimiento.</li>
                    <li><strong>Seguridad y prevención de errores</strong> (monitorización técnica): interés legítimo (art. 6.1.f).</li>
                    <li><strong>Cookies analíticas:</strong> tu consentimiento (ver la <Link to="/legal/cookies" className="text-brand-700 underline">Política de cookies</Link>).</li>
                </ul>
            </Section>

            <Section title="4. Inteligencia artificial">
                <p>
                    Algunas funciones (asistente de chat, reconocimiento de texto de apuntes y búsqueda en tus documentos)
                    se apoyan en proveedores de IA. Para ello, los textos, consultas e imágenes que facilites pueden
                    transmitirse a dichos proveedores (Anthropic y Voyage AI) exclusivamente para generar la respuesta.
                    Según sus condiciones para empresas, <strong>estos datos no se utilizan para entrenar sus modelos</strong>.
                    El contenido generado por IA es orientativo y puede contener errores; verifica siempre la información
                    con las fuentes oficiales. Estas funciones no toman decisiones automatizadas con efectos jurídicos sobre ti.
                </p>
            </Section>

            <Section title="5. Destinatarios y encargados del tratamiento">
                <p>
                    No vendemos tus datos. Para prestar el servicio recurrimos a proveedores que actúan como encargados del
                    tratamiento, algunos ubicados fuera del Espacio Económico Europeo. En esos casos la transferencia se
                    ampara en las cláusulas contractuales tipo de la Comisión Europea (SCC) y/o en el Data Privacy Framework
                    (DPF) UE-EE. UU.:
                </p>
                <div className="overflow-x-auto">
                    <table className="mt-2 w-full border-collapse text-left text-xs">
                        <thead>
                            <tr className="border-b border-slate-200 text-slate-500">
                                <th className="py-2 pr-3 font-semibold">Proveedor</th>
                                <th className="py-2 pr-3 font-semibold">Finalidad</th>
                                <th className="py-2 font-semibold">Ubicación / garantía</th>
                            </tr>
                        </thead>
                        <tbody>
                            {ENCARGADOS.map((e) => (
                                <tr key={e.nombre} className="border-b border-slate-100 align-top">
                                    <td className="py-2 pr-3 font-medium text-slate-800">{e.nombre}</td>
                                    <td className="py-2 pr-3 text-slate-600">{e.finalidad}</td>
                                    <td className="py-2 text-slate-600">{e.ubicacion}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </Section>

            <Section title="6. Conservación">
                <p>
                    Conservamos tus datos mientras tu cuenta esté activa. Si eliminas tu cuenta, suprimimos tus datos
                    personales y los archivos asociados, salvo aquellos que debamos conservar por obligación legal (p. ej.,
                    facturación) durante los plazos legalmente exigidos.
                </p>
            </Section>

            <Section title="7. Tus derechos">
                <p>
                    Puedes ejercer tus derechos de acceso, rectificación, supresión, oposición, limitación y portabilidad,
                    así como retirar tu consentimiento en cualquier momento, escribiendo a <strong>{TITULAR.email}</strong>.
                    Desde <Link to="/dashboard/perfil" className="text-brand-700 underline">tu perfil</Link> puedes además
                    <strong> descargar una copia de tus datos</strong> y <strong>eliminar tu cuenta</strong> directamente.
                    Tienes derecho a reclamar ante la Agencia Española de Protección de Datos (
                    <a href="https://www.aepd.es" target="_blank" rel="noreferrer" className="text-brand-700 underline">www.aepd.es</a>).
                </p>
            </Section>

            <Section title="8. Seguridad">
                <p>
                    Aplicamos medidas técnicas y organizativas para proteger tus datos, incluyendo el cifrado de las
                    contraseñas y de los tokens de integraciones, el control de acceso por roles y el acceso a tus archivos
                    mediante enlaces firmados de corta duración.
                </p>
            </Section>
        </LegalLayout>
    );
}
