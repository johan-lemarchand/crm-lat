import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Badge } from '@/components/ui/badge';
import { Search, X } from 'lucide-react';
import type { Label } from '@/types/kanban';
import { Calendar } from '@/components/ui/calendar';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { format } from 'date-fns';
import { CalendarIcon } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

interface BoardFiltersProps {
    labels: Label[];
    selectedLabels: number[];
    searchTerm: string;
    onLabelSelect: (labelId: number) => void;
    onSearch: (term: string) => void;
    dueDateFilter: Date | undefined;
    onDueDateSelect: (date: Date | undefined) => void;
}

export function BoardFilters({
    labels,
    selectedLabels,
    searchTerm,
    onLabelSelect,
    onSearch,
    dueDateFilter,
    onDueDateSelect
}: BoardFiltersProps) {
    return (
        <div className="flex gap-4 mb-4">
            <div className="relative flex-1">
                <Search className="absolute left-2 top-2.5 h-4 w-4 text-muted-foreground" />
                <Input
                    placeholder="Rechercher une carte..."
                    value={searchTerm}
                    onChange={(e) => onSearch(e.target.value)}
                    className="pl-8"
                />
            </div>
            <Select
                value=""
                onValueChange={(value) => onLabelSelect(parseInt(value))}
            >
                <SelectTrigger className="w-[200px]">
                    <SelectValue placeholder="Filtrer par label" />
                </SelectTrigger>
                <SelectContent>
                    {labels.map(label => (
                        <SelectItem key={label.id} value={label.id.toString()}>
                            <div className="flex items-center gap-2">
                                <div
                                    className="w-3 h-3 rounded-full"
                                    style={{ backgroundColor: label.color }}
                                />
                                {label.name}
                            </div>
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
            <div className="flex gap-2">
                {selectedLabels.map(labelId => {
                    const label = labels.find(l => l.id === labelId);
                    if (!label) return null;
                    return (
                        <Badge
                            key={label.id}
                            variant="secondary"
                            className="gap-1"
                        >
                            <div
                                className="w-2 h-2 rounded-full"
                                style={{ backgroundColor: label.color }}
                            />
                            {label.name}
                            <X
                                className="h-3 w-3 cursor-pointer"
                                onClick={() => onLabelSelect(labelId)}
                            />
                        </Badge>
                    );
                })}
            </div>
            <Popover>
                <PopoverTrigger asChild>
                    <Button
                        variant="outline"
                        className={cn(
                            "justify-start text-left font-normal",
                            !dueDateFilter && "text-muted-foreground"
                        )}
                    >
                        <CalendarIcon className="mr-2 h-4 w-4" />
                        {dueDateFilter ? format(dueDateFilter, "dd MMMM yyyy") : "Filtrer par date"}
                    </Button>
                </PopoverTrigger>
                <PopoverContent className="w-auto p-0" align="start">
                    <Calendar
                        mode="single"
                        selected={dueDateFilter}
                        onSelect={onDueDateSelect}
                    />
                </PopoverContent>
            </Popover>
            {dueDateFilter && (
                <Button
                    size="icon"
                    variant="ghost"
                    onClick={() => onDueDateSelect(undefined)}
                >
                    <X className="h-4 w-4" />
                </Button>
            )}
        </div>
    );
} 