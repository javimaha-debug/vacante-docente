import {
    DndContext,
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
import VacancyRow from './VacancyRow';

const STATUS_OF = { selected: 'selected', revisar: 'revisar', neutral: 'neutral' };
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

function SortableRowItem({ id, vacancy, status, position, notes, home, onStatusChange, onNotesChange }) {
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
            <VacancyRow
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

function Section({ id, children }) {
    const { setNodeRef, isOver } = useDroppable({ id });
    return (
        <div
            ref={setNodeRef}
            className={isOver ? 'rounded-xl ring-2 ring-brand-300' : undefined}
        >
            {children}
        </div>
    );
}

// Single drag-and-drop surface for the list view: reorder within "Mi lista
// priorizada" and drag a candidate row up into it (or back down).
export default function ListBoard({
    selected,
    revisar = [],
    neutral,
    home,
    onStatusChange,
    onNotesChange,
    onReorder,
    hasMore,
    onLoadMore,
    isLoadingMore,
}) {
    const sensors = useSensors(
        useSensor(MouseSensor, { activationConstraint: { distance: 8 } }),
        useSensor(TouchSensor, { activationConstraint: { delay: 180, tolerance: 8 } }),
        useSensor(KeyboardSensor, { coordinateGetter: sortableKeyboardCoordinates })
    );

    const selectedIds = selected.map((i) => i.vacancy_id);
    const revisarIds = revisar.map((i) => i.vacancy_id);
    const neutralIds = neutral.map((v) => v.id);

    const columnOf = (id) => {
        if (id in STATUS_OF) return id;
        if (selectedIds.includes(id)) return 'selected';
        if (revisarIds.includes(id)) return 'revisar';
        if (neutralIds.includes(id)) return 'neutral';
        return null;
    };

    const handleDragEnd = ({ active, over }) => {
        if (!over) return;
        const from = columnOf(active.id);
        const to = columnOf(over.id);
        if (!from || !to) return;

        if (from !== to) {
            onStatusChange(active.id, to);
            return;
        }
        if (to === 'selected' && active.id !== over.id && over.id !== 'selected') {
            const oldIndex = selectedIds.indexOf(active.id);
            const newIndex = selectedIds.indexOf(over.id);
            if (oldIndex !== -1 && newIndex !== -1) {
                onReorder(arrayMove(selectedIds, oldIndex, newIndex));
            }
        }
    };

    return (
        <DndContext sensors={sensors} collisionDetection={closestCorners} onDragEnd={handleDragEnd}>
            <div className="scroll-thin h-full space-y-6 overflow-y-auto pr-1">
                <Section id="selected">
                    <h2 className="mb-2 text-sm font-bold text-slate-700">
                        Mi lista priorizada <span className="text-slate-400">({selected.length})</span>
                        <span className="ml-2 text-xs font-normal text-slate-400">arrastra para ordenar o quitar</span>
                    </h2>
                    <SortableContext items={selectedIds} strategy={verticalListSortingStrategy}>
                        {selected.length === 0 ? (
                            <p className="rounded-lg border border-dashed border-slate-200 px-3 py-6 text-center text-xs text-slate-400">
                                Arrastra una vacante aquí desde abajo, o pulsa ✓ en una vacante.
                            </p>
                        ) : (
                            <div className="space-y-1.5">
                                {selected.map((item, index) => (
                                    <SortableRowItem
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
                                ))}
                            </div>
                        )}
                </SortableContext>
                </Section>

                {revisar.length > 0 && (
                    <Section id="revisar">
                        <h2 className="mb-2 text-sm font-bold text-slate-700">
                            A revisar <span className="text-slate-400">({revisar.length})</span>
                            <span className="ml-2 text-xs font-normal text-slate-400">dudas pendientes</span>
                        </h2>
                        <SortableContext items={revisarIds} strategy={verticalListSortingStrategy}>
                            <div className="space-y-1.5">
                                {revisar.map((item) => (
                                    <SortableRowItem
                                        key={item.vacancy_id}
                                        id={item.vacancy_id}
                                        vacancy={item.vacancy}
                                        status="revisar"
                                        notes={item.notes}
                                        home={home}
                                        onStatusChange={(status) => onStatusChange(item.vacancy_id, status)}
                                        onNotesChange={(notes) => onNotesChange(item.vacancy_id, notes)}
                                    />
                                ))}
                            </div>
                        </SortableContext>
                    </Section>
                )}

                <Section id="neutral">
                    <h2 className="mb-2 text-sm font-bold text-slate-700">
                        Vacantes <span className="text-slate-400">({neutral.length})</span>
                    </h2>
                    <SortableContext items={neutralIds} strategy={verticalListSortingStrategy}>
                        {neutral.length === 0 ? (
                            <p className="rounded-lg border border-dashed border-slate-200 px-3 py-6 text-center text-xs text-slate-400">
                                No hay vacantes con estos filtros.
                            </p>
                        ) : (
                            <div className="space-y-1.5">
                                {neutral.map((v) => (
                                    <SortableRowItem
                                        key={v.id}
                                        id={v.id}
                                        vacancy={v}
                                        status="neutral"
                                        home={home}
                                        onStatusChange={(status) => onStatusChange(v.id, status)}
                                    />
                                ))}
                            </div>
                        )}
                    </SortableContext>
                    {hasMore && (
                        <button
                            onClick={onLoadMore}
                            disabled={isLoadingMore}
                            className="mt-3 w-full rounded-lg bg-white px-3 py-2 text-sm font-semibold text-brand-600 ring-1 ring-slate-200 hover:bg-brand-50 disabled:opacity-60"
                        >
                            {isLoadingMore ? 'Cargando…' : 'Cargar más vacantes'}
                        </button>
                    )}
                </Section>
            </div>
        </DndContext>
    );
}
