import { useState } from 'react';
import clsx from 'clsx';
import VacancyCard from './VacancyCard';
import SortableList from './SortableList';

function Column({ title, count, accent, children, footer }) {
    return (
        <div className="flex min-h-0 flex-col rounded-xl bg-slate-50/80 ring-1 ring-slate-200">
            <div className="flex items-center justify-between gap-2 border-b border-slate-200 px-3 py-2">
                <div className="flex items-center gap-2">
                    <span className={clsx('h-2.5 w-2.5 rounded-full', accent)} />
                    <h2 className="text-sm font-semibold text-slate-700">{title}</h2>
                </div>
                <span className="rounded-full bg-white px-2 py-0.5 text-xs font-bold text-slate-500 ring-1 ring-slate-200">
                    {count}
                </span>
            </div>
            <div className="scroll-thin min-h-0 flex-1 space-y-2 overflow-y-auto p-2.5">{children}</div>
            {footer}
        </div>
    );
}

export default function KanbanBoard({
    neutral,
    selected,
    discarded,
    showDiscarded,
    onStatusChange,
    onNotesChange,
    onReorder,
    hasMore,
    onLoadMore,
    isLoadingMore,
}) {
    const [discardedOpen, setDiscardedOpen] = useState(false);

    return (
        <div className="grid h-full min-h-0 grid-cols-1 gap-3 lg:grid-cols-3">
            {/* Sin revisar */}
            <Column
                title="Sin revisar"
                count={neutral.length}
                accent="bg-slate-400"
                footer={
                    hasMore && (
                        <div className="border-t border-slate-200 p-2">
                            <button
                                onClick={onLoadMore}
                                disabled={isLoadingMore}
                                className="w-full rounded-lg bg-white px-3 py-1.5 text-xs font-semibold text-brand-600 ring-1 ring-slate-200 hover:bg-brand-50 disabled:opacity-60"
                            >
                                {isLoadingMore ? 'Cargando…' : 'Cargar más'}
                            </button>
                        </div>
                    )
                }
            >
                {neutral.length === 0 ? (
                    <p className="px-2 py-6 text-center text-xs text-slate-400">No hay vacantes con estos filtros.</p>
                ) : (
                    neutral.map((v) => (
                        <VacancyCard
                            key={v.id}
                            vacancy={v}
                            status="neutral"
                            onStatusChange={(status) => onStatusChange(v.id, status)}
                        />
                    ))
                )}
            </Column>

            {/* Mi lista (sortable) */}
            <Column title="Mi lista" count={selected.length} accent="bg-emerald-500">
                <SortableList
                    items={selected}
                    onReorder={onReorder}
                    onStatusChange={onStatusChange}
                    onNotesChange={onNotesChange}
                    emptyLabel="Arrastra aquí o pulsa ✓ Mi lista en una vacante."
                />
            </Column>

            {/* Descartadas (collapsible) */}
            <Column title="Descartadas" count={discarded.length} accent="bg-rose-400">
                {!showDiscarded ? (
                    <p className="px-2 py-6 text-center text-xs text-slate-400">
                        Activa «Mostrar descartadas» en los filtros para verlas.
                    </p>
                ) : (
                    <>
                        <button
                            onClick={() => setDiscardedOpen((v) => !v)}
                            className="mb-1 w-full rounded-lg bg-white px-3 py-1.5 text-left text-xs font-semibold text-slate-500 ring-1 ring-slate-200"
                        >
                            {discardedOpen ? '▾ Ocultar' : '▸ Mostrar'} {discarded.length} descartadas
                        </button>
                        {discardedOpen &&
                            discarded.map((item) => (
                                <VacancyCard
                                    key={item.vacancy_id}
                                    vacancy={item.vacancy}
                                    status="discarded"
                                    notes={item.notes ?? ''}
                                    onStatusChange={(status) => onStatusChange(item.vacancy_id, status)}
                                    onNotesChange={(notes) => onNotesChange(item.vacancy_id, notes)}
                                />
                            ))}
                    </>
                )}
            </Column>
        </div>
    );
}
