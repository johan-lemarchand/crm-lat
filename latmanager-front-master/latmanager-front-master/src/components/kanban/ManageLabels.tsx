import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Plus } from 'lucide-react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import type { Label as LabelType } from '@/types/kanban';
import { useState } from 'react';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

interface ManageLabelsProps {
    cardId: number;
}

export function ManageLabels({ cardId }: ManageLabelsProps) {
    const [isOpen, setIsOpen] = useState(false);
    const queryClient = useQueryClient();

    const { data: labels } = useQuery<LabelType[]>({
        queryKey: ['labels'],
        queryFn: async () => {
            const response = await fetch(`${import.meta.env.VITE_API_URL}/api/labels`);
            return response.json();
        }
    });

    const { mutate: addLabel } = useMutation({
        mutationFn: async (labelId: number) => {
            await fetch(`${import.meta.env.VITE_API_URL}/api/cards/${cardId}/labels`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ labelId })
            });
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['board'] });
        }
    });

    const { mutate: createLabel } = useMutation({
        mutationFn: async (data: { name: string; color: string }) => {
            const response = await fetch(`${import.meta.env.VITE_API_URL}/api/labels`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
            return response.json();
        },
        onSuccess: (newLabel) => {
            queryClient.invalidateQueries({ queryKey: ['labels'] });
            addLabel(newLabel.id);
        }
    });

    return (
        <Dialog open={isOpen} onOpenChange={setIsOpen}>
            <DialogTrigger asChild>
                <Button variant="outline" size="sm">
                    <Plus className="h-4 w-4 mr-1" />
                    Ajouter
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Gérer les labels</DialogTitle>
                </DialogHeader>

                <div className="space-y-4">
                    <div className="grid gap-2">
                        {labels?.map((label) => (
                            <div
                                key={label.id}
                                className="flex items-center gap-2 p-2 hover:bg-gray-100 rounded cursor-pointer"
                                onClick={() => {
                                    addLabel(label.id);
                                    setIsOpen(false);
                                }}
                            >
                                <div
                                    className="w-4 h-4 rounded"
                                    style={{ backgroundColor: label.color }}
                                />
                                <span>{label.name}</span>
                            </div>
                        ))}
                    </div>

                    <div className="border-t pt-4">
                        <h4 className="font-medium mb-2">Créer un nouveau label</h4>
                        <form
                            onSubmit={(e) => {
                                e.preventDefault();
                                const formData = new FormData(e.currentTarget);
                                createLabel({
                                    name: formData.get('name') as string,
                                    color: formData.get('color') as string
                                });
                                setIsOpen(false);
                            }}
                            className="space-y-4"
                        >
                            <div className="grid gap-2">
                                <Label htmlFor="name">Nom</Label>
                                <Input id="name" name="name" required />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="color">Couleur</Label>
                                <Input id="color" name="color" type="color" required />
                            </div>
                            <Button type="submit">Créer</Button>
                        </form>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    );
} 