import { Button } from "@/components/ui/button";
import { Calendar } from "@/components/ui/calendar";
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from "@/components/ui/dialog";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import { RadioGroup, RadioGroupItem } from "@/components/ui/radio-group";
import { Label } from "@/components/ui/label";
import { format } from "date-fns";
import { CalendarIcon, Loader2, Trash2, History, Database } from "lucide-react";
import { cn } from "@/lib/utils";

interface DeleteLogsDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  startDate?: Date;
  endDate?: Date;
  onStartDateChange: (date?: Date) => void;
  onEndDateChange: (date?: Date) => void;
  onConfirm: () => void;
  isDeleting: boolean;
  deleteType: 'all' | 'api' | 'history';
  onDeleteTypeChange: (type: 'all' | 'api' | 'history') => void;
}

export function DeleteLogsDialog({
  open,
  onOpenChange,
  startDate,
  endDate,
  onStartDateChange,
  onEndDateChange,
  onConfirm,
  isDeleting,
  deleteType,
  onDeleteTypeChange
}: DeleteLogsDialogProps) {
  const getDeleteButtonLabel = (type: 'all' | 'api' | 'history') => {
    switch (type) {
      case 'history':
        return 'Supprimer l\'historique';
      case 'api':
        return 'Supprimer les logs';
      case 'all':
        return 'Tout supprimer';
    }
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Confirmation de suppression</DialogTitle>
        </DialogHeader>
        <div className="py-4 space-y-6">
          <RadioGroup
            value={deleteType}
            onValueChange={(value) => onDeleteTypeChange(value as 'all' | 'api' | 'history')}
            className="grid grid-cols-3 gap-4"
          >
            <div>
              <RadioGroupItem value="all" id="all" className="peer sr-only" />
              <Label
                htmlFor="all"
                className="flex flex-col items-center justify-between rounded-md border-2 border-muted bg-popover p-4 hover:bg-accent hover:text-accent-foreground peer-data-[state=checked]:border-primary [&:has([data-state=checked])]:border-primary cursor-pointer"
              >
                <Trash2 className="mb-2 h-6 w-6" />
                <span>Tout</span>
              </Label>
            </div>
            <div>
              <RadioGroupItem value="api" id="api" className="peer sr-only" />
              <Label
                htmlFor="api"
                className="flex flex-col items-center justify-between rounded-md border-2 border-muted bg-popover p-4 hover:bg-accent hover:text-accent-foreground peer-data-[state=checked]:border-primary [&:has([data-state=checked])]:border-primary cursor-pointer"
              >
                <Database className="mb-2 h-6 w-6" />
                <span>Logs</span>
              </Label>
            </div>
            <div>
              <RadioGroupItem value="history" id="history" className="peer sr-only" />
              <Label
                htmlFor="history"
                className="flex flex-col items-center justify-between rounded-md border-2 border-muted bg-popover p-4 hover:bg-accent hover:text-accent-foreground peer-data-[state=checked]:border-primary [&:has([data-state=checked])]:border-primary cursor-pointer"
              >
                <History className="mb-2 h-6 w-6" />
                <span>Historique</span>
              </Label>
            </div>
          </RadioGroup>

          <div className="grid grid-cols-2 gap-4">
            <div className="flex flex-col gap-2">
              <label>Date de début (optionnel)</label>
              <Popover>
                <PopoverTrigger asChild>
                  <Button
                    variant="outline"
                    className={cn(
                      "justify-start text-left font-normal",
                      !startDate && "text-muted-foreground"
                    )}
                  >
                    <CalendarIcon className="mr-2 h-4 w-4" />
                    {startDate ? format(startDate, "dd/MM/yyyy") : "Sélectionner"}
                  </Button>
                </PopoverTrigger>
                <PopoverContent className="w-auto p-0" align="start">
                  <Calendar
                    mode="single"
                    selected={startDate}
                    onSelect={onStartDateChange}
                    initialFocus
                  />
                </PopoverContent>
              </Popover>
            </div>
            <div className="flex flex-col gap-2">
              <label>Date de fin (optionnel)</label>
              <Popover>
                <PopoverTrigger asChild>
                  <Button
                    variant="outline"
                    className={cn(
                      "justify-start text-left font-normal",
                      !endDate && "text-muted-foreground"
                    )}
                  >
                    <CalendarIcon className="mr-2 h-4 w-4" />
                    {endDate ? format(endDate, "dd/MM/yyyy") : "Sélectionner"}
                  </Button>
                </PopoverTrigger>
                <PopoverContent className="w-auto p-0" align="end">
                  <Calendar
                    mode="single"
                    selected={endDate}
                    onSelect={onEndDateChange}
                    initialFocus
                  />
                </PopoverContent>
              </Popover>
            </div>
          </div>
        </div>
        <DialogFooter>
          <Button variant="outline" onClick={() => onOpenChange(false)}>
            Annuler
          </Button>
          <Button
            variant="destructive"
            onClick={onConfirm}
            disabled={isDeleting || (!!(startDate && !endDate) || !!(!startDate && endDate))}
          >
            {isDeleting ? (
              <>
                <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                Suppression...
              </>
            ) : (
              getDeleteButtonLabel(deleteType)
            )}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
} 