import {useRef, useEffect, useState} from 'react';
import {Dialog, DialogContent, DialogTitle, DialogDescription} from '@/components/ui/dialog';

interface CommandTerminalProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    executionId: number | null;
    output: string;
    errorOutput: string;
}

export function CommandTerminal({ open, onOpenChange, executionId, output, errorOutput }: CommandTerminalProps) {
    const terminalRef = useRef<HTMLDivElement>(null);
    const [processedLines, setProcessedLines] = useState<string[]>([]);
    const processedMessages = useRef(new Set<string>());

    useEffect(() => {
        if (!output) {
            setProcessedLines([]);
            processedMessages.current.clear();
            return;
        }

        try {
            // Traiter les données SSE
            const lines = output.split('\n');
            
            lines.forEach(line => {
                const trimmedLine = line.trim();
                
                if (!trimmedLine) return;

                if (trimmedLine.startsWith('data: ')) {
                    const jsonStr = trimmedLine.substring(6); // Enlever le préfixe 'data: '
                    
                    try {
                        const data = JSON.parse(jsonStr);
                        
                        // Si c'est un message qu'on a déjà traité, on le saute
                        const messageKey = JSON.stringify(data);
                        if (processedMessages.current.has(messageKey)) {
                            return;
                        }
                        processedMessages.current.add(messageKey);

                        if (data.output) {
                            // Ajouter directement chaque ligne au fur et à mesure
                            const lines = data.output.split(/\r?\n/);
                            lines.forEach((line: string) => {
                                if (line.trim()) {
                                    setProcessedLines(prev => [...prev, line]);
                                }
                            });
                        } else if (data.message) {
                            setProcessedLines(prev => [...prev, data.message]);
                        } else if (data.status === 'error') {
                            setProcessedLines(prev => [...prev, `Erreur: ${data.message || 'Une erreur est survenue'}`]);
                        }
                    } catch (parseError) {
                        if (!processedMessages.current.has(jsonStr)) {
                            processedMessages.current.add(jsonStr);
                            setProcessedLines(prev => [...prev, jsonStr]);
                        }
                    }
                } else {
                    if (!processedMessages.current.has(trimmedLine)) {
                        processedMessages.current.add(trimmedLine);
                        setProcessedLines(prev => [...prev, trimmedLine]);
                    }
                }
            });
        } catch (e) {
            setProcessedLines(prev => [...prev, output]);
        }
    }, [output]);

    useEffect(() => {
        if (terminalRef.current && open) {
            terminalRef.current.scrollTop = terminalRef.current.scrollHeight;
        }
    }, [processedLines, open]);

    const formatOutput = (lines: string[]) => {
        if (!lines.length) return null;
        
        return lines.map((line, index) => {
            // Formater les lignes de log
            if (line.match(/^\d{2}:\d{2}:\d{2}\s+(DEBUG|INFO|ERROR|WARNING)/)) {
                const [time, level, ...rest] = line.split(/\s+/);
                const message = rest.join(' ');
                
                const levelColors = {
                    DEBUG: 'text-gray-400',
                    INFO: 'text-blue-400',
                    WARNING: 'text-yellow-400',
                    ERROR: 'text-red-400'
                };

                return (
                    <div key={index} className="flex items-start gap-2">
                        <span className="text-gray-500 font-mono">{time}</span>
                        <span className={`${levelColors[level as keyof typeof levelColors]} w-16`}>
                            {level}
                        </span>
                        <span className="text-gray-300 whitespace-pre-wrap break-all">{message}</span>
                    </div>
                );
            }

            // Formater les lignes de progression
            if (line.match(/^\s*\d+\/\d+\s*\[[\s=\->]+\]\s*\d+%/)) {
                const parts = line.match(/^(\d+\/\d+)\s*\[([\s=\->]+)\]\s*(\d+%)\s*(.*?)$/);
                if (parts) {
                    const [, count, progress, percentage, details] = parts;
                    return (
                        <div key={index} className="flex items-center gap-2 text-cyan-400">
                            <span className="w-16 text-right">{count.trim()}</span>
                            <span className="font-bold">[</span>
                            <span className="font-bold min-w-[200px]">{progress}</span>
                            <span className="font-bold">]</span>
                            <span className="text-cyan-300">{percentage.trim()}</span>
                            {details && <span className="text-cyan-200">{details.trim()}</span>}
                        </div>
                    );
                }
            }

            // Formater les messages OK
            if (line.includes('[OK]')) {
                return (
                    <div key={index} className="text-green-400 font-bold py-1">
                        {line}
                    </div>
                );
            }

            // Ligne par défaut
            return (
                <div key={index} className="text-gray-300 py-0.5 whitespace-pre-wrap break-all">
                    {line}
                </div>
            );
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-[800px] max-h-[80vh] p-0 flex flex-col">
                <DialogTitle className="p-4 border-b">Terminal de sortie de commande</DialogTitle>
                <DialogDescription className="sr-only">
                    Affichage de la sortie de la commande #{executionId}
                </DialogDescription>
                <div className="flex flex-col h-full min-h-0">
                    <div className="bg-black text-white p-2 border-b border-gray-800 flex-none">
                        <span className="text-sm font-mono">
                            Exécution #{executionId}
                        </span>
                    </div>
                    <div 
                        ref={terminalRef}
                        className="flex-1 bg-black p-4 font-mono text-sm overflow-y-auto min-h-0"
                        style={{
                            scrollBehavior: 'smooth',
                            maxHeight: 'calc(80vh - 120px)'
                        }}
                    >
                        {formatOutput(processedLines)}
                        {errorOutput && (
                            <div className="text-red-400 mt-2">
                                {errorOutput}
                            </div>
                        )}
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    );
}
