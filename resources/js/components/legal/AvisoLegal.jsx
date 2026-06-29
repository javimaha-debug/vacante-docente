import LegalLayout, { Section } from './LegalLayout';
import { TITULAR } from './info';

export default function AvisoLegal() {
    return (
        <LegalLayout title="Aviso legal" subtitle="Información general exigida por la Ley 34/2002 (LSSI-CE).">
            <Section title="1. Datos identificativos del titular">
                <p>
                    En cumplimiento del artículo 10 de la Ley 34/2002, de Servicios de la Sociedad de la Información y de
                    Comercio Electrónico (LSSI-CE), se informa de que el titular de este sitio web y de la aplicación{' '}
                    <strong>{TITULAR.marca}</strong> es:
                </p>
                <ul className="ml-5 list-disc space-y-1">
                    <li><strong>Titular:</strong> {TITULAR.razonSocial}</li>
                    <li><strong>NIF:</strong> {TITULAR.nif}</li>
                    <li><strong>Domicilio:</strong> {TITULAR.domicilio}</li>
                    <li><strong>Correo electrónico:</strong> {TITULAR.email}</li>
                    <li><strong>Sitio web:</strong> {TITULAR.web}</li>
                </ul>
            </Section>

            <Section title="2. Objeto">
                <p>
                    {TITULAR.marca} es una plataforma de software como servicio (SaaS) dirigida a docentes para organizar la
                    petición de vacantes, el seguimiento de bolsas y oposiciones, y la preparación con apoyo de
                    herramientas de inteligencia artificial. El acceso y uso del servicio atribuye la condición de usuario e
                    implica la aceptación del presente Aviso legal, de la Política de privacidad y de los Términos y
                    condiciones.
                </p>
            </Section>

            <Section title="3. Condiciones de uso">
                <p>
                    El usuario se compromete a hacer un uso lícito, diligente y conforme a la ley del servicio, absteniéndose
                    de utilizarlo con fines ilícitos o lesivos para terceros, o que de cualquier forma puedan dañar,
                    inutilizar o sobrecargar la plataforma o impedir su normal utilización.
                </p>
            </Section>

            <Section title="4. Propiedad intelectual e industrial">
                <p>
                    Los contenidos, marcas, logotipos, diseño y código del servicio son titularidad del titular o de sus
                    licenciantes y están protegidos por la normativa de propiedad intelectual e industrial. Queda prohibida
                    su reproducción, distribución o comunicación pública sin autorización expresa. Los datos oficiales (BOE,
                    DOGV, listados de la Administración) pertenecen a sus respectivas fuentes y se ofrecen con fines
                    informativos.
                </p>
            </Section>

            <Section title="5. Exclusión de responsabilidad">
                <p>
                    La información oficial (vacantes, listados, normativa, convocatorias) se recopila de fuentes públicas y
                    se ofrece a título informativo. El titular no garantiza la ausencia de errores ni la disponibilidad
                    permanente del servicio, y recomienda contrastar siempre los datos con las fuentes oficiales antes de
                    tomar decisiones. El contenido generado por inteligencia artificial puede contener imprecisiones y debe
                    verificarse.
                </p>
            </Section>

            <Section title="6. Legislación aplicable">
                <p>
                    Este Aviso legal se rige por la legislación española. Para cualquier controversia, las partes se someten
                    a los juzgados y tribunales que correspondan conforme a la normativa aplicable.
                </p>
            </Section>
        </LegalLayout>
    );
}
