import { ScrollArea } from "@/components/ui/scroll-area";
import { JsonFormatter } from "../JsonFormatter";

interface LogContentProps {
  type: "resume" | "output" | "error";
  content: string | null;
  commandId?: number;
  exportType?: 'all' | 'api' | 'history';
  startDate?: Date;
  endDate?: Date;
}

export function LogContent({ 
  type, 
  content,
  commandId,
  exportType,
  startDate,
  endDate 
}: LogContentProps) {
  if (!content) {
    return (
      <div className="text-muted-foreground p-4">
        {`Aucun${type === "error" ? "e erreur" : "e sortie"}`}
      </div>
    );
  }

  if (type === "output" && content.includes('{')) {
    return (
      <JsonFormatter 
        data={content} 
        maxHeight="400px"
        commandId={commandId}
        exportType={exportType}
        startDate={startDate}
        endDate={endDate}
      />
    );
  }

  return (
    <ScrollArea className="h-[400px]">
      <div 
        className={`whitespace-pre-wrap font-mono text-sm p-4 text-left ${
          type === "error" ? "text-red-500" : ""
        }`}
      >
        {content}
      </div>
    </ScrollArea>
  );
} 