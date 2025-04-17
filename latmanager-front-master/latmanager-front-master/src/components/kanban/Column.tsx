import { useDroppable } from '@dnd-kit/core';
import { Card } from './Card';
import type { Column as ColumnType } from '@/types/kanban';
import { CreateCard } from './CreateCard';
import { Button } from '@/components/ui/button';
import { Plus } from 'lucide-react';

interface ColumnProps {
    column: ColumnType;
    boardId: number;
}

export function Column({ column, boardId }: ColumnProps) {
    const { setNodeRef } = useDroppable({
        id: column.id
    });

    return (
        <div 
            ref={setNodeRef}
            className="bg-gray-50 w-72 rounded-lg border border-gray-200 shadow-sm overflow-hidden flex flex-col"
        >
            <div className="p-3 border-b border-gray-200 bg-gradient-to-r from-gray-50 to-gray-100">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-2">
                        <span className="font-medium text-gray-700">{column.name}</span>
                        <span className="px-2 py-0.5 bg-gray-200 text-gray-600 rounded-full text-xs font-medium">
                            {column.cards ? column.cards.length : 0}
                        </span>
                    </div>
                </div>
            </div>
            
            <div className="p-2 flex-1 overflow-y-auto max-h-[calc(100vh-220px)]">
                <div className="space-y-2 min-h-[50px]">
                    {column.cards.map(card => (
                        <Card 
                            key={`${column.id}-card-${card.id}`}
                            card={card} 
                            columnId={column.id} 
                        />
                    ))}
                </div>
                <div className="mt-3">
                    <CreateCard columnId={column.id} boardId={boardId} />
                </div>
            </div>
        </div>
    );
}