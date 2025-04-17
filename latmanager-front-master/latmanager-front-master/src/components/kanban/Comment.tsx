import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { format } from 'date-fns';
import { fr } from 'date-fns/locale';
import { MoreVertical, Pencil, Trash } from 'lucide-react';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import type { Comment as CommentType } from '@/types/kanban';

interface CommentProps {
    comment: CommentType;
    cardId: number;
}

export function Comment({ comment, cardId }: CommentProps) {
    const [isEditing, setIsEditing] = useState(false);
    const [content, setContent] = useState(comment.content);
    const queryClient = useQueryClient();

    const { mutate: updateComment } = useMutation({
        mutationFn: async () => {
            await fetch(`${import.meta.env.VITE_API_URL}/api/cards/${cardId}/comments/${comment.id}`, {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ content })
            });
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['board'] });
            setIsEditing(false);
        }
    });

    const { mutate: deleteComment } = useMutation({
        mutationFn: async () => {
            await fetch(`${import.meta.env.VITE_API_URL}/api/cards/${cardId}/comments/${comment.id}`, {
                method: 'DELETE'
            });
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['board'] });
        }
    });

    if (isEditing) {
        return (
            <div className="space-y-2">
                <Textarea
                    value={content}
                    onChange={(e) => setContent(e.target.value)}
                    className="min-h-[100px]"
                    autoFocus
                />
                <div className="flex gap-2">
                    <Button 
                        onClick={() => updateComment()}
                        disabled={content === comment.content}
                    >
                        Enregistrer
                    </Button>
                    <Button 
                        variant="outline" 
                        onClick={() => {
                            setContent(comment.content);
                            setIsEditing(false);
                        }}
                    >
                        Annuler
                    </Button>
                </div>
            </div>
        );
    }

    return (
        <div className="bg-gray-50 rounded p-3">
            <div className="flex justify-between items-start">
                <div className="space-y-1">
                    <div className="flex items-center gap-2">
                        <span className="font-medium">{comment.author}</span>
                        <span className="text-sm text-gray-500">
                            {format(new Date(comment.createdAt), 'dd MMM yyyy à HH:mm', { locale: fr })}
                        </span>
                    </div>
                    <p className="text-sm">{comment.content}</p>
                </div>
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button variant="ghost" size="sm">
                            <MoreVertical className="h-4 w-4" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent>
                        <DropdownMenuItem onClick={() => setIsEditing(true)}>
                            <Pencil className="h-4 w-4 mr-2" />
                            Modifier
                        </DropdownMenuItem>
                        <DropdownMenuItem onClick={() => deleteComment()}>
                            <Trash className="h-4 w-4 mr-2" />
                            Supprimer
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>
        </div>
    );
} 