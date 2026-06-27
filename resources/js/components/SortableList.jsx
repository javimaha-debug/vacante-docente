import {
    DndContext,
    PointerSensor,
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
import VacancyCard from './VacancyCard';

function SortableCard({ item, index, onStatusChange, onNotesChange }) {
    const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({
        id: item.vacancy_id,
    });

    const style = {
        transform: CSS.Transform.toString(transform),
        transition,
        zIndex: isDragging ? 50 : undefined,
    };

    return (
        <div ref={setNodeRef} style={style}>
            <VacancyCard
                vacancy={item.vacancy}
                status="selected"
                position={index + 1}
                notes={item.notes ?? ''}
                onStatusChange={(status) => onStatusChange(item.vacancy_id, status)}
                onNotesChange={(notes) => onNotesChange(item.vacancy_id, notes)}
                dragHandleProps={{ ...attributes, ...listeners }}
                isDragging={isDragging}
            />
        </div>
    );
}

export default function SortableList({ items, onReorder, onStatusChange, onNotesChange, emptyLabel }) {
    const sensors = useSensors(
        useSensor(PointerSensor, { activationConstraint: { distance: 5 } }),
        useSensor(KeyboardSensor, { coordinateGetter: sortableKeyboardCoordinates })
    );

    const handleDragEnd = (event) => {
        const { active, over } = event;
        if (!over || active.id === over.id) return;

        const oldIndex = items.findIndex((i) => i.vacancy_id === active.id);
        const newIndex = items.findIndex((i) => i.vacancy_id === over.id);
        if (oldIndex === -1 || newIndex === -1) return;

        const reordered = arrayMove(items, oldIndex, newIndex);
        onReorder(reordered.map((i) => i.vacancy_id));
    };

    if (items.length === 0) {
        return (
            <p className="rounded-lg border border-dashed border-slate-200 px-3 py-6 text-center text-xs text-slate-400">
                {emptyLabel ?? 'Aún no has seleccionado vacantes.'}
            </p>
        );
    }

    return (
        <DndContext sensors={sensors} collisionDetection={closestCenter} onDragEnd={handleDragEnd}>
            <SortableContext items={items.map((i) => i.vacancy_id)} strategy={verticalListSortingStrategy}>
                <div className="space-y-2">
                    {items.map((item, index) => (
                        <SortableCard
                            key={item.vacancy_id}
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
