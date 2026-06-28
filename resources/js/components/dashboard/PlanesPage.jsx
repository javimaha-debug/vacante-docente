import { useQuery } from '@tanstack/react-query';
import api from '../../lib/api';
import { useAuth } from '../../hooks/useAuth';

const FEATURE_LABELS = {
    explorador_basico: 'Explorador de vacantes',
    lista_30_vacantes: 'Lista de hasta 30 vacantes',
    tablon_lectura: 'Lectura del tablón',
    ia_5_consultas_mes: '5 consultas IA al mes',
    monitor_gva: 'Monitor de avisos GVA',
    todo_free: 'Todo lo del plan Gratis',
    vacantes_ilimitadas: 'Vacantes ilimitadas',
    filtros_avanzados: 'Filtros avanzados',
    exportar_ovidoc: 'Exportar a OVIDOC',
    alertas_continuas: 'Alertas de adjudicaciones continuas',
    tablon_completo: 'Tablón completo (publicar)',
    calculadora_bolsa: 'Calculadora de bolsa',
    todo_interino: 'Todo lo del plan Interino',
    ia_ilimitada: 'IA ilimitada',
    normativa_ccaa: 'Normativa por comunidad',
    tests_flashcards: 'Tests y flashcards',
    simulador_oral: 'Simulador de oral',
    alertas_convocatorias: 'Alertas de convocatorias',
    monitor_convocatorias: 'Monitor de convocatorias',
    todo_opositor: 'Todo lo del plan Opositor',
    herramientas_aula: 'Herramientas de aula',
    normativa_vigente: 'Normativa vigente',
    asistente_nee: 'Asistente NEE',
    banco_recursos: 'Banco de recursos',
    todo_docente_pro: 'Todo lo del plan Docente Pro',
};

export default function PlanesPage() {
    const { user } = useAuth();
    const { data, isLoading } = useQuery({
        queryKey: ['planes'],
        queryFn: async () => (await api.get('/planes')).data,
    });

    return (
        <div className="mx-auto max-w-5xl">
            <div className="text-center">
                <h1 className="text-2xl font-bold text-slate-900">Planes</h1>
                <p className="mt-1 text-sm text-slate-500">Elige el plan que mejor se adapta a tu momento profesional.</p>
            </div>

            {isLoading ? (
                <div className="mt-6 grid gap-4 md:grid-cols-3">
                    {Array.from({ length: 3 }).map((_, i) => <div key={i} className="h-72 animate-pulse rounded-2xl bg-white" />)}
                </div>
            ) : (
                <div className="mt-6 grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    {(data?.data ?? []).map((plan) => {
                        const isCurrent = user?.plan === plan.codigo;
                        return (
                            <div
                                key={plan.codigo}
                                className={`flex flex-col rounded-2xl border bg-white p-5 shadow-sm ${isCurrent ? 'border-brand-500 ring-2 ring-brand-200' : 'border-slate-200'}`}
                            >
                                <div className="flex items-center justify-between">
                                    <h2 className="text-lg font-bold text-slate-800">{plan.nombre}</h2>
                                    {isCurrent && <span className="rounded-full bg-brand-100 px-2 py-0.5 text-xs font-semibold text-brand-700">Tu plan</span>}
                                </div>
                                <p className="mt-1 text-sm text-slate-500">{plan.descripcion}</p>
                                <ul className="mt-4 flex-1 space-y-1.5 text-sm text-slate-600">
                                    {(plan.features ?? []).map((f) => (
                                        <li key={f} className="flex items-start gap-2">
                                            <span className="text-green-500" aria-hidden="true">✓</span>
                                            {FEATURE_LABELS[f] ?? f}
                                        </li>
                                    ))}
                                </ul>
                                <button
                                    disabled={isCurrent}
                                    className="mt-4 rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700 disabled:bg-slate-200 disabled:text-slate-400"
                                >
                                    {isCurrent ? 'Plan actual' : 'Próximamente'}
                                </button>
                            </div>
                        );
                    })}
                </div>
            )}
            <p className="mt-6 text-center text-xs text-slate-400">Los precios se anunciarán próximamente.</p>
        </div>
    );
}
