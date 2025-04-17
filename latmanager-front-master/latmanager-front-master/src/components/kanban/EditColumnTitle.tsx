import React, { useState } from 'react';
import { Input } from '@/components/ui/input';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import type { Board } from '@/types/kanban';

interface EditColumnTitleProps {
    columnId: number;
    boardId: number;
    initialTitle: string;
    onClose: () => void;
}

export function EditColumnTitle({ columnId, boardId, initialTitle, onClose }: EditColumnTitleProps) {
    const [title, setTitle] = useState(initialTitle);
    const queryClient = useQueryClient();
    const [error, setError] = useState<string | null>(null);

    const { mutate: updateTitle, isPending } = useMutation({
        mutationFn: async () => {
            const response = await fetch(`${import.meta.env.VITE_API_URL}/api/boards/${boardId}/columns/${columnId}`, {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ name: title })
            });
            
            if (!response.ok) {
                throw new Error("Erreur lors de la mise à jour de la colonne");
            }
            
            return response.json();
        },
        onSuccess: (updatedColumn) => {
            // Récupérer les données actuelles du tableau
            const currentBoard = queryClient.getQueryData<Board>(['board', boardId.toString()]);
            
            if (currentBoard) {
                // Créer une copie du tableau avec la colonne mise à jour
                const updatedBoard = {
                    ...currentBoard,
                    columns: currentBoard.columns.map(col => 
                        col.id === columnId ? { ...col, name: title } : col
                    )
                };
                
                // Mettre à jour le cache avec le tableau modifié
                queryClient.setQueryData(['board', boardId.toString()], updatedBoard);
                setError(null);
            } else {
                // Si le tableau n'est pas dans le cache, forcer un rechargement
                queryClient.invalidateQueries({ queryKey: ['board', boardId.toString()] });
            }
            
            onClose();
        },
        onError: (err) => {
            console.error("Erreur lors de la mise à jour:", err);
            setError("Erreur lors de la mise à jour de la colonne");
        }
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (title.trim() && title !== initialTitle) {
            updateTitle();
        } else {
            onClose();
        }
    };

    return (
        <form onSubmit={handleSubmit} className="flex-1 relative">
            <Input
                value={title}
                onChange={(e) => setTitle(e.target.value)}
                onBlur={handleSubmit}
                autoFocus
                className="h-7 py-1"
                disabled={isPending}
            />
            {isPending && (
                <div className="absolute right-2 top-1 text-xs text-blue-500">
                    Mise à jour...
                </div>
            )}
            {error && (
                <div className="text-xs text-red-500 mt-1">
                    {error}
                </div>
            )}
        </form>
    );
} 