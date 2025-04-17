import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import {
    ColumnDef,
    ColumnFiltersState,
    flexRender,
    getCoreRowModel,
    getFilteredRowModel,
    getSortedRowModel,
    SortingState,
    useReactTable,
    VisibilityState,
    ColumnResizeMode,
} from '@tanstack/react-table';
import {Card, CardContent, CardHeader, CardTitle} from '@/components/ui/card';
import {Table, TableBody, TableCell, TableHead, TableHeader, TableRow} from '@/components/ui/table';
import {Input} from '@/components/ui/input';
import {Button} from '@/components/ui/button';
import {DropdownMenu, DropdownMenuContent, DropdownMenuTrigger, DropdownMenuCheckboxItem} from '@/components/ui/dropdown-menu';
import {AlertCircle, CheckCircle, List, Loader2, MinusCircle, Pencil, PlayIcon, Plus, XCircle, ChevronDown, ChevronUp} from 'lucide-react';
import CommandForm from '@/components/CommandForm';
import {useToast} from "@/components/ui/use-toast";
import {Toaster} from "@/components/ui/toaster";
import {CommandTerminal} from '@/components/CommandTerminal';
import {ArrowDownIcon, ArrowUpIcon, CaretSortIcon} from "@radix-ui/react-icons";
import {Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle} from "@/components/ui/dialog";
import {Label} from "@/components/ui/label";
import {Badge} from "@/components/ui/badge";
import {Tooltip, TooltipContent, TooltipTrigger, TooltipProvider} from "@/components/ui/tooltip";
import {
    Command,
    CommandFormData,
    CommandOutput,
    CommandsResponse,
    CommandFormDataValue,
    CommandParameters
} from '@/types/interfaces';
import { api } from '@/lib/api';
import { OdfLogs, StepsModal, OdfLogsResponse } from '@/components/home';
import WavesoftLogs from '@/components/home/WavesoftLogs';
import {useEffect, useState} from "react";

export default function Home() {
    const [sorting, setSorting] = useState<SortingState>([]);
    const [columnFilters, setColumnFilters] = useState<ColumnFiltersState>([]);
    const [columnVisibility, setColumnVisibility] = useState<VisibilityState>(() => {
        // Charger les préférences depuis localStorage au démarrage
        const savedVisibility = localStorage.getItem('tableColumnsVisibility');
        return savedVisibility ? JSON.parse(savedVisibility) : {};
    });
    const [columnResizeMode] = useState<ColumnResizeMode>('onChange');

    // État pour la modal des étapes
    const [stepsModalOpen, setStepsModalOpen] = useState(false);
    const [selectedSteps, setSelectedSteps] = useState<any[]>([]);

    // Sauvegarder les préférences quand elles changent
    useEffect(() => {
        localStorage.setItem('tableColumnsVisibility', JSON.stringify(columnVisibility));
    }, [columnVisibility]);

    const [commandDialogOpen, setCommandDialogOpen] = useState(false);
    const [terminalOpen, setTerminalOpen] = useState(false);
    const [currentOutput, setCurrentOutput] = useState<CommandOutput>({ output: '', errorOutput: '', status: '' });
    const [currentExecutionId, setCurrentExecutionId] = useState<number | null>(null);
    const [editDialogOpen, setEditDialogOpen] = useState(false);
    const [currentApp, setCurrentApp] = useState<CommandFormData | null>(null);
    const queryClient = useQueryClient();
    const { toast } = useToast();
    const [runningCommands, setRunningCommands] = useState<number[]>([]);
    const [selectedCommandId, setSelectedCommandId] = useState<number | null>(null);
    const [showParamsDialog, setShowParamsDialog] = useState(false);
    const [commandParams, setCommandParams] = useState<CommandParameters>({});
    const { data, isLoading, error } = useQuery<CommandsResponse>({
        queryKey: ['commands'],
        queryFn: () => api.get('/api/commands'),
    });

    // Requête pour récupérer les logs ODF
    const { 
        data: odfLogsData, 
        isLoading: odfLogsLoading, 
        error: odfLogsError 
    } = useQuery<OdfLogsResponse>({
        queryKey: ['odf-logs'],
        queryFn: () => api.get('/api/odf/logs')
    });

    useEffect(() => {
        const interval = setInterval(() => {
            queryClient.invalidateQueries({ queryKey: ['commands'] });
        }, 30000);

        return () => clearInterval(interval);
    }, [queryClient]);

    const commands = data?.commands || [];
    const schedulerCommand = data?.schedulerCommand;
    const odfLogs = odfLogsData?.logs || [];

    const getSchedulerStatus = (status: string | null): 'SUCCESS' | 'ERROR' | 'WARNING' | null => {
        if (!status) return null;
        
        switch (status.toUpperCase()) {
            case 'SUCCESS':
            case 'success':
                return 'SUCCESS';
            case 'ERROR':
            case 'error':
                return 'ERROR';
            case 'WARNING':
            case 'running':
                return 'WARNING';
            default:
                return null;
        }
    };

    const updateMutation = useMutation({
        mutationFn: async ({data}: { name: string, data: CommandFormData }) => {
            const response = await fetch(`/api/commands/${data.id}`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    name: data.name,
                    scriptName: data.scriptName,
                    startTime: data.startTime,
                    endTime: data.endTime,
                    recurrence: data.recurrence,
                    active: data.active,
                    interval: data.interval,
                    attemptMax: data.attemptMax,
                    statusSendEmail: data.statusSendEmail
                })
            });
            const responseData = await response.json();
            if (!response.ok) {
                throw new Error(responseData.error || 'Failed to update command');
            }
            return responseData;
        },
        onSuccess: () => {
            queryClient.invalidateQueries({queryKey: ['commands']});
            toast({
                title: "Succès",
                description: "La commande a été mise à jour",
                variant: "success",
            });
            setEditDialogOpen(false);
            setCurrentApp(null);
        },
        onError: (error: Error) => {
            toast({
                title: "Erreur",
                description: error.message,
                variant: "destructive",
            });
        }
    });

    const deleteMutation = useMutation({
        mutationFn: async (commandId: number) => {
            const response = await fetch(`/api/commands/${commandId}`, {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                }
            });
            const responseData = await response.json();
            if (!response.ok) {
                throw new Error(responseData.error || 'Failed to delete command');
            }
            return responseData;
        },
        onSuccess: () => {
            queryClient.invalidateQueries({queryKey: ['commands']}); 
            toast({
                title: "Succès",
                description: "La commande a été supprimée",
                variant: "success",
            });
            setEditDialogOpen(false);
            setCurrentApp(null);
        },
        onError: (error: Error) => {
            toast({
                title: "Erreur",
                description: error.message,
                variant: "destructive",
            });
        }
    });

    const formatTimeFromISO = (isoTime: string | null) => {
        if (!isoTime) return null;
        try {
            const date = new Date(isoTime);
            const hours = date.getHours().toString().padStart(2, '0');
            const minutes = date.getMinutes().toString().padStart(2, '0');
            return `${hours}:${minutes}`;
            // eslint-disable-next-line @typescript-eslint/no-unused-vars
        } catch (error) {
            return null;
        }
    };

    const handleEdit = (app: Command) => {
        const formattedApp: CommandFormData = {
            id: app.id,
            name: app.name,
            scriptName: app.scriptName,
            recurrence: app.recurrence,
            startTime: formatTimeFromISO(app.startTime),
            endTime: formatTimeFromISO(app.endTime),
            active: app.active,
            interval: app.interval,
            attemptMax: app.attemptMax,
            statusSendEmail: app.statusSendEmail
        };
        setCurrentApp(formattedApp);
        setEditDialogOpen(true);
    };

    const updateCurrentApp = (field: keyof CommandFormData, value: CommandFormDataValue) => {
        setCurrentApp(prev => {
            if (!prev) return null;
            return {
                ...prev,
                [field]: value
            };
        });
    };

    const handleSave = async () => {
        if (!currentApp) return;
        try {
            await updateMutation.mutateAsync({
                name: currentApp.name,
                data: currentApp
            });
        } catch (error) {
            // Suppression du console.error
        }
    };

    const handleDelete = (commandId: number) => {
        if (window.confirm('Êtes-vous sûr de vouloir supprimer cette commande ?')) {
            deleteMutation.mutate(commandId);
        }
    };

    useEffect(() => {
        if (!commands) return;

        const stillRunning = commands
            .filter((cmd: Command) => cmd.lastStatus === 'running')
            .map((cmd: Command) => cmd.id);

        setRunningCommands(prev => {
            const prevSorted = [...prev].sort();
            const newSorted = [...stillRunning].sort();

            if (JSON.stringify(prevSorted) === JSON.stringify(newSorted)) {
                return prev;
            }
            return stillRunning;
        });
    }, [commands]);

    const columns: ColumnDef<Command>[] = [
        {
            accessorKey: 'name',
            header: ({ column }) => {
                return (
                    <Button
                        variant="ghost"
                        onClick={() => column.toggleSorting(column.getIsSorted() === "asc")}
                    >
                        Dossier
                        {column.getIsSorted() === "asc" ? (
                            <ArrowUpIcon className="ml-2 h-4 w-4" />
                        ) : column.getIsSorted() === "desc" ? (
                            <ArrowDownIcon className="ml-2 h-4 w-4" />
                        ) : (
                            <CaretSortIcon className="ml-2 h-4 w-4" />
                        )}
                    </Button>
                )
            },
            cell: ({ row }) => <div className="font-medium">{row.getValue('name')}</div>,
        },
        {
            accessorKey: 'scriptName',
            header: ({ column }) => {
                return (
                    <Button
                        variant="ghost"
                        onClick={() => column.toggleSorting(column.getIsSorted() === "asc")}
                    >
                        Script
                        {column.getIsSorted() === "asc" ? (
                            <ArrowUpIcon className="ml-2 h-4 w-4" />
                        ) : column.getIsSorted() === "desc" ? (
                            <ArrowDownIcon className="ml-2 h-4 w-4" />
                        ) : (
                            <CaretSortIcon className="ml-2 h-4 w-4" />
                        )}
                    </Button>
                )
            },
            cell: ({ row }) => {
                const scriptName = row.getValue('scriptName') as string;
                const name = row.getValue('name') as string;
                const version = data?.versions?.[scriptName] ||
                            data?.versions?.[`${name}_${scriptName}`] ||
                            data?.versions?.[`${name}${scriptName}`] ||
                            null;
                
                return (
                    <div className="flex flex-col gap-1 items-center">
                        <div>{scriptName}</div>
                        {version && (
                            <Badge variant="outline" className="w-fit">
                                v{version}
                            </Badge>
                        )}
                    </div>
                );
            },
        },
        {
            accessorKey: 'lastStatus',
            header: ({ column }) => {
                return (
                    <Button
                        variant="ghost"
                        onClick={() => column.toggleSorting(column.getIsSorted() === "asc")}
                    >
                        Dernière exécution
                        {column.getIsSorted() === "asc" ? (
                            <ArrowUpIcon className="ml-2 h-4 w-4" />
                        ) : column.getIsSorted() === "desc" ? (
                            <ArrowDownIcon className="ml-2 h-4 w-4" />
                        ) : (
                            <CaretSortIcon className="ml-2 h-4 w-4" />
                        )}
                    </Button>
                )
            },
            cell: ({ row }) => {
                const status = row.getValue('lastStatus') as string;
                const date = row.original.lastExecutionDate;
                const isRunning = runningCommands.includes(row.original.id);

                if (isRunning) {
                    return (
                        <div className="flex items-center gap-2">
                            <Loader2 className="h-4 w-4 animate-spin" />
                            <span>En cours...</span>
                        </div>
                    );
                }

                return (
                    <div className="flex items-center gap-2">
                        {getSchedulerStatus(status) === 'SUCCESS' && <CheckCircle className="h-4 w-4 text-green-500" />}
                        {getSchedulerStatus(status) === 'ERROR' && <XCircle className="h-4 w-4 text-red-500" />}
                        {getSchedulerStatus(status) === 'WARNING' && <AlertCircle className="h-4 w-4 text-yellow-500" />}
                        {!status && <MinusCircle className="h-4 w-4 text-gray-500" />}
                        <span>{date ? new Date(date).toLocaleString() : 'Jamais'}</span>
                    </div>
                );
            },
        },
        {
            accessorKey: 'nextStatus',
            header: ({ column }) => {
                return (
                    <Button
                        variant="ghost"
                        onClick={() => column.toggleSorting(column.getIsSorted() === "asc")}
                    >
                        Prochaine exécution
                        {column.getIsSorted() === "asc" ? (
                            <ArrowUpIcon className="ml-2 h-4 w-4" />
                        ) : column.getIsSorted() === "desc" ? (
                            <ArrowDownIcon className="ml-2 h-4 w-4" />
                        ) : (
                            <CaretSortIcon className="ml-2 h-4 w-4" />
                        )}
                    </Button>
                )
            },
            cell: ({ row }) => {
                const date = row.original.nextExecutionDate;
                return (
                    <div className="flex items-center gap-2">
                        <span>{date ? new Date(date).toLocaleString() : 'Jamais'}</span>
                    </div>
                );
            },
        },
        {
            accessorKey: 'schedulerStatus',
            header: 'Statut scheduler',
            cell: ({ row }) => {
                const command = row.original;
                const statusContent = {
                    Désactivée: (
                        <div className="flex flex-col">
                            <div className="flex items-center">
                                <XCircle className="mr-2 h-4 w-4 text-red-500" />
                                Désactivée
                            </div>
                        </div>
                    ),
                    Activée: (
                        <div className="flex flex-col">
                            <div className="flex items-center">
                                <CheckCircle className="mr-2 h-4 w-4 text-green-500" />
                                Activée
                            </div>
                        </div>
                    )
                };

                // eslint-disable-next-line @typescript-eslint/ban-ts-comment
                // @ts-expect-error
                return statusContent[command.statusScheduler];
            },
        },
        {
            accessorKey: 'manualExecutionDate',
            header: ({ column }) => {
                return (
                    <Button
                        variant="ghost"
                        onClick={() => column.toggleSorting(column.getIsSorted() === "asc")}
                    >
                        Date lancement manuel
                        {column.getIsSorted() === "asc" ? (
                            <ArrowUpIcon className="ml-2 h-4 w-4" />
                        ) : column.getIsSorted() === "desc" ? (
                            <ArrowDownIcon className="ml-2 h-4 w-4" />
                        ) : (
                            <CaretSortIcon className="ml-2 h-4 w-4" />
                        )}
                    </Button>
                )
            },
            cell: ({ row }) => {
                const date = row.original.manualExecutionDate;
                return (
                    <div className="flex items-center gap-2">
                        <span>{date ? new Date(date).toLocaleString() : 'Jamais'}</span>
                    </div>
                );
            },
        },
        {
            accessorKey: 'statusCommand',
            header: 'Statut script',
            cell: ({ row }) => {
                const status = row.getValue('lastStatus') as 'success' | 'error' | null;
                
                if (!status) {
                    return (
                        <div className="flex flex-col">
                            <div className="flex items-center">
                                <MinusCircle className="mr-2 h-4 w-4 text-gray-500" />
                                Non défini
                            </div>
                        </div>
                    );
                }

                const statusContent: Record<'success' | 'error', JSX.Element> = {
                    error: (
                        <div className="flex flex-col">
                            <div className="flex items-center">
                                <XCircle className="mr-2 h-4 w-4 text-red-500" />
                                Error
                            </div>
                        </div>
                    ),
                    success: (
                        <div className="flex flex-col">
                            <div className="flex items-center">
                                <CheckCircle className="mr-2 h-4 w-4 text-green-500" />
                                Success
                            </div>
                        </div>
                    )
                };

                return statusContent[status];
            },
        },
        {
            accessorKey: 'size',
            header: ({ column }) => {
                return (
                    <Button
                        variant="ghost"
                        onClick={() => column.toggleSorting(column.getIsSorted() === "asc")}
                    >
                        Taille
                        {column.getIsSorted() === "asc" ? (
                            <ArrowUpIcon className="ml-2 h-4 w-4" />
                        ) : column.getIsSorted() === "desc" ? (
                            <ArrowDownIcon className="ml-2 h-4 w-4" />
                        ) : (
                            <CaretSortIcon className="ml-2 h-4 w-4" />
                        )}
                    </Button>
                )
            },
            cell: ({ row }) => {
                const sizeData: any = row.original.size;
                const size = typeof sizeData === 'object' && sizeData?.formatted 
                    ? sizeData.formatted 
                    : (typeof sizeData === 'string' ? sizeData : "0 B");
                const details: any = row.original.details || {};
                
                // Formatage de la période
                const periodText = details.period || "Aucune donnée";
                
                return (
                    <div className="flex flex-col gap-1">
                        <div className="font-medium">{size}</div>
                        <div className="text-xs text-muted-foreground flex flex-col">
                            <div>• Logs exécutions: {details.execution || "0 B"}</div>
                            <div>• Logs API: {details.api || "0 B"}</div>
                            <div>• Résumés: {details.resume || "0 B"}</div>
                            <div className="flex flex-col">
                                <div>• Période: {periodText}</div>
                            </div>
                        </div>
                    </div>
                );
            },
        },
        {
            accessorKey: 'id',
            header: 'Exécuter',
            cell: ({ row }) => {
                const commandId = row.getValue('id') as number;
                const scriptName = row.original.scriptName;
                const isRunning = runningCommands.includes(commandId);
                return (
                    <Button
                        size="sm"
                        variant="outline"
                        onClick={() => handleExecuteCommand(commandId, scriptName)}
                        disabled={isRunning}
                    >
                        {isRunning ? (
                            <Loader2 className="h-4 w-4 animate-spin" />
                        ) : (
                            <PlayIcon className="h-4 w-4" />
                        )}
                    </Button>
                );
            },
        },
        {
            accessorKey: 'statusSendEmail',
            header: 'Notifications',
            cell: ({ row }) => {
                const statusSendEmail = row.original.statusSendEmail;
                return (
                    <div className="flex items-center gap-2">
                        {statusSendEmail ? (
                            <div className="flex items-center">
                                <CheckCircle className="mr-2 h-4 w-4 text-green-500" />
                                Activées
                            </div>
                        ) : (
                            <div className="flex items-center">
                                <XCircle className="mr-2 h-4 w-4 text-red-500" />
                                Désactivées
                            </div>
                        )}
                    </div>
                );
            },
        },
        {
            id: 'actions',
            cell: ({ row }) => {
                return (
                    <div className="flex flex-col gap-2">
                        <Tooltip>
                            <TooltipTrigger asChild>
                                <Button 
                                    variant="ghost" 
                                    className="h-8 w-8 p-0"
                                    onClick={() => handleEdit(row.original)}
                                >
                                    <Pencil className="h-4 w-4" />
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent>
                                <p>Modifier</p>
                            </TooltipContent>
                        </Tooltip>
                        <Tooltip>
                            <TooltipTrigger asChild>
                                <Button 
                                    variant="ghost" 
                                    className="h-8 w-8 p-0"
                                    onClick={() => {
                                        window.location.href = `/logs/command/${row.original.id}`;
                                    }}
                                >
                                    <List className="h-4 w-4" />
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent>
                                <p>Voir les logs</p>
                            </TooltipContent>
                        </Tooltip>
                    </div>
                );
            },
        },
    ];
    const table = useReactTable({
        data: commands,
        columns,
        getCoreRowModel: getCoreRowModel(),
        getSortedRowModel: getSortedRowModel(),
        getFilteredRowModel: getFilteredRowModel(),
        onSortingChange: setSorting,
        onColumnFiltersChange: setColumnFilters,
        onColumnVisibilityChange: setColumnVisibility,
        columnResizeMode,
        enableColumnResizing: true,
        state: {
            sorting,
            columnFilters,
            columnVisibility,
        },
    });

    const handleExecuteCommand = async (commandId: number, scriptName: string) => {
        if (scriptName === 'activities') {
            setSelectedCommandId(commandId);
            setShowParamsDialog(true);
        } else {
            await executeCommand(commandId);
        }
    };

    const executeCommand = async (commandId: number, params: CommandParameters = {}) => {
        try {
            setRunningCommands(prev => [...prev, commandId]);
            setTerminalOpen(true);
            setCurrentOutput({ output: '', errorOutput: '', status: '' });

            const response = await fetch(`${import.meta.env.VITE_API_URL}/api/commands/${commandId}/execute`, {
                method: 'POST',
                headers: {
                    'Accept': 'text/event-stream',
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(params)
            });

            if (!response.ok) {
                throw new Error('Failed to execute command');
            }

            const reader = response.body?.getReader();
            const decoder = new TextDecoder();

            if (!reader) {
                throw new Error('No reader available');
            }

            let buffer = '';
            while (true) {
                const { done, value } = await reader.read();
                if (done) break;

                // Décode les nouvelles données et les ajoute au buffer
                buffer += decoder.decode(value, { stream: true });
                
                // Traite les lignes complètes
                const lines = buffer.split('\n');
                buffer = lines.pop() || ''; // Garde la dernière ligne potentiellement incomplète

                for (const line of lines) {
                    if (line.trim() && line.startsWith('data: ')) {
                        try {
                            const jsonStr = line.substring(5).trim();
                            const data = JSON.parse(jsonStr);

                            // Met à jour l'ID d'exécution si présent
                            if (data.id) {
                                setCurrentExecutionId(data.id);
                            }

                            // Met à jour la sortie immédiatement
                            setCurrentOutput(prev => ({
                                output: data.output ? prev.output + data.output : prev.output,
                                errorOutput: data.errorOutput ? prev.errorOutput + data.errorOutput : prev.errorOutput,
                                status: data.status || prev.status
                            }));
                        } catch (error) {
                            console.error('Error parsing SSE data:', error);
                            // En cas d'erreur de parsing, ajoute la ligne brute
                            setCurrentOutput(prev => ({
                                ...prev,
                                output: prev.output + line.substring(5).trim() + '\n'
                            }));
                        }
                    }
                }
            }

            // Traiter le reste du buffer si nécessaire
            if (buffer.trim()) {
                console.log('Remaining buffer:', buffer);
            }

            setRunningCommands(prev => prev.filter(id => id !== commandId));
        } catch (error: any) {
            console.error('Command execution error:', error);
            setRunningCommands(prev => prev.filter(id => id !== commandId));
            setCurrentOutput(prev => ({
                ...prev,
                errorOutput: prev.errorOutput + '\nErreur lors de l\'exécution de la commande: ' + error.message,
                status: 'error'
            }));
        }
    };

    // États pour contrôler l'ouverture/fermeture des sections
    const [scriptsExpanded, setScriptsExpanded] = useState(true);
    if (error) return (
        <div className="py-8">
            <Card className="border-0 shadow-none">
                <CardContent className="pt-6">
                    <div className="flex flex-col items-center justify-center gap-4 p-8">
                        <XCircle className="h-12 w-12 text-red-500" />
                        <div className="text-xl font-medium text-center">Erreur de chargement</div>
                        <div className="text-sm text-muted-foreground text-center max-w-md">
                            {error.message}
                        </div>
                        <Button 
                            onClick={() => window.location.reload()}
                            className="mt-4"
                        >
                            Réessayer
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>
    );
    if (isLoading) return <div>Chargement...</div>;

    return (
        <div className="py-8">
            <TooltipProvider>
                <div className="flex justify-between items-center mb-6">
                    <h2 className="text-2xl font-semibold">Liste des commandes</h2>
                </div>
                <Toaster />
                
                {/* Navigation Cards */}
                <div className="flex flex-wrap gap-4 mb-6">
                    <Button
                        variant="outline"
                        className="h-12 px-4 flex items-center gap-2 text-sm hover:bg-accent"
                        onClick={() => {
                            const scriptsSection = document.querySelector('#scripts-automatiques');
                            scriptsSection?.scrollIntoView({ behavior: 'smooth' });
                        }}
                    >
                        <List className="h-4 w-4 text-blue-500" />
                        <span>Scripts automatiques</span>
                    </Button>
                    
                    <Button
                        variant="outline"
                        className="h-12 px-4 flex items-center gap-2 text-sm hover:bg-accent"
                        onClick={() => {
                            const trimbleSection = document.querySelector('#api-trimble');
                            trimbleSection?.scrollIntoView({ behavior: 'smooth' });
                        }}
                    >
                        <PlayIcon className="h-4 w-4 text-green-500" />
                        <span>API TRIMBLE</span>
                    </Button>
                    
                    <Button
                        variant="outline"
                        className="h-12 px-4 flex items-center gap-2 text-sm hover:bg-accent"
                        onClick={() => {
                            const wavesoftSection = document.querySelector('#wavesoft');
                            wavesoftSection?.scrollIntoView({ behavior: 'smooth' });
                        }}
                    >
                        <CheckCircle className="h-4 w-4 text-purple-500" />
                        <span>WAVESOFT</span>
                    </Button>
                </div>

                <CommandTerminal 
                    open={terminalOpen} 
                    onOpenChange={setTerminalOpen}
                    executionId={currentExecutionId}
                    output={currentOutput.output}
                    errorOutput={currentOutput.errorOutput}
                />
                <Card className="border-0 shadow-none" id="scripts-automatiques">
                    <CardHeader className="pb-2">
                        <div className="flex justify-between items-center">
                            <div 
                                className="flex items-center gap-4 cursor-pointer" 
                                onClick={() => setScriptsExpanded(!scriptsExpanded)}
                            >
                                <CardTitle className="text-2xl font-semibold">Scripts automatiques</CardTitle>
                                <div className="text-sm text-muted-foreground flex items-center gap-2">
                                    Dernière vérification : {schedulerCommand?.lastExecutionDate ? (
                                        <>
                                            {new Date(schedulerCommand.lastExecutionDate).toLocaleString('fr-FR', {
                                                year: 'numeric',
                                                month: '2-digit',
                                                day: '2-digit',
                                                hour: '2-digit',
                                                minute: '2-digit',
                                                second: '2-digit',
                                                hour12: false
                                            })}
                                            {getSchedulerStatus(schedulerCommand.lastStatus) === 'SUCCESS' && <CheckCircle className="h-4 w-4 text-green-500" />}
                                            {getSchedulerStatus(schedulerCommand.lastStatus) === 'ERROR' && <XCircle className="h-4 w-4 text-red-500" />}
                                            {getSchedulerStatus(schedulerCommand.lastStatus) === 'WARNING' && <AlertCircle className="h-4 w-4 text-yellow-500" />}
                                        </>
                                    ) : 'Jamais'}
                                </div>
                                {scriptsExpanded ? (
                                    <ChevronUp className="h-5 w-5 text-muted-foreground" />
                                ) : (
                                    <ChevronDown className="h-5 w-5 text-muted-foreground" />
                                )}
                            </div>
                            {scriptsExpanded && (
                                <div className="flex items-center gap-2">
                                    <DropdownMenu>
                                        <DropdownMenuTrigger asChild>
                                            <Button variant="outline" size="sm" className="ml-auto">
                                                Colonnes <ChevronDown className="ml-2 h-4 w-4" />
                                            </Button>
                                        </DropdownMenuTrigger>
                                        <DropdownMenuContent align="end">
                                            {table
                                                .getAllColumns()
                                                .filter((column) => column.getCanHide())
                                                .map((column) => {
                                                    return (
                                                        <DropdownMenuCheckboxItem
                                                            key={column.id}
                                                            className="capitalize"
                                                            checked={column.getIsVisible()}
                                                            onCheckedChange={(value) =>
                                                                column.toggleVisibility(value)
                                                            }
                                                        >
                                                            {column.id === 'statusSendEmail' 
                                                                ? 'Notifications'
                                                                : column.id === 'scriptName'
                                                                ? 'Script'
                                                                : column.id === 'lastStatus'
                                                                ? 'Dernière exécution'
                                                                : column.id === 'nextStatus'
                                                                ? 'Prochaine exécution'
                                                                : column.id === 'schedulerStatus'
                                                                ? 'Statut scheduler'
                                                                : column.id === 'manualExecutionDate'
                                                                ? 'Date lancement manuel'
                                                                : column.id === 'statusCommand'
                                                                ? 'Statut script'
                                                                : column.id === 'size'
                                                                ? 'Taille'
                                                                : column.id === 'actions'
                                                                ? 'Exécuter'
                                                                : column.id}
                                                        </DropdownMenuCheckboxItem>
                                                    );
                                                })}
                                        </DropdownMenuContent>
                                    </DropdownMenu>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() => {
                                            setCurrentApp({
                                                id: 0,
                                                name: '',
                                                scriptName: '',
                                                recurrence: '',
                                                startTime: null,
                                                endTime: null,
                                                active: true,
                                                attemptMax: 3,
                                                statusSendEmail: false
                                            });
                                            setCommandDialogOpen(true);
                                        }}
                                    >
                                        <Plus className="mr-2 h-4 w-4" /> Ajouter
                                    </Button>
                                </div>
                            )}
                        </div>
                    </CardHeader>
                    {scriptsExpanded && (
                        <CardContent>
                            <div className="rounded-md border">
                                <Table>
                                    <TableHeader>
                                        {table.getHeaderGroups().map((headerGroup) => (
                                            <TableRow key={headerGroup.id}>
                                                {headerGroup.headers.map((header) => {
                                                    return (
                                                        <TableHead key={header.id} style={{ width: header.getSize() }}>
                                                            {header.isPlaceholder
                                                                ? null
                                                                : flexRender(
                                                                    header.column.columnDef.header,
                                                                    header.getContext()
                                                                )}
                                                        </TableHead>
                                                    );
                                                })}
                                            </TableRow>
                                        ))}
                                    </TableHeader>
                                    <TableBody>
                                        {table.getRowModel().rows?.length ? (
                                            table.getRowModel().rows.map((row) => (
                                                <TableRow
                                                    key={row.id}
                                                    data-state={row.getIsSelected() && "selected"}
                                                >
                                                    {row.getVisibleCells().map((cell) => (
                                                        <TableCell key={cell.id}>
                                                            {flexRender(cell.column.columnDef.cell, cell.getContext())}
                                                        </TableCell>
                                                    ))}
                                                </TableRow>
                                            ))
                                        ) : (
                                            <TableRow>
                                                <TableCell colSpan={columns.length} className="h-24 text-center">
                                                    Aucune commande configurée
                                                </TableCell>
                                            </TableRow>
                                        )}
                                    </TableBody>
                                </Table>
                            </div>
                        </CardContent>
                    )}
                </Card>
                <CommandForm 
                    open={commandDialogOpen} 
                    onOpenChange={setCommandDialogOpen}
                />
                <Dialog open={editDialogOpen} onOpenChange={setEditDialogOpen}>
                    <DialogContent className="sm:max-w-[525px]">
                        <DialogHeader>
                            <DialogTitle>Modifier la commande</DialogTitle>
                        </DialogHeader>
                        <div className="grid gap-4 py-4">
                            <div className="grid grid-cols-4 items-center gap-4">
                                <Label htmlFor="name" className="text-right">
                                    Nom
                                </Label>
                                <Input
                                    id="name"
                                    value={currentApp?.name || ''}
                                    className="col-span-3"
                                    onChange={(e) => updateCurrentApp('name', e.target.value)}
                                />
                            </div>
                            <div className="grid grid-cols-4 items-center gap-4">
                                <Label htmlFor="scriptName" className="text-right">
                                    Script
                                </Label>
                                <Input
                                    id="scriptName"
                                    value={currentApp?.scriptName || ''}
                                    className="col-span-3"
                                    onChange={(e) => updateCurrentApp('scriptName', e.target.value)}
                                />
                            </div>

                            <div className="grid grid-cols-4 items-center gap-4">
                                <Label htmlFor="active" className="text-right">
                                    Actif
                                </Label>
                                <div className="col-span-3">
                                    <input
                                        type="checkbox"
                                        id="active"
                                        checked={currentApp?.active || false}
                                        onChange={(e) => updateCurrentApp('active', e.target.checked)}
                                        className="mr-2"
                                    />
                                </div>
                            </div>
                            <div className="grid grid-cols-4 items-center gap-4">
                                <Label htmlFor="statusSendEmail" className="text-right">
                                    Notifications par email
                                </Label>
                                <div className="col-span-3">
                                    <input
                                        type="checkbox"
                                        id="statusSendEmail"
                                        checked={currentApp?.statusSendEmail || false}
                                        onChange={(e) => updateCurrentApp('statusSendEmail', e.target.checked)}
                                        className="mr-2"
                                    />
                                </div>
                            </div>
                        </div>
                        <DialogFooter className="flex justify-between">
                            <Button
                                type="button"
                                variant="destructive"
                                onClick={() => currentApp && handleDelete(currentApp.id)}
                                disabled={!currentApp || deleteMutation.isPending}
                            >
                                {deleteMutation.isPending ? 'Suppression...' : 'Supprimer'}
                            </Button>
                            <div className="flex gap-2">
                                <Button type="button" variant="outline" onClick={() => setEditDialogOpen(false)}>
                                    Annuler
                                </Button>
                                <Button type="submit" onClick={handleSave} disabled={!currentApp || updateMutation.isPending}>
                                    {updateMutation.isPending ? (
                                        <>
                                            <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                            Enregistrement...
                                        </>
                                    ) : (
                                        'Enregistrer'
                                    )}
                                </Button>
                            </div>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
                <Dialog open={showParamsDialog} onOpenChange={setShowParamsDialog}>
                    <DialogContent className="sm:max-w-[525px]">
                        <DialogHeader>
                            <DialogTitle>Paramètres de la commande</DialogTitle>
                        </DialogHeader>
                        <div className="grid gap-4 py-4">
                            <div className="grid grid-cols-4 items-center gap-4">
                                <Label htmlFor="start-date" className="text-right">
                                    Date de début
                                </Label>
                                <Input
                                    id="start-date"
                                    type="date"
                                    className="col-span-3"
                                    onChange={(e) => setCommandParams(prev => ({
                                        ...prev,
                                        'start-date': e.target.value
                                    }))}
                                />
                            </div>
                            <div className="grid grid-cols-4 items-center gap-4">
                                <Label htmlFor="end-date" className="text-right">
                                    Date de fin
                                </Label>
                                <Input
                                    id="end-date"
                                    type="date"
                                    className="col-span-3"
                                    onChange={(e) => setCommandParams(prev => ({
                                        ...prev,
                                        'end-date': e.target.value
                                    }))}
                                />
                            </div>
                            <div className="grid grid-cols-4 items-center gap-4">
                                <Label htmlFor="start-time" className="text-right">
                                    Heure de début
                                </Label>
                                <Input
                                    id="start-time"
                                    type="time"
                                    className="col-span-3"
                                    defaultValue="00:00"
                                    onChange={(e) => setCommandParams(prev => ({
                                        ...prev,
                                        'start-time': e.target.value
                                    }))}
                                />
                            </div>
                            <div className="grid grid-cols-4 items-center gap-4">
                                <Label htmlFor="end-time" className="text-right">
                                    Heure de fin
                                </Label>
                                <Input
                                    id="end-time"
                                    type="time"
                                    className="col-span-3"
                                    defaultValue="23:59"
                                    onChange={(e) => setCommandParams(prev => ({
                                        ...prev,
                                        'end-time': e.target.value
                                    }))}
                                />
                            </div>
                            <div className="grid grid-cols-4 items-center gap-4">
                                <Label className="text-right">Options</Label>
                                <div className="col-span-3 space-y-2">
                                    <div className="flex items-center space-x-2">
                                        <input
                                            type="checkbox"
                                            id="skip-activities"
                                            onChange={(e) => setCommandParams(prev => ({
                                                ...prev,
                                                'skip-activities': e.target.checked
                                            }))}
                                        />
                                        <Label htmlFor="skip-activities">Ignorer les activités</Label>
                                    </div>
                                    <div className="flex items-center space-x-2">
                                        <input
                                            type="checkbox"
                                            id="skip-timeslots"
                                            onChange={(e) => setCommandParams(prev => ({
                                                ...prev,
                                                'skip-timeslots': e.target.checked
                                            }))}
                                        />
                                        <Label htmlFor="skip-timeslots">Ignorer les créneaux</Label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => {
                                setShowParamsDialog(false);
                                setCommandParams({});
                            }}>
                                Annuler
                            </Button>
                            <Button 
                                type="submit" 
                                onClick={() => selectedCommandId && executeCommand(selectedCommandId, commandParams)}
                            >
                                Exécuter
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
                
                {/* Section API ODF - Intégration du composant OdfLogs */}
                <div id="api-trimble">
                    <OdfLogs 
                        apiVersion={data?.versions?.Api_Trimble}
                        title="API TRIMBLE - Logs d'exécution"
                        limit={10}
                        showHeader={true}
                        lastExecution={schedulerCommand?.lastExecutionDate ? {
                            date: new Date(schedulerCommand.lastExecutionDate).toLocaleString('fr-FR', {
                                year: 'numeric',
                                month: '2-digit',
                                day: '2-digit',
                                hour: '2-digit',
                                minute: '2-digit',
                                second: '2-digit',
                                hour12: false
                            }),
                            status: schedulerCommand.lastStatus || undefined
                        } : undefined}
                    />
                </div>

                {/* Section Wavesoft - Intégration du composant WavesoftLogs */}
                <div id="wavesoft">
                    <WavesoftLogs 
                        apiVersion={data?.versions?.Wavesoft}
                        title="WAVESOFT - Logs de création d'offre"
                        limit={10}
                        showHeader={true}
                    />
                </div>
                
                {/* Modal pour afficher le contenu JSON des étapes */}
                <StepsModal 
                    open={stepsModalOpen}
                    onOpenChange={setStepsModalOpen}
                    steps={selectedSteps}
                />
            </TooltipProvider>
        </div>
    );
}
