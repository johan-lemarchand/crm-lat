import { useState } from 'react';
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogFooter,
  DialogDescription,
} from "@/components/ui/dialog";
import { RadioGroup, RadioGroupItem } from "@/components/ui/radio-group";
import { ScrollArea } from "@/components/ui/scroll-area";
import { format } from "date-fns";

type ExportType = 'resume' | 'requests' | 'responses';

interface ExportDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  onExport: (format: 'csv' | 'excel' | 'json', options: ExportOptions) => Promise<void>;
  executionDates: string[];
  apiDates: string[];
}

interface ExportOptions {
  selectedDates: string[];
  exportType: ExportType;
}

export function ExportDialog({ open, onOpenChange, onExport, executionDates, apiDates }: ExportDialogProps) {
  const [selectedDates, setSelectedDates] = useState<string[]>([]);
  const [selectedFormat, setSelectedFormat] = useState<'csv' | 'excel' | 'json'>('json');
  const [exportType, setExportType] = useState<ExportType>('resume');

  const handleExportTypeChange = (value: ExportType) => {
    setExportType(value);
    setSelectedDates([]);
  };

  const availableDates = exportType === 'resume' ? executionDates : apiDates;

  const handleDateToggle = (date: string) => {
    setSelectedDates(current => 
      current.includes(date) 
        ? current.filter(d => d !== date)
        : [...current, date]
    );
  };

  const handleSelectAll = () => {
    setSelectedDates(selectedDates.length === availableDates.length ? [] : [...availableDates]);
  };

  const handleExport = async () => {
    await onExport(selectedFormat, {
      selectedDates,
      exportType
    });
    onOpenChange(false);
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-[425px]">
        <DialogHeader>
          <DialogTitle>Exporter les logs</DialogTitle>
          <DialogDescription>
            Sélectionnez les données et le format d'export souhaité
          </DialogDescription>
        </DialogHeader>
        <div className="grid gap-4 py-4">
          <div className="flex flex-col gap-2">
            <label className="text-sm font-medium">Type de données</label>
            <RadioGroup 
              value={exportType} 
              onValueChange={handleExportTypeChange}
            >
              <div className="flex items-center space-x-2">
                <RadioGroupItem value="resume" id="resume" />
                <label htmlFor="resume">Résumé d'exécution</label>
              </div>
              <div className="flex items-center space-x-2">
                <RadioGroupItem value="requests" id="requests" />
                <label htmlFor="requests">Requêtes API</label>
              </div>
              <div className="flex items-center space-x-2">
                <RadioGroupItem value="responses" id="responses" />
                <label htmlFor="responses">Réponses API</label>
              </div>
            </RadioGroup>
          </div>

          <div className="flex flex-col gap-2">
            <div className="flex justify-between items-center">
              <label className="text-sm font-medium">
                {exportType === 'resume' ? 'Dates d\'exécution' : 'Dates des appels API'}
              </label>
              <Button 
                variant="outline" 
                size="sm"
                onClick={handleSelectAll}
              >
                {selectedDates.length === availableDates.length ? 'Tout désélectionner' : 'Tout sélectionner'}
              </Button>
            </div>
            <ScrollArea className="h-[200px] rounded-md border p-2">
              <div className="space-y-2">
                {availableDates.map(date => (
                  <div key={date} className="flex items-center space-x-2">
                    <Checkbox 
                      id={date}
                      checked={selectedDates.includes(date)}
                      onCheckedChange={() => handleDateToggle(date)}
                    />
                    <label htmlFor={date} className="text-sm">
                      {exportType === 'resume' ? 'Exécution' : 'Appel'} du {format(new Date(date), "dd/MM/yyyy HH:mm:ss")}
                    </label>
                  </div>
                ))}
              </div>
            </ScrollArea>
          </div>

          <div className="flex flex-col gap-2">
            <label className="text-sm font-medium">Format</label>
            <div className="flex gap-2">
              <Button
                variant={selectedFormat === 'csv' ? 'default' : 'outline'}
                onClick={() => setSelectedFormat('csv')}
              >
                CSV
              </Button>
              <Button
                variant={selectedFormat === 'excel' ? 'default' : 'outline'}
                onClick={() => setSelectedFormat('excel')}
              >
                Excel
              </Button>
              <Button
                variant={selectedFormat === 'json' ? 'default' : 'outline'}
                onClick={() => setSelectedFormat('json')}
              >
                JSON
              </Button>
            </div>
          </div>
        </div>
        <DialogFooter>
          <Button variant="outline" onClick={() => onOpenChange(false)}>
            Annuler
          </Button>
          <Button onClick={handleExport}>
            Exporter
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
} 