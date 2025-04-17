import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { useMutation, useQueryClient } from '@tanstack/react-query';

interface CreateCommentProps {
    cardId: number;
}

export function CreateComment({ cardId }: CreateCommentProps) {
    const [content, setContent] = useState('');
    const queryClient = useQueryClient();

    const { mutate: createComment } = useMutation({
        mutationFn: async () => {
            await fetch(`${import.meta.env.VITE_API_URL}/api/cards/${cardId}/comments`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ content })
            });
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['board'] });
            setContent('');
        }
    });

    return (
        <div className="space-y-2">
            <Textarea
                value={content}
                onChange={(e) => setContent(e.target.value)}
                placeholder="Ajouter un commentaire..."
                className="min-h-[100px]"
            />
            <Button 
                onClick={() => createComment()}
                disabled={!content.trim()}
            >
                Commenter
            </Button>
        </div>
    );
} 