import React, { useState } from 'react';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogFooter,
} from '@/components/ui/dialog';
import { Plus } from 'lucide-react';
import type { Board } from '@/types/kanban';

interface CreateColumnProps {
    boardId: number;
}

export function CreateColumn({ boardId }: CreateColumnProps) {
    const [isOpen, setIsOpen] = useState(false);
    const [name, setName] = useState('');
    const queryClient = useQueryClient();

    const { mutate: createColumn, isPending } = useMutation({
        mutationFn: async () => {
            const response = await fetch(`${import.meta.env.VITE_API_URL}/api/boards/${boardId}/columns`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ name })
            });
            
            if (!response.ok) {
                throw new Error('Erreur lors de la création de la colonne');
            }
            
            return response.json();
        },
        onSuccess: (newColumnData) => {
            // Récupérer les données du tableau actuelles
            const currentBoard = queryClient.getQueryData<Board>(['board', boardId.toString()]);
            
            if (currentBoard) {
                // Créer une copie mise à jour du tableau avec la nouvelle colonne
                const updatedBoard = {
                    ...currentBoard,
                    columns: [...currentBoard.columns, newColumnData]
                };
                
                // Mettre à jour le cache avec le tableau complet mis à jour
                queryClient.setQueryData(['board', boardId.toString()], updatedBoard);
            } else {
                // Si pour une raison quelconque nous n'avons pas les données actuelles,
                // demander au backend de renvoyer les données complètes du tableau
                queryClient.invalidateQueries({ queryKey: ['board', boardId.toString()] });
            }
            
            setIsOpen(false);
            setName('');
        },
        onError: (error) => {
            console.error("Erreur lors de la création de la colonne:", error);
            alert("Erreur lors de la création de la colonne");
        }
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (name.trim()) {
            createColumn();
        }
    };

    return (
        <>
            <Button size="sm" onClick={() => setIsOpen(true)}>
                <Plus className="w-4 h-4 mr-2" />
                Ajouter une colonne
            </Button>

            <Dialog open={isOpen} onOpenChange={setIsOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Nouvelle colonne</DialogTitle>
                    </DialogHeader>
                    <form onSubmit={handleSubmit}>
                        <div className="space-y-4">
                            <div className="space-y-2">
                                <Input
                                    placeholder="Nom de la colonne"
                                    value={name}
                                    onChange={(e) => setName(e.target.value)}
                                />
                            </div>
                        </div>
                        <DialogFooter className="mt-4">
                            <Button type="button" variant="outline" onClick={() => setIsOpen(false)}>
                                Annuler
                            </Button>
                            <Button type="submit" disabled={isPending || !name.trim()}>
                                {isPending ? 'Création...' : 'Créer'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
} 