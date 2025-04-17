import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import type { Board } from '@/types/kanban';

interface MoveCardProps {
    cardId: number;
    currentColumnId: number;
}

export function MoveCard({ cardId, currentColumnId }: MoveCardProps) {
    const [selectedColumnId, setSelectedColumnId] = useState<string>(currentColumnId.toString());
    const queryClient = useQueryClient();

    const { data: board } = useQuery<Board>({
        queryKey: ['board'],
        select: (data) => data
    });

    const { mutate: moveCard } = useMutation({
        mutationFn: async () => {
            const columnId = parseInt(selectedColumnId);
            await fetch(`${import.meta.env.VITE_API_URL}/api/columns/${columnId}/cards/${cardId}`, {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    columnId,
                    position: board?.columns?.find(col => col.id === columnId)?.cards?.length ?? 0
                })
            });
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['board'] });
        }
    });

    if (!board) return null;

    return (
        <div className="flex gap-2">
            <Select
                value={selectedColumnId}
                onValueChange={setSelectedColumnId}
            >
                <SelectTrigger className="w-[200px]">
                    <SelectValue placeholder="Sélectionner une colonne" />
                </SelectTrigger>
                <SelectContent>
                    {board.columns && board.columns.map(column => (
                        <SelectItem
                            key={column.id}
                            value={column.id.toString()}
                            disabled={column.id === currentColumnId}
                        >
                            {column.name}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
            <Button
                onClick={() => moveCard()}
                disabled={parseInt(selectedColumnId) === currentColumnId}
            >
                Déplacer
            </Button>
        </div>
    );
} 