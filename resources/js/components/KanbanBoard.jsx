import clsx from 'clsx';
import {
    DndContext,
    DragOverlay,
    KeyboardSensor,
    MouseSensor,
    TouchSensor,
    closestCorners,
    useSensor,
    useSensors,
    useDroppable,
} from '@dnd-kit/core';
import {
    SortableContext,
    arrayMove,
    sortableKeyboardCoordinates,
    useSortable,
    verticalListSortingStrategy,
} from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { useState } from 'react';
import VacancyCard from './VacancyCard';

const STATUS_OF = { neutral: 'neutral', selected: 'selected', revisar: 'revisar', discarded: 'discarded' };

// Pointer events on interactive children (buttons, links, notes) must NOT
// start a drag, so their normal click/typing behaviour works; dragging from
// anywhere else on the card does.
const INTERACTIVE = 'button, a, textarea, input, select, label';
function guardListeners(listeners) {
    return {
        ...listeners,
        onPointerDown: (e) => {
            if (e.target.closest?.(INTERACTIVE)) return;
            listeners?.onPointerDown?.(e);
        },
    };
}

// A card draggable from ANY point (not just a handle) between columns and
// reorderable within "Mi lista".
function SortableVacancyCard({ id, vacancy, status, position, notes, home, onStatusChange, onNotesChange }) {
    const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({ id });
    const style = {
        transform: CSS.Transform.toString(transform),
        transition,
        zIndex: isDragging ? 50 : undefined,
        opacity: isDragging ? 0.5 : 1,
    };
    return (
        <div
            ref={setNodeRef}
            style={style}
            {...attributes}
            {...guardListeners(listeners)}
            className="cursor-grab active:cursor-grabbing"
        >
            <VacancyCard
                vacancy={vacancy}
                status={status}
                position={position}
                notes={notes ?? ''}
                home={home}
                onStatusChange={onStatusChange}
                onNotesChange={onNotesChange}
                isDragging={isDragging}
            />
        </div>
    );
}

