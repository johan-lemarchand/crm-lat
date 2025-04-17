import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { X } from 'lucide-react';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import type { Label as LabelType } from '@/types/kanban';

interface LabelProps {
    label: LabelType;
    cardId: number;
    isEditable?: boolean;
}

export function Label({ label, cardId, isEditable }: LabelProps) {
    const queryClient = useQueryClient();

    const { mutate: removeLabel } = useMutation({
        mutationFn: async () => {
            await fetch(`${import.meta.env.VITE_API_URL}/api/cards/${cardId}/labels/${label.id}`, {
                method: 'DELETE'
            });
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['board'] });
        }
    });

    if (!isEditable) {
        return (
            <Badge
                style={{
                    backgroundColor: label.color,
                    color: 'white'
                }}
            >
                {label.name}
            </Badge>
        );
    }

    return (
        <div className="flex items-center gap-1">
            <Badge
                style={{
                    backgroundColor: label.color,
                    color: 'white'
                }}
            >
                {label.name}
            </Badge>
            <Button
                size="sm"
                variant="ghost"
                className="h-5 w-5 p-0"
                onClick={() => removeLabel()}
            >
                <X className="h-3 w-3" />
            </Button>
        </div>
    );
}

// Fonction utilitaire pour déterminer si une couleur est claire
function isLightColor(color: string): boolean {
    const hex = color.replace('#', '');
    const r = parseInt(hex.substr(0, 2), 16);
    const g = parseInt(hex.substr(2, 2), 16);
    const b = parseInt(hex.substr(4, 2), 16);
    const brightness = ((r * 299) + (g * 587) + (b * 114)) / 1000;
    return brightness > 155;
} 