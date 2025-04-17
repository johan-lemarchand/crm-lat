import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from "@/components/ui/badge";
import { format } from "date-fns";
import { useState } from 'react';
import { LogContent } from './LogContent';
import { LogResume } from './LogResume';
import { ApiLogViewer } from './ApiLogViewer';
import { Log } from '@/types/logs';
import { useExport } from '@/hooks/useExport';
import { Button } from "@/components/ui/button";
import { Download } from "lucide-react";
import { ExportDialog } from '@/components/ExportDialog';
import { ExportFormat } from '@/components/ExportButton';
import { CurrencyFiles } from "./CurrencyFiles";

interface LogTabsProps {
    logs: Log[];
    commandId: number;
    scriptName: string;
}

export function LogTabs({ logs, commandId, scriptName }: LogTabsProps) {
    const [selectedTab, setSelectedTab] = useState('latest');
    const [isExportDialogOpen, setIsExportDialogOpen] = useState(false);

    // Vérifier si c'est la commande Wavesoft:currency
    const isCurrencyCommand = scriptName === 'currency';
    // Vérifier si c'est la commande Wavesoft:delock_coupon
    const isDelockCommand = scriptName === 'delock_coupon';

    if (!commandId) {
        return (
            <div className="text-center p-8 text-muted-foreground">
                Aucun identifiant de commande fourni
            </div>
        );
    }

    const latestLog = logs[0];
    if (!latestLog) {
        return (
            <div className="text-center p-8 text-muted-foreground">
                Aucun log disponible
            </div>
        );
    }

    const { handleExport } = useExport({ 
        commandId,
        type: selectedTab === 'api' ? 'api' : selectedTab === 'history' ? 'history' : 'all'
    });

    const onExport = async (format: ExportFormat, options: { selectedDates: string[]; exportType: 'resume' | 'requests' | 'responses' }) => {
        try {
            await handleExport(format, options);
        } catch (error) {
            console.error('Export failed:', error);
        }
    };

    // Extraire les dates d'exécution uniques
    const executionDates = [...new Set(logs.map(log => log.startedAt))].sort().reverse();
    
    // Extraire les dates des appels API uniques
    const apiDates = [...new Set(
        logs.flatMap(log => 
            log.apiLogs?.map(apiLog => apiLog.createdAt) || []
        )
    )].sort().reverse();

    return (
        <Tabs 
            defaultValue="latest" 
            value={selectedTab}
            onValueChange={setSelectedTab}
            className="w-full"
        >
            <div className="flex items-center justify-between mb-4">
                <TabsList>
                    <TabsTrigger value="latest">Dernière exécution</TabsTrigger>
                    <TabsTrigger value="history">Historique</TabsTrigger>
                    {!isDelockCommand && <TabsTrigger value="api">Logs</TabsTrigger>}
                    {isCurrencyCommand && (
                        <TabsTrigger value="currency">Fichiers CSV</TabsTrigger>
                    )}
                </TabsList>

                {!isDelockCommand && (
                    <div className="flex items-center gap-2">
                        <Button 
                            variant="outline" 
                            onClick={() => setIsExportDialogOpen(true)}
                        >
                            <Download className="mr-2 h-4 w-4" />
                            Exporter
                        </Button>
                    </div>
                )}
            </div>

            <TabsContent value="latest">
                <Card>
                    <CardHeader>
                        <div className="flex justify-between items-center">
                            <div>
                                <CardTitle>Dernière exécution</CardTitle>
                                <p className="text-sm text-muted-foreground">
                                    Démarré le {format(new Date(latestLog.startedAt), 'dd/MM/yyyy HH:mm:ss')}
                                    {latestLog.finishedAt && ` - Terminé le ${format(new Date(latestLog.finishedAt), 'dd/MM/yyyy HH:mm:ss')}`}
                                </p>
                            </div>
                            <Badge variant={latestLog.status === 'success' ? 'success' : 'destructive'}>
                                {latestLog.status}
                            </Badge>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <Tabs defaultValue="resume" className="w-full">
                            <TabsList className="w-full">
                                <TabsTrigger value="resume" className="flex-1">Résumé</TabsTrigger>
                                <TabsTrigger value="output" className="flex-1">Sortie</TabsTrigger>
                                <TabsTrigger value="error" className="flex-1">Erreur</TabsTrigger>
                            </TabsList>
                            <TabsContent value="resume">
                                <LogResume resume={latestLog.resume} startedAt={latestLog.startedAt} status={latestLog.status} />
                            </TabsContent>
                            <TabsContent value="output">
                                <LogContent type="output" content={latestLog.output} />
                            </TabsContent>
                            <TabsContent value="error">
                                <LogContent type="error" content={latestLog.error} />
                            </TabsContent>
                        </Tabs>
                    </CardContent>
                </Card>
            </TabsContent>

            <TabsContent value="history">
                <div className="space-y-4">
                    {logs.slice(1).map((log) => (
                        <Card key={log.id}>
                            <CardHeader>
                                <div className="flex justify-between items-center">
                                    <div>
                                        <CardTitle>Exécution du {format(new Date(log.startedAt), 'dd/MM/yyyy HH:mm:ss')}</CardTitle>
                                    </div>
                                    <Badge variant={log.status === 'success' ? 'success' : 'destructive'}>
                                        {log.status}
                                    </Badge>
                                </div>
                            </CardHeader>
                            <CardContent>
                                <LogResume resume={log.resume} startedAt={log.startedAt} status={log.status} />
                            </CardContent>
                        </Card>
                    ))}
                </div>
            </TabsContent>

            {!isDelockCommand && (
                <TabsContent value="api">
                    <ApiLogViewer apiLogs={logs.flatMap(log => log.apiLogs || [])} />
                </TabsContent>
            )}

            {isCurrencyCommand && (
                <TabsContent value="currency">
                    <CurrencyFiles commandId={commandId} />
                </TabsContent>
            )}

            {!isDelockCommand && (
                <ExportDialog
                    open={isExportDialogOpen}
                    onOpenChange={setIsExportDialogOpen}
                    onExport={onExport}
                    executionDates={executionDates}
                    apiDates={apiDates}
                />
            )}
        </Tabs>
    );
} 