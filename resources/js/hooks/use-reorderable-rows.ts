import { useEffect, useState, type DragEvent } from 'react';

export function useReorderableRows<T extends { id: string }>(sourceRows: T[], enabled: boolean, onReorder: (ids: string[]) => void) {
    const [rows, setRows] = useState(sourceRows);
    const [draggedId, setDraggedId] = useState<string | null>(null);
    const [dropTargetId, setDropTargetId] = useState<string | null>(null);

    useEffect(() => setRows(sourceRows), [sourceRows]);

    const rowDragProps = (id: string) => ({
        draggable: enabled,
        onDragStart: (event: DragEvent<HTMLTableRowElement>) => {
            if (!enabled) return;
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', id);
            setDraggedId(id);
        },
        onDragOver: (event: DragEvent<HTMLTableRowElement>) => {
            if (!enabled || !draggedId || draggedId === id) return;
            event.preventDefault();
            event.dataTransfer.dropEffect = 'move';
            setDropTargetId(id);
        },
        onDragLeave: () => setDropTargetId((current) => (current === id ? null : current)),
        onDrop: (event: DragEvent<HTMLTableRowElement>) => {
            event.preventDefault();
            if (!enabled || !draggedId || draggedId === id) return;

            const next = [...rows];
            const from = next.findIndex((row) => row.id === draggedId);
            const to = next.findIndex((row) => row.id === id);
            if (from < 0 || to < 0) return;

            const [moved] = next.splice(from, 1);
            next.splice(to, 0, moved);
            setRows(next);
            setDraggedId(null);
            setDropTargetId(null);
            onReorder(next.map((row) => row.id));
        },
        onDragEnd: () => {
            setDraggedId(null);
            setDropTargetId(null);
        },
    });

    return { rows, rowDragProps, draggedId, dropTargetId };
}
