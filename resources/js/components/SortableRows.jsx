import {
    DndContext,
    MouseSensor,
    TouchSensor,
    KeyboardSensor,
    closestCenter,
    useSensor,
    useSensors,
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

// Pointer events on interactive children must not start a drag.
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

function SortableRow({ item, index, home, onStatusChange, onNotesChange }) {
    const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({
        id: item.vacancy_id,
    });

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
                vacancy={item.vacancy}
                status="selected"
                position={index + 1}
                notes={item.notes ?? ''}
                home={home}
                onStatusChange={(status) => onStatusChange(item.vacancy_id, status)}
                onNotesChange={(notes) => onNotesChange(item.vacancy_id, notes)}
                isDragging={isDragging}
            />
        </div>
    );
}

// Drag-and-drop list of compact rows used for the prioritised vacancy list.
export default function SortableRows({ items, home, onReorder, onStatusChange, onNotesChange, emptyLabel }) {
    const sensors = useSensors(
        useSensor(MouseSensor, { activationConstraint: { distance: 8 } }),
        useSensor(TouchSensor, { activationConstraint: { delay: 180, tolerance: 8 } }),
        useSensor(KeyboardSensor, { coordinateGetter: sortableKeyboardCoordinates })
    );

    const handleDragEnd = (event) => {
        const { active, over } = event;
        if (!over || active.id === over.id) return;

        const oldIndex = items.findIndex((i) => i.vacancy_id === active.id);
        const newIndex = items.findIndex((i) => i.vacancy_id === over.id);
        if (oldIndex === -1 || newIndex === -1) return;

        onReorder(arrayMove(items, oldIndex, newIndex).map((i) => i.vacancy_id));
    };

    if (items.length === 0) {
        return (
            <p className="rounded-lg border border-dashed border-slate-200 px-3 py-6 text-center text-xs text-slate-400">
                {emptyLabel ?? 'Aún no has añadido vacantes a tu lista. Usa ✓ para añadirlas.'}
            </p>
        );
    }

    return (
        <DndContext sensors={sensors} collisionDetection={closestCenter} onDragEnd={handleDragEnd}>
            <SortableContext items={items.map((i) => i.vacancy_id)} strategy={verticalListSortingStrategy}>
                <div className="space-y-1.5">
                    {items.map((item, index) => (
                        <SortableRow
                            key={item.vacancy_id}
                            home={home}
                            item={item}
                            index={index}
                            onStatusChange={onStatusChange}
                            onNotesChange={onNotesChange}
                        />
                    ))}
                </div>
            </SortableContext>
        </DndContext>
    );
}
