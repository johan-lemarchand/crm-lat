import { useState } from 'react';
import { Textarea } from '@/components/ui/textarea';
import { Button } from '@/components/ui/button';
import { useMutation, useQueryClient } from '@tanstack/react-query';

interface EditCardDescriptionProps {
    cardId: number;
    columnId: number;
    initialDescription: string | null;
}

export function EditCardDescription({ cardId, columnId, initialDescription }: EditCardDescriptionProps) {
    const [isEditing, setIsEditing] = useState(false);
    const [description, setDescription] = useState(initialDescription || '');
    const queryClient = useQueryClient();

    const { mutate: updateDescription } = useMutation({
        mutationFn: async () => {
            await fetch(`${import.meta.env.VITE_API_URL}/api/columns/${columnId}/cards/${cardId}`, {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ description })
            });
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['board'] });
            setIsEditing(false);
        }
    });

    if (!isEditing) {
        return (
            <div 
                onClick={() => setIsEditing(true)}
                className="p-2 rounded bg-gray-50 hover:bg-gray-100 cursor-pointer min-h-[100px]"
            >
                {description || 'Ajouter une description...'}
            </div>
        );
    }

    return (
        <div className="space-y-2">
            <Textarea
                value={description}
                onChange={(e) => setDescription(e.target.value)}
                placeholder="Ajouter une description..."
                className="min-h-[100px]"
                autoFocus
            />
            <div className="flex gap-2">
                <Button 
                    onClick={() => updateDescription()}
                    disabled={description === initialDescription}
                >
                    Enregistrer
                </Button>
                <Button 
                    variant="outline" 
                    onClick={() => {
                        setDescription(initialDescription || '');
                        setIsEditing(false);
                    }}
                >
                    Annuler
                </Button>
            </div>
        </div>
    );
} 