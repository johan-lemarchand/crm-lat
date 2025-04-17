import { Button } from '@/components/ui/button';
import { Calendar } from '@/components/ui/calendar';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { format } from 'date-fns';
import { CalendarIcon } from 'lucide-react';

interface DueDateProps {
    cardId: number;
    columnId: number;
    currentDueDate: string | null;
}

export function DueDate({ cardId, columnId, currentDueDate }: DueDateProps) {
    const queryClient = useQueryClient();

    const { mutate: updateDueDate } = useMutation({
        mutationFn: async (date: Date | null) => {
            await fetch(`${import.meta.env.VITE_API_URL}/api/columns/${columnId}/cards/${cardId}`, {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ dueDate: date?.toISOString() || null })
            });
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['board'] });
        }
    });

    return (
        <div className="flex items-center gap-2">
            <Popover>
                <PopoverTrigger asChild>
                    <Button variant="outline" className="w-[240px] justify-start text-left font-normal">
                        <CalendarIcon className="mr-2 h-4 w-4" />
                        {currentDueDate ? (
                            format(new Date(currentDueDate), 'dd MMMM yyyy')
                        ) : (
                            <span>Choisir une date</span>
                        )}
                    </Button>
                </PopoverTrigger>
                <PopoverContent className="w-auto p-0" align="start">
                    <Calendar
                        mode="single"
                        selected={currentDueDate ? new Date(currentDueDate) : undefined}
                        onSelect={(date) => updateDueDate(date)}
                        initialFocus
                    />
                </PopoverContent>
            </Popover>
            {currentDueDate && (
                <Button 
                    variant="ghost" 
                    size="sm"
                    onClick={() => updateDueDate(null)}
                >
                    Supprimer
                </Button>
            )}
        </div>
    );
} 