function Column({ id, title, count, accent, children, footer }) {
    const { setNodeRef, isOver } = useDroppable({ id });
    return (
        <div
            ref={setNodeRef}
            className={clsx(
                'flex min-h-0 flex-col rounded-xl bg-slate-50/80 ring-1 transition',
                isOver ? 'bg-brand-50/40 ring-2 ring-brand-300' : 'ring-slate-200'
            )}
        >
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
    revisar = [],
    discarded,
    home = null,
    showDiscarded,
    onStatusChange,
    onNotesChange,
    onReorder,
    hasMore,
    onLoadMore,
    isLoadingMore,
}) {
    const [activeId, setActiveId] = useState(null);

    const sensors = useSensors(
        // Mouse: drag after a small move (so clicks on the card still register).
        useSensor(MouseSensor, { activationConstraint: { distance: 8 } }),
        // Touch: press-and-hold to drag, so a quick swipe still scrolls the column.
        useSensor(TouchSensor, { activationConstraint: { delay: 180, tolerance: 8 } }),
        useSensor(KeyboardSensor, { coordinateGetter: sortableKeyboardCoordinates })
    );

    const neutralIds = neutral.map((v) => v.id);
    const selectedIds = selected.map((i) => i.vacancy_id);
    const revisarIds = revisar.map((i) => i.vacancy_id);
    const discardedIds = discarded.map((i) => i.vacancy_id);

    // Which column a given id lives in. `over.id` can also be a column id when
    // hovering an empty area of a column.
    const columnOf = (id) => {
        if (id in STATUS_OF) return id;
        if (neutralIds.includes(id)) return 'neutral';
        if (selectedIds.includes(id)) return 'selected';
        if (revisarIds.includes(id)) return 'revisar';
        if (discardedIds.includes(id)) return 'discarded';
        return null;
    };

    const activeVacancy =
        neutral.find((v) => v.id === activeId) ||
        selected.find((i) => i.vacancy_id === activeId)?.vacancy ||
        revisar.find((i) => i.vacancy_id === activeId)?.vacancy ||
        discarded.find((i) => i.vacancy_id === activeId)?.vacancy ||
        null;

    const handleDragEnd = (event) => {
        setActiveId(null);
        const { active, over } = event;
        if (!over) return;

        const from = columnOf(active.id);
        const to = columnOf(over.id);
        if (!from || !to) return;

        if (from !== to) {
            // Dropped on a different column → change the vacancy's status.
            onStatusChange(active.id, to);
            return;
        }

        // Same column: only "Mi lista" supports manual reordering.
        if (to === 'selected' && active.id !== over.id && over.id !== 'selected') {
            const oldIndex = selectedIds.indexOf(active.id);
            const newIndex = selectedIds.indexOf(over.id);
            if (oldIndex !== -1 && newIndex !== -1) {
                onReorder(arrayMove(selectedIds, oldIndex, newIndex));
            }
        }
    };

    return (
        <DndContext
            sensors={sensors}
            collisionDetection={closestCorners}
            onDragStart={(e) => setActiveId(e.active.id)}
            onDragCancel={() => setActiveId(null)}
            onDragEnd={handleDragEnd}
        >
            <div className="grid h-full min-h-0 grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-4">
                {/* Sin revisar */}
                <Column
                    id="neutral"
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
                    <SortableContext items={neutralIds} strategy={verticalListSortingStrategy}>
                        {neutral.length === 0 ? (
                            <p className="px-2 py-6 text-center text-xs text-slate-400">
                                No hay vacantes con estos filtros.
                            </p>
                        ) : (
                            neutral.map((v) => (
                                <SortableVacancyCard
                                    key={v.id}
                                    id={v.id}
                                    vacancy={v}
                                    status="neutral"
                                    home={home}
                                    onStatusChange={(status) => onStatusChange(v.id, status)}
                                />
                            ))
                        )}
                    </SortableContext>
                </Column>

                {/* A revisar */}
                <Column id="revisar" title="A revisar" count={revisar.length} accent="bg-amber-500">
                    <SortableContext items={revisarIds} strategy={verticalListSortingStrategy}>
                        {revisar.length === 0 ? (
                            <p className="rounded-lg border border-dashed border-slate-200 px-3 py-6 text-center text-xs text-slate-400">
                                Arrastra aquí las que tengas dudas, o pulsa «A revisar».
                            </p>
                        ) : (
                            revisar.map((item) => (
                                <SortableVacancyCard
                                    key={item.vacancy_id}
                                    id={item.vacancy_id}
                                    vacancy={item.vacancy}
                                    status="revisar"
                                    notes={item.notes}
                                    home={home}
                                    onStatusChange={(status) => onStatusChange(item.vacancy_id, status)}
                                    onNotesChange={(notes) => onNotesChange(item.vacancy_id, notes)}
                                />
                            ))
                        )}
                    </SortableContext>
                </Column>

                {/* Mi lista (sortable + droppable) */}
                <Column id="selected" title="Mi lista" count={selected.length} accent="bg-emerald-500">
                    <SortableContext items={selectedIds} strategy={verticalListSortingStrategy}>
                        {selected.length === 0 ? (
                            <p className="rounded-lg border border-dashed border-slate-200 px-3 py-6 text-center text-xs text-slate-400">
                                Arrastra una vacante aquí (icono ⠿) o pulsa ✓ Mi lista.
                            </p>
                        ) : (
                            selected.map((item, index) => (
                                <SortableVacancyCard
                                    key={item.vacancy_id}
                                    id={item.vacancy_id}
                                    vacancy={item.vacancy}
                                    status="selected"
                                    position={index + 1}
                                    notes={item.notes}
                                    home={home}
                                    onStatusChange={(status) => onStatusChange(item.vacancy_id, status)}
                                    onNotesChange={(notes) => onNotesChange(item.vacancy_id, notes)}
                                />
                            ))
                        )}
                    </SortableContext>
                </Column>

                {/* Descartadas */}
                <Column id="discarded" title="Descartadas" count={discarded.length} accent="bg-rose-400">
                    {!showDiscarded ? (
                        <p className="px-2 py-6 text-center text-xs text-slate-400">
                            Marca «Descartada» en «Estado en mi lista» (filtros) para verlas.
                        </p>
                    ) : (
                        <SortableContext items={discardedIds} strategy={verticalListSortingStrategy}>
                            {discarded.length === 0 ? (
                                <p className="px-2 py-6 text-center text-xs text-slate-400">
                                    Arrastra una vacante aquí o pulsa ✕ Descartar.
                                </p>
                            ) : (
                                discarded.map((item) => (
                                    <SortableVacancyCard
                                        key={item.vacancy_id}
                                        id={item.vacancy_id}
                                        vacancy={item.vacancy}
                                        status="discarded"
                                        notes={item.notes}
                                        home={home}
                                        onStatusChange={(status) => onStatusChange(item.vacancy_id, status)}
                                        onNotesChange={(notes) => onNotesChange(item.vacancy_id, notes)}
                                    />
                                ))
                            )}
                        </SortableContext>
                    )}
                </Column>
            </div>

            <DragOverlay>
                {activeVacancy ? <VacancyCard vacancy={activeVacancy} status="neutral" home={home} isDragging /> : null}
            </DragOverlay>
        </DndContext>
    );
}
