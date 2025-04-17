import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Button } from '@/components/ui/button';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { useToast } from '@/components/ui/use-toast';
import { Trash2 } from 'lucide-react';
import { Toaster } from "@/components/ui/toaster";
import { useState, useEffect } from 'react';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from "@/components/ui/dialog";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Label } from "@/components/ui/label";
import ReactMarkdown from 'react-markdown';

interface SystemLogs {
    php: {
        content: string;
        size: string;
    };
    apache: {
        content: string;
        size: string;
    };
    db: {
        size: string;
        total_logs: number;
        commands: Array<{
            id: number;
            name: string;
            scriptName: string;
            executionCount: number;
            apiLogsCount: number;
            resumeCount: number;
            size: string;
            details: {
                execution: string;
                api: string;
                resume: string;
            };
        }>;
    };
}

interface DbCommand {
    id: number;
    name: string;
    scriptName: string;
    executionCount: number;
    apiLogsCount: number;
    resumeCount: number;
    size: string;
    details: {
        execution: string;
        api: string;
        resume: string;
    };
}

interface ChangelogResponse {
    content: string;
}

export default function Settings() {
    const [selectedTab, setSelectedTab] = useState("php");
    const [clearDialogOpen, setClearDialogOpen] = useState(false);
    const [selectedCommand, setSelectedCommand] = useState<string>("all");
    const [selectedLogType, setSelectedLogType] = useState<string>("all");
    const [selectedScriptLogType, setSelectedScriptLogType] = useState<string>("all");
    const [availableCommands, setAvailableCommands] = useState<DbCommand[]>([]);
    const { toast } = useToast();
    const queryClient = useQueryClient();

    const { data: logs } = useQuery<SystemLogs>({
        queryKey: ['systemLogs'],
        queryFn: () => fetch(`${import.meta.env.VITE_API_URL}/api/settings/logs`).then(res => res.json()),
    });

    const { data: commands } = useQuery<{ commands: DbCommand[] }>({
        queryKey: ['dbCommands'],
        queryFn: () => fetch(`${import.meta.env.VITE_API_URL}/api/settings/logs/db/commands`).then(res => res.json()),
        enabled: clearDialogOpen && selectedTab === 'db',
    });

    const { data: changelog } = useQuery<ChangelogResponse>({
        queryKey: ['changelog'],
        queryFn: () => fetch(`${import.meta.env.VITE_API_URL}/api/settings/changelog`).then(res => res.json()),
    });

    useEffect(() => {
        if (logs?.db?.commands) {
            setAvailableCommands(logs.db.commands);
        }
    }, [logs]);

    useEffect(() => {
        if (commands?.commands) {
            setAvailableCommands(commands.commands);
        }
    }, [commands]);

    const clearLogs = async (type: string) => {
        try {
            let url = `${import.meta.env.VITE_API_URL}/api/settings/logs/${type}`;
            if (type === 'db') {
                const params = new URLSearchParams();
                if (selectedLogType === 'script' && selectedCommand !== 'all') {
                    params.append('commandId', selectedCommand);
                    params.append('logType', selectedScriptLogType);
                } else if (selectedLogType !== 'all' && selectedLogType !== 'script') {
                    params.append('logType', selectedLogType);
                }
                if (params.toString()) {
                    url += `?${params.toString()}`;
                }
            }
            
            await fetch(url, { method: 'DELETE' });
            toast({
                variant: "success",
                title: "Succès",
                description: "Les logs ont été vidés avec succès",
            });
            setClearDialogOpen(false);
            
            await queryClient.invalidateQueries({ queryKey: ['systemLogs'] });
            if (type === 'db') {
                await queryClient.invalidateQueries({ queryKey: ['dbCommands'] });
            }
        } catch (error) {
            toast({
                title: "Erreur",
                description: "Une erreur est survenue lors de la suppression des logs",
                variant: "destructive",
            });
        }
    };

    const handleClearClick = (type: string) => {
        setSelectedTab(type);
        setClearDialogOpen(true);
    };

    if (!logs) return <div>Chargement...</div>;

    return (
        <div className="container mx-auto py-6">
            <Toaster />
            <Card>
                <CardHeader>
                    <CardTitle>Paramètres système</CardTitle>
                </CardHeader>
                <CardContent>
                    <Tabs defaultValue="php" className="w-full">
                        <TabsList>
                            <TabsTrigger value="php">Logs PHP</TabsTrigger>
                            <TabsTrigger value="apache">Logs Apache</TabsTrigger>
                            <TabsTrigger value="db">Logs Scripts</TabsTrigger>
                            <TabsTrigger value="changelog">Changelog</TabsTrigger>
                        </TabsList>
                        <TabsContent value="php" className="mt-4">
                            <Card>
                                <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                    <div>
                                        <CardTitle className="text-sm font-medium">Logs PHP</CardTitle>
                                        <p className="text-sm text-muted-foreground">Taille: {logs?.php?.size || '0 KB'}</p>
                                    </div>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() => handleClearClick('php')}
                                    >
                                        <Trash2 className="h-4 w-4" />
                                        <span className="ml-2">Vider les logs</span>
                                    </Button>
                                </CardHeader>
                                <CardContent>
                                    <ScrollArea className="h-[600px] w-full rounded-md border p-4">
                                        <pre className="whitespace-pre-wrap font-mono text-sm text-left">
                                            {logs?.php?.content || 'Aucun log disponible'}
                                        </pre>
                                    </ScrollArea>
                                </CardContent>
                            </Card>
                        </TabsContent>
                        <TabsContent value="apache" className="mt-4">
                            <Card>
                                <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                    <div>
                                        <CardTitle className="text-sm font-medium">Logs Apache</CardTitle>
                                        <p className="text-sm text-muted-foreground">Taille: {logs?.apache?.size || '0 KB'}</p>
                                    </div>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() => handleClearClick('apache')}
                                    >
                                        <Trash2 className="h-4 w-4" />
                                        <span className="ml-2">Vider les logs</span>
                                    </Button>
                                </CardHeader>
                                <CardContent>
                                    <ScrollArea className="h-[600px] w-full rounded-md border p-4">
                                        <pre className="whitespace-pre-wrap font-mono text-sm text-left">
                                            {logs?.apache?.content || 'Aucun log disponible'}
                                        </pre>
                                    </ScrollArea>
                                </CardContent>
                            </Card>
                        </TabsContent>
                        <TabsContent value="db" className="mt-4">
                            <div className="space-y-4">
                                <div className="flex justify-between items-center">
                                    <div>
                                        <p>Taille totale : {logs?.db?.size || '0 KB'}</p>
                                        <p>Nombre total de logs : {logs?.db?.total_logs || 0}</p>
                                    </div>
                                    <Button variant="destructive" onClick={() => handleClearClick('db')}>
                                        Vider les logs
                                    </Button>
                                </div>
                                <p className="text-sm text-muted-foreground">
                                    Cette section contient les logs de tous les scripts exécutés. La suppression des logs libérera de l'espace dans la base de données.
                                </p>
                                <div className="mt-8">
                                    <h3 className="text-lg font-medium mb-4">Détails par script</h3>
                                    {logs?.db?.commands?.map((command) => (
                                        <div key={command.id} className="flex justify-between items-center py-2 border-b">
                                            <div>
                                                <p className="font-medium">{command.name}:{command.scriptName}</p>
                                                <p className="text-sm text-muted-foreground">
                                                    Exécutions: {command.executionCount} • API: {command.apiLogsCount} • Résumés: {command.resumeCount}
                                                </p>
                                            </div>
                                            <div className="text-right">
                                                <p className="font-medium">{command.size}</p>
                                                <p className="text-sm text-muted-foreground">
                                                    Exécutions: {command.details.execution} • API: {command.details.api} • Résumés: {command.details.resume}
                                                </p>
                                            </div>
                                        </div>
                                    )) || <p>Aucune commande disponible</p>}
                                </div>
                            </div>
                        </TabsContent>
                        <TabsContent value="changelog" className="mt-4">
                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-sm font-medium">Historique des versions</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <ScrollArea className="h-[600px] w-full rounded-md border p-4">
                                        <div className="prose prose-sm dark:prose-invert max-w-none text-left">
                                            <ReactMarkdown>
                                                {changelog?.content || '# Changelog\n\nAucun historique disponible.'}
                                            </ReactMarkdown>
                                        </div>
                                    </ScrollArea>
                                </CardContent>
                            </Card>
                        </TabsContent>
                    </Tabs>
                </CardContent>
            </Card>

            <Dialog open={clearDialogOpen} onOpenChange={setClearDialogOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Vider les logs</DialogTitle>
                    </DialogHeader>
                    {selectedTab === 'db' ? (
                        <>
                            <div className="grid gap-4 py-4">
                                <div className="space-y-4">
                                    <div className="space-y-2">
                                        <Label>Type de logs à vider</Label>
                                        <Select value={selectedLogType} onValueChange={setSelectedLogType}>
                                            <SelectTrigger>
                                                <SelectValue placeholder="Sélectionnez un type" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="all">Tous les logs</SelectItem>
                                                <SelectItem value="execution">Logs d'exécution uniquement</SelectItem>
                                                <SelectItem value="api">Logs uniquement</SelectItem>
                                                <SelectItem value="resume">Résumés uniquement</SelectItem>
                                                <SelectItem value="script">Logs d'un script spécifique</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>

                                    {selectedLogType === 'script' && (
                                        <>
                                            <div className="space-y-2">
                                                <Label>Sélectionnez le script</Label>
                                                <Select value={selectedCommand} onValueChange={setSelectedCommand}>
                                                    <SelectTrigger>
                                                        <SelectValue placeholder="Sélectionnez un script" />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        {availableCommands && availableCommands.length > 0 ? (
                                                            availableCommands.map((command) => (
                                                                <SelectItem key={command.id} value={command.id.toString()}>
                                                                    {command.name}:{command.scriptName} ({command.executionCount} exécutions, {command.apiLogsCount} logs, {command.resumeCount} résumés)
                                                                </SelectItem>
                                                            ))
                                                        ) : (
                                                            <SelectItem value="" disabled>Aucune commande disponible</SelectItem>
                                                        )}
                                                    </SelectContent>
                                                </Select>
                                            </div>
                                            {selectedCommand !== 'all' && (
                                                <div className="space-y-2">
                                                    <Label>Type de logs à supprimer pour ce script</Label>
                                                    <Select value={selectedScriptLogType} onValueChange={setSelectedScriptLogType}>
                                                        <SelectTrigger>
                                                            <SelectValue placeholder="Sélectionnez un type" />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            <SelectItem value="all">Tous les logs</SelectItem>
                                                            <SelectItem value="execution">Logs d'exécution uniquement</SelectItem>
                                                            <SelectItem value="api">Logs uniquement</SelectItem>
                                                            <SelectItem value="resume">Résumés uniquement</SelectItem>
                                                        </SelectContent>
                                                    </Select>
                                                </div>
                                            )}
                                        </>
                                    )}
                                </div>
                            </div>
                            <DialogFooter>
                                <Button variant="outline" onClick={() => setClearDialogOpen(false)}>
                                    Annuler
                                </Button>
                                <Button variant="destructive" onClick={() => clearLogs(selectedTab)}>
                                    Vider
                                </Button>
                            </DialogFooter>
                        </>
                    ) : (
                        <>
                            <div className="py-4">
                                <p>Attention : Cette action va supprimer tous les logs {selectedTab === 'php' ? 'PHP' : 'Apache'}. Voulez-vous continuer ?</p>
                            </div>
                            <DialogFooter>
                                <Button variant="outline" onClick={() => setClearDialogOpen(false)}>
                                    Annuler
                                </Button>
                                <Button variant="destructive" onClick={() => clearLogs(selectedTab)}>
                                    OK
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </DialogContent>
            </Dialog>
        </div>
    );
} 