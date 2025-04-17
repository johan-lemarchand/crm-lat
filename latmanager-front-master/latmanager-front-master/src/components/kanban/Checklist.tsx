import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Checkbox } from '@/components/ui/checkbox';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { MoreVertical, Plus, Trash } from 'lucide-react';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import type { Checklist as ChecklistType } from '@/types/kanban';
import { Progress } from '@/components/ui/progress';

interface ChecklistProps {
    checklist: ChecklistType;
    cardId: number;
    columnId: number;
}

export function Checklist({ checklist, cardId, columnId }: ChecklistProps) {
    const [newItemText, setNewItemText] = useState('');
    const [isAddingItem, setIsAddingItem] = useState(false);
    const queryClient = useQueryClient();

    const progress = checklist.items.length > 0
        ? Math.round((checklist.items.filter(item => item.completed).length / checklist.items.length) * 100)
        : 0;

    const { mutate: toggleItem } = useMutation({
        mutationFn: async (itemId: number) => {
            await fetch(`${import.meta.env.VITE_API_URL}/api/cards/${cardId}/checklists/${checklist.id}/items/${itemId}`, {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ completed: !checklist.items.find(item => item.id === itemId)?.completed })
            });
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['board'] });
        }
    });

    const { mutate: addItem } = useMutation({
        mutationFn: async () => {
            await fetch(`${import.meta.env.VITE_API_URL}/api/cards/${cardId}/checklists/${checklist.id}/items`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ content: newItemText })
            });
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['board'] });
            setNewItemText('');
            setIsAddingItem(false);
        }
    });

    const { mutate: deleteChecklist } = useMutation({
        mutationFn: async () => {
            await fetch(`${import.meta.env.VITE_API_URL}/api/cards/${cardId}/checklists/${checklist.id}`, {
                method: 'DELETE'
            });
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['board'] });
        }
    });

    const { mutate: deleteItem } = useMutation({
        mutationFn: async (itemId: number) => {
            await fetch(`${import.meta.env.VITE_API_URL}/api/cards/${cardId}/checklists/${checklist.id}/items/${itemId}`, {
                method: 'DELETE'
            });
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['board'] });
        }
    });

    return (
        <div className="space-y-2">
            <div className="flex items-center justify-between">
                <div className="flex items-center gap-2">
                    <h4 className="font-medium">{checklist.title}</h4>
                    <span className="text-sm text-gray-500">{progress}%</span>
                </div>
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button variant="ghost" size="sm">
                            <MoreVertical className="h-4 w-4" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent>
                        <DropdownMenuItem onClick={() => deleteChecklist()}>
                            <Trash className="h-4 w-4 mr-2" />
                            Supprimer
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>

            <Progress value={progress} />

            <div className="space-y-2">
                {checklist.items.map(item => (
                    <div key={item.id} className="flex items-center gap-2">
                        <Checkbox
                            checked={item.completed}
                            onCheckedChange={() => toggleItem(item.id)}
                        />
                        <span className={item.completed ? 'line-through text-gray-500' : ''}>
                            {item.content}
                        </span>
                        <Button
                            variant="ghost"
                            size="sm"
                            className="ml-auto h-6 w-6 p-0"
                            onClick={() => deleteItem(item.id)}
                        >
                            <Trash className="h-3 w-3" />
                        </Button>
                    </div>
                ))}
            </div>

            {isAddingItem ? (
                <div className="space-y-2">
                    <Input
                        value={newItemText}
                        onChange={(e) => setNewItemText(e.target.value)}
                        placeholder="Ajouter un élément..."
                        autoFocus
                    />
                    <div className="flex gap-2">
                        <Button
                            onClick={() => addItem()}
                            disabled={!newItemText.trim()}
                        >
                            Ajouter
                        </Button>
                        <Button
                            variant="outline"
                            onClick={() => {
                                setNewItemText('');
                                setIsAddingItem(false);
                            }}
                        >
                            Annuler
                        </Button>
                    </div>
                </div>
            ) : (
                <Button
                    variant="outline"
                    size="sm"
                    className="w-full"
                    onClick={() => setIsAddingItem(true)}
                >
                    <Plus className="h-4 w-4 mr-2" />
                    Ajouter un élément
                </Button>
            )}
        </div>
    );
} 