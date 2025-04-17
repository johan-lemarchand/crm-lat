import { Button } from "@/components/ui/button";
import { Trash2 } from "lucide-react";
import { format } from "date-fns";

interface LogHeaderProps {
  commandName: string;
  scriptName: string;
  startDate?: Date;
  endDate?: Date;
  onDeleteClick: () => void;
}

export function LogHeader({ commandName, scriptName, startDate, endDate, onDeleteClick }: LogHeaderProps) {
  const getPeriodText = () => {
    if (!startDate || !endDate) return null;
    
    const totalDays = Math.floor((endDate.getTime() - startDate.getTime()) / (1000 * 60 * 60 * 24)) + 1;
    
    if (totalDays >= 365) {
      const years = Math.floor(totalDays / 365);
      const remainingDays = totalDays % 365;
      const months = Math.floor(remainingDays / 30);
      
      let period = `${years} an${years > 1 ? 's' : ''}`;
      if (months > 0) period += ` et ${months} mois`;
      return period;
    } 
    
    if (totalDays >= 30) {
      const months = Math.floor(totalDays / 30);
      const remainingDays = totalDays % 30;
      
      let period = `${months} mois`;
      if (remainingDays > 0) period += ` et ${remainingDays} jour${remainingDays > 1 ? 's' : ''}`;
      return period;
    }
    
    return `${totalDays} jour${totalDays > 1 ? 's' : ''}`;
  };

  return (
    <div className="flex justify-between items-center mb-6">
      <h1 className="text-2xl font-bold">
        Logs du script {commandName}:{scriptName}
      </h1>
      <div className="flex items-center gap-4">
        {startDate && endDate && (
          <span className="text-sm text-muted-foreground">
            Période de log existante ({getPeriodText()}) du {format(startDate, "dd/MM/yyyy")} - {format(endDate, "dd/MM/yyyy")}
          </span>
        )}
        <Button variant="destructive" onClick={onDeleteClick}>
          <Trash2 className="mr-2 h-4 w-4" />
          Vider les logs
        </Button>
      </div>
    </div>
  );
} 