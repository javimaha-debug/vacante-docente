import { Link } from 'react-router-dom';
import LegalLayout, { Section } from './LegalLayout';
import { TITULAR } from './info';

export default function TerminosCondiciones() {
    return (
        <LegalLayout
            title="Términos y condiciones"
            subtitle="Condiciones de uso del servicio Doccentia."
        >
            <Section title="1. Aceptación">
                <p>
                    Estos Términos y condiciones regulan el acceso y uso de {TITULAR.marca}. Al registrarte o utilizar el
                    servicio, declaras haber leído y aceptado estos términos, así como el{' '}
                    <Link to="/legal/aviso-legal" className="text-brand-700 underline">Aviso legal</Link> y la{' '}
                    <Link to="/legal/privacidad" className="text-brand-700 underline">Política de privacidad</Link>.
                </p>
            </Section>

            <Section title="2. Cuenta de usuario">
                <p>
                    Para usar determinadas funciones debes crear una cuenta y eres responsable de la veracidad de tus datos
                    y de la custodia de tus credenciales. Debes ser mayor de edad o contar con la capacidad legal necesaria
                    para contratar.
                </p>
            </Section>

            <Section title="3. Planes y pagos">
                <p>
                    El servicio ofrece un plan gratuito y planes de pago por suscripción. Los pagos se procesan a través de
                    Stripe. Las suscripciones se renuevan según la modalidad contratada y puedes cancelarlas en cualquier
                    momento; salvo indicación en contrario, la cancelación surte efecto al final del periodo ya abonado.
                </p>
            </Section>

            <Section title="4. Uso aceptable">
                <p>
                    Te comprometes a no usar el servicio para fines ilícitos, a no vulnerar derechos de terceros y a no
                    intentar acceder, alterar o sobrecargar la infraestructura. Eres responsable de los contenidos que subas
                    y declaras disponer de los derechos necesarios sobre ellos.
                </p>
            </Section>

            <Section title="5. Asistente de inteligencia artificial">
                <p>
                    Las funciones de IA generan contenido de forma automática a partir de tus consultas y materiales. Dicho
                    contenido es orientativo, puede contener errores y <strong>no constituye asesoramiento oficial</strong>.
                    Debes verificar siempre la información con las fuentes oficiales antes de tomar decisiones.
                </p>
            </Section>

            <Section title="6. Disponibilidad y datos oficiales">
                <p>
                    Procuramos la máxima disponibilidad, pero el servicio se presta «tal cual» y puede sufrir
                    interrupciones. Los datos oficiales (vacantes, listados, normativa, convocatorias) proceden de fuentes
                    públicas y se ofrecen con fines informativos, sin garantía de exactitud o actualidad.
                </p>
            </Section>

            <Section title="7. Cancelación y supresión de la cuenta">
                <p>
                    Puedes eliminar tu cuenta en cualquier momento desde{' '}
                    <Link to="/dashboard/perfil" className="text-brand-700 underline">tu perfil</Link>. La eliminación
                    suprime tus datos personales y archivos asociados conforme a la Política de privacidad.
                </p>
            </Section>

            <Section title="8. Modificaciones y ley aplicable">
                <p>
                    Podemos actualizar estos términos por motivos legales o de mejora del servicio, avisando con antelación
                    razonable. Estos términos se rigen por la legislación española. Para consultas: {TITULAR.emailSoporte}.
                </p>
            </Section>
        </LegalLayout>
    );
}
