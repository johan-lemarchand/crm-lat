import { useDraggable } from '@dnd-kit/core';
import { CSS } from '@dnd-kit/utilities';
import type { Card as CardType } from '@/types/kanban';
import { CalendarIcon } from 'lucide-react';
import { format } from 'date-fns';

interface CardProps {
    card: CardType;
    columnId: number;
}

export function Card({ card, columnId }: CardProps) {
    const { attributes, listeners, setNodeRef, transform, isDragging } = useDraggable({
        id: card.id,
        data: {
            type: 'card',
            columnId
        }
    });

    const style = transform ? {
        transform: CSS.Transform.toString(transform),
        transition: isDragging ? 'none' : 'transform 120ms ease-in-out'
    } : undefined;

    // Fonction pour formater la date d'échéance
    const formatDueDate = (dateString: string) => {
        if (!dateString) return null;
        const date = new Date(dateString);
        const today = new Date();
        const isOverdue = date < today;
        
        return {
            formattedDate: format(date, 'dd MMM'),
            isOverdue
        };
    };

    // Vérifier si la carte a des labels
    const hasLabels = card.labels && card.labels.length > 0;
    // Vérifier si la carte a une date d'échéance
    const hasDueDate = card.dueDate;
    // Vérifier si la carte a une liste de tâches
    const hasChecklist = card.checklists && card.checklists.length > 0;
    // Calculer le progrès des tâches si une liste existe
    const checklistProgress = hasChecklist 
        ? Math.round((card.checklists.filter((item: any) => item.completed).length / card.checklists.length) * 100)
        : 0;

    return (
        <div
            ref={setNodeRef}
            style={style}
            {...listeners}
            {...attributes}
            className={`bg-white p-3 rounded-md border border-gray-200 shadow-sm cursor-grab hover:shadow-md transition-all hover:border-blue-300 group
                ${isDragging ? 'opacity-30 scale-[0.98] z-10' : 'opacity-100'}
            `}
        >
            {/* Labels au début de la carte si présents */}
            {hasLabels && (
                <div className="flex flex-wrap gap-1 mb-2">
                    {card.labels.map((label: any) => (
                        <div 
                            key={label.id} 
                            className="h-2 w-12 rounded-full" 
                            style={{ backgroundColor: label.color }}
                            title={label.name}
                        />
                    ))}
                </div>
            )}
            
            <div className="font-medium text-gray-800">{card.title}</div>
            {card.description && (
                <div className="text-sm text-gray-600 mt-1 line-clamp-2">
                    {card.description}
                </div>
            )}
            
            {/* Barre de progression si liste de tâches présente */}
            {hasChecklist && (
                <div className="mt-2">
                    <div className="w-full bg-gray-200 rounded-full h-1.5">
                        <div 
                            className="bg-blue-500 h-1.5 rounded-full" 
                            style={{ width: `${checklistProgress}%` }}
                        />
                    </div>
                    <div className="text-xs text-gray-500 mt-0.5 text-right">
                        {checklistProgress}%
                    </div>
                </div>
            )}
            
            {/* Date d'échéance */}
            {hasDueDate && card.dueDate && (
                <div className="mt-2">
                    <div className={`inline-flex items-center gap-1 px-2 py-1 rounded-md text-xs ${
                        formatDueDate(card.dueDate)?.isOverdue ? 'bg-red-50 text-red-600' : 'bg-blue-50 text-blue-600'
                    }`}>
                        <CalendarIcon className="h-3 w-3" />
                        <span>{formatDueDate(card.dueDate)?.formattedDate}</span>
                    </div>
                </div>
            )}
        </div>
    );
} 