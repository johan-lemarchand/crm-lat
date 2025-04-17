import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Plus } from 'lucide-react';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { useMutation, useQueryClient } from '@tanstack/react-query';

interface CreateChecklistProps {
    cardId: number;
    columnId: number;
}

export function CreateChecklist({ cardId, columnId }: CreateChecklistProps) {
    const [isOpen, setIsOpen] = useState(false);
    const [title, setTitle] = useState('');
    const queryClient = useQueryClient();

    const { mutate: createChecklist } = useMutation({
        mutationFn: async () => {
            await fetch(`${import.meta.env.VITE_API_URL}/api/cards/${cardId}/checklists`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ title })
            });
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['board'] });
            setTitle('');
            setIsOpen(false);
        }
    });

    return (
        <Dialog open={isOpen} onOpenChange={setIsOpen}>
            <DialogTrigger asChild>
                <Button variant="outline" size="sm">
                    <Plus className="h-4 w-4 mr-2" />
                    Ajouter
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Nouvelle checklist</DialogTitle>
                </DialogHeader>
                <div className="space-y-4">
                    <div className="space-y-2">
                        <Input
                            value={title}
                            onChange={(e) => setTitle(e.target.value)}
                            placeholder="Titre de la checklist"
                            autoFocus
                        />
                    </div>
                    <Button
                        onClick={() => createChecklist()}
                        disabled={!title.trim()}
                    >
                        Créer
                    </Button>
                </div>
            </DialogContent>
        </Dialog>
    );
} 