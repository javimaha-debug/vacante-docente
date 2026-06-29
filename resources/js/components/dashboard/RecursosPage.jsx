import { useQuery } from '@tanstack/react-query';
import api from '../../lib/api';

const CATEGORY_LABELS = {
    oficial: 'Recursos oficiales',
    sindicato: 'Sindicatos',
    otro: 'Otros recursos',
};

const CATEGORY_ORDER = ['oficial', 'sindicato', 'otro'];

export default function RecursosPage() {
    const { data, isLoading, isError, error, refetch } = useQuery({
        queryKey: ['recursos'],
        queryFn: async () => (await api.get('/recursos')).data,
        staleTime: 10 * 60 * 1000,
    });

    const links = data?.data ?? [];

    const byCategory = CATEGORY_ORDER.reduce((acc, cat) => {
        const items = links.filter((l) => l.category === cat);
        if (items.length > 0) acc[cat] = items;
        return acc;
    }, {});

    if (isLoading) {
        return (
            <div className="space-y-4">
                {[1, 2].map((i) => (
                    <div key={i} className="animate-pulse rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
                        <div className="mb-4 h-4 w-1/4 rounded bg-slate-200" />
                        <div className="space-y-3">
                            {[1, 2, 3].map((j) => <div key={j} className="h-12 rounded-lg bg-slate-100" />)}
                        </div>
                    </div>
                ))}
            </div>
        );
    }

    if (isError) {
        return (
            <div className="rounded-lg bg-rose-50 px-4 py-3 text-sm text-rose-600">
                {error?.friendlyMessage ?? 'No se pudieron cargar los recursos.'}
                <button onClick={() => refetch()} className="ml-2 font-semibold underline">Reintentar</button>
            </div>
        );
    }

    return (
        <div className="space-y-6">
            <div>
                <h1 className="font-heading text-xl font-bold text-slate-800">Banco de recursos</h1>
                <p className="text-sm text-slate-500 mt-1">Recursos esenciales para la bolsa de trabajo y oposiciones.</p>
            </div>

            {Object.entries(byCategory).map(([cat, items]) => (
                <section key={cat} className="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
                    <h2 className="mb-4 text-xs font-bold uppercase tracking-wide text-slate-400">
                        {CATEGORY_LABELS[cat] ?? cat}
                    </h2>
                    <ul className="divide-y divide-slate-50">
                        {items.map((link) => (
                            <li key={link.id}>
                                <a
                                    href={link.url}
                                    target="_blank"
                                    rel="noreferrer"
                                    className="flex items-start gap-3 py-3 -mx-1 rounded-lg px-1 transition hover:bg-slate-50"
                                >
                                    <span className="text-2xl shrink-0 mt-0.5" aria-hidden="true">{link.icon}</span>
                                    <div className="min-w-0 flex-1">
                                        <p className="text-sm font-semibold text-slate-800 group-hover:text-brand-700">{link.title}</p>
                                        {link.description && (
                                            <p className="text-xs text-slate-500 mt-0.5">{link.description}</p>
                                        )}
                                    </div>
                                    <span className="ml-auto shrink-0 self-center text-slate-300" aria-hidden="true">→</span>
                                </a>
                            </li>
                        ))}
                    </ul>
                </section>
            ))}

            {links.length === 0 && (
                <div className="rounded-2xl bg-white p-8 text-center shadow-sm ring-1 ring-slate-200">
                    <p className="text-sm text-slate-400">No hay recursos disponibles aún.</p>
                </div>
            )}
        </div>
    );
}
