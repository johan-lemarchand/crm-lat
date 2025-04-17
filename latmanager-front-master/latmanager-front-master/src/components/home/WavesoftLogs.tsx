import React, { useState } from 'react';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { ChevronDown, ChevronUp, Loader2, RefreshCw, FileX, List, Eye, Copy, CheckCheck, AlertCircle } from 'lucide-react';
import { api } from '@/lib/api';
import { Pagination } from '@/components/ui/pagination';
import { format } from 'date-fns';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
    DialogFooter
} from '@/components/ui/dialog';
import { toast } from '@/components/ui/use-toast';

interface WavesoftLog {
    id: number;
    trsId: number;
    userName: string;
    status: string;
    messageError: string;
    aboId: number;
    createdAt: string;
    automateFile: string;
}

interface WavesoftLogsProps {
    apiVersion?: string;
    limit?: number;
    title?: string;
    showHeader?: boolean;
    showPagination?: boolean;
    lastExecution?: {
        date?: string;
        status?: string;
    };
}

const ITEMS_PER_PAGE = 10;

const WavesoftLogs: React.FC<WavesoftLogsProps> = ({
    apiVersion,
    limit = 10,
    title = "Logs Wavesoft",
    showHeader = true,
    showPagination = false,
    lastExecution: propLastExecution
}) => {
    // États pour l'UI
    const [wavesoftLogsExpanded, setWavesoftLogsExpanded] = useState(true);
    const [currentPage, setCurrentPage] = useState(1);
    const [lastExecution, setLastExecution] = useState<{
        date?: string;
        status?: string;
    } | undefined>(propLastExecution);
    const [selectedFile, setSelectedFile] = useState<string | null>(null);
    const [isFileModalOpen, setIsFileModalOpen] = useState(false);
    const [hasCopied, setHasCopied] = useState(false);

    const queryClient = useQueryClient();

    // Requête pour récupérer les logs Wavesoft
    const {
        data: wavesoftLogsData,
        isLoading: wavesoftLogsLoading,
        error: wavesoftLogsError
    } = useQuery<{ status: string; logs: WavesoftLog[] }>({
        queryKey: ['wavesoft-logs', showPagination],
        queryFn: () => api.get('/api/wavesoft/logs')
    });

    const wavesoftLogs = wavesoftLogsData?.logs || [];
    const totalPages = Math.ceil(wavesoftLogs.length / ITEMS_PER_PAGE);
    
    const paginatedLogs = showPagination
        ? wavesoftLogs.slice((currentPage - 1) * ITEMS_PER_PAGE, currentPage * ITEMS_PER_PAGE)
        : wavesoftLogs.slice(0, limit);

    // Mettre à jour la dernière exécution basée sur les logs
    React.useEffect(() => {
        if (wavesoftLogs.length > 0) {
            const sortedLogs = [...wavesoftLogs].sort((a, b) =>
                new Date(b.createdAt).getTime() - new Date(a.createdAt).getTime()
            );
            
            const mostRecentLog = sortedLogs[0];
            
            setLastExecution({
                date: new Date(mostRecentLog.createdAt).toLocaleString('fr-FR'),
                status: mostRecentLog.status
            });
        }
    }, [wavesoftLogs]);

    React.useEffect(() => {
        if (!isFileModalOpen) {
            setHasCopied(false);
        }
    }, [isFileModalOpen]);

    const openFileModal = (fileContent: string) => {
        setSelectedFile(fileContent);
        setIsFileModalOpen(true);
    };

    const formatFileContent = (content: string) => {
        return content.split('\n').map((line, index) => (
            <div key={index} className="whitespace-pre-wrap font-mono text-sm border-b border-gray-100 py-1">
                {line}
            </div>
        ));
    };

    const copyToClipboard = () => {
        if (selectedFile) {
            navigator.clipboard.writeText(selectedFile).then(() => {
                setHasCopied(true);
                toast({
                    title: "Copié !",
                    description: "Le contenu du fichier a été copié dans le presse-papiers",
                    variant: "success"
                });
                
                // Réinitialiser l'état après 2 secondes
                setTimeout(() => {
                    setHasCopied(false);
                }, 2000);
            });
        }
    };

    return (
        <>
            <Card className="border-0 shadow-none mt-8">
                {showHeader && (
                    <CardHeader className="pb-0">
                        <div className="flex justify-between items-center">
                            <div>
                                <div
                                    className="flex items-center gap-4 cursor-pointer"
                                    onClick={() => setWavesoftLogsExpanded(!wavesoftLogsExpanded)}
                                >
                                    <CardTitle className="text-2xl font-semibold">
                                        {title}
                                        {apiVersion && (
                                            <Badge variant="outline" className="w-fit ml-2">
                                                v{apiVersion}
                                            </Badge>
                                        )}
                                    </CardTitle>
                                    {wavesoftLogsExpanded ? (
                                        <ChevronUp className="h-5 w-5 text-muted-foreground" />
                                    ) : (
                                        <ChevronDown className="h-5 w-5 text-muted-foreground" />
                                    )}
                                </div>
                                
                                {/* Afficher la dernière exécution sous le titre */}
                                {lastExecution?.date && wavesoftLogsExpanded && (
                                    <div className="flex items-center mt-2">
                                        <div className="text-sm text-muted-foreground">
                                            Dernière exécution : {lastExecution.date}
                                            {lastExecution.status && (
                                                <span className="ml-2 inline-flex items-center">
                                                    {lastExecution.status === 'success' ? (
                                                        <>
                                                            <AlertCircle className="h-4 w-4 text-green-500 mr-1" />
                                                            <span className="text-green-500">Succès</span>
                                                        </>
                                                    ) : (
                                                        <>
                                                            <AlertCircle className="h-4 w-4 text-red-500 mr-1" />
                                                            <span className="text-red-500">Erreur</span>
                                                        </>
                                                    )}
                                                </span>
                                            )}
                                        </div>
                                    </div>
                                )}
                            </div>
                            
                            {wavesoftLogsExpanded && (
                                <div className="flex items-center gap-2">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() => queryClient.invalidateQueries({ queryKey: ['wavesoft-logs'] })}
                                        className="flex items-center gap-2"
                                    >
                                        <RefreshCw className="h-4 w-4" />
                                        Actualiser
                                    </Button>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() => window.location.href = showPagination ? '/' : '/logs/wavesoft'}
                                        className="flex items-center gap-2"
                                    >
                                        <List className="h-4 w-4" />
                                        {showPagination ? "Retour à l'accueil" : "Voir tous les logs Wavesoft"}
                                    </Button>
                                </div>
                            )}
                        </div>
                    </CardHeader>
                )}

                {wavesoftLogsExpanded && (
                    <CardContent>
                        {wavesoftLogsLoading ? (
                            <div className="flex justify-center items-center p-8">
                                <Loader2 className="h-8 w-8 animate-spin text-primary" />
                            </div>
                        ) : wavesoftLogsError ? (
                            <div className="flex flex-col items-center justify-center gap-4 p-8">
                                <div className="text-xl font-medium text-center">Erreur de chargement</div>
                                <div className="text-sm text-muted-foreground text-center max-w-md">
                                    {(wavesoftLogsError as Error).message}
                                </div>
                                <Button
                                    onClick={() => queryClient.invalidateQueries({ queryKey: ['wavesoft-logs'] })}
                                    className="mt-4"
                                >
                                    Réessayer
                                </Button>
                            </div>
                        ) : wavesoftLogs.length === 0 ? (
                            <div className="flex flex-col items-center justify-center gap-4 p-8">
                                <FileX className="h-12 w-12 text-muted-foreground" />
                                <div className="text-xl font-medium">Aucun log Wavesoft</div>
                                <div className="text-sm text-muted-foreground text-center max-w-md">
                                    Aucun log d'exécution Wavesoft n'a été trouvé.
                                </div>
                            </div>
                        ) : (
                            <>
                                <div className="rounded-md border">
                                    <Table>
                                        <TableHeader>
                                            <TableRow>
                                                <TableHead className="text-center">ID</TableHead>
                                                <TableHead className="text-center">Fichier</TableHead>
                                                <TableHead className="text-center">Utilisateur</TableHead>
                                                <TableHead className="text-center">Statut</TableHead>
                                                <TableHead className="text-center">Message d'erreur</TableHead>
                                                <TableHead className="text-center">TRS ID</TableHead>
                                                <TableHead className="text-center">ABO ID</TableHead>
                                                <TableHead className="text-center">Date de création</TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            {paginatedLogs.map((log) => (
                                                <TableRow key={log.id}>
                                                    <TableCell className="font-medium text-center">{log.id}</TableCell>
                                                    <TableCell className="text-center">
                                                        <Button
                                                            variant="link"
                                                            className="p-0 h-auto text-blue-500 flex items-center gap-1"
                                                            onClick={() => openFileModal(log.automateFile)}
                                                        >
                                                            <Eye className="h-3 w-3" />
                                                            <span>Voir le fichier</span>
                                                        </Button>
                                                    </TableCell>
                                                    <TableCell className="text-center">{log.userName}</TableCell>
                                                    <TableCell className="text-center">
                                                        <Badge 
                                                            variant={log.status === 'success' ? 'success' : 'destructive'}
                                                        >
                                                            {log.status}
                                                        </Badge>
                                                    </TableCell>
                                                    <TableCell className="text-center">
                                                        {log.messageError || <span className="text-muted-foreground">-</span>}
                                                    </TableCell>
                                                    <TableCell className="text-center">{log.trsId}</TableCell>
                                                    <TableCell className="text-center">{log.aboId}</TableCell>
                                                    <TableCell className="text-center">
                                                        {format(new Date(log.createdAt), 'dd/MM/yyyy HH:mm:ss')}
                                                    </TableCell>
                                                </TableRow>
                                            ))}
                                        </TableBody>
                                    </Table>
                                </div>
                                {showPagination && totalPages > 1 && (
                                    <div className="mt-4">
                                        <Pagination
                                            currentPage={currentPage}
                                            totalPages={totalPages}
                                            onPageChange={setCurrentPage}
                                        />
                                    </div>
                                )}
                            </>
                        )}
                    </CardContent>
                )}
            </Card>

            {/* Modale pour afficher le contenu du fichier */}
            <Dialog open={isFileModalOpen} onOpenChange={setIsFileModalOpen}>
                <DialogContent className="max-w-6xl max-h-[90vh] w-[90vw]">
                    <DialogHeader>
                        <DialogTitle>Contenu du fichier automate</DialogTitle>
                        <DialogDescription>
                            Voici le contenu du fichier utilisé pour l'exécution de l'automate
                        </DialogDescription>
                    </DialogHeader>
                    <div className="bg-gray-50 p-4 rounded border overflow-x-auto h-[60vh]">
                        {selectedFile && formatFileContent(selectedFile)}
                    </div>
                    <DialogFooter>
                        <Button 
                            variant="outline" 
                            className="gap-2"
                            onClick={copyToClipboard}
                        >
                            {hasCopied ? (
                                <>
                                    <CheckCheck className="h-4 w-4" />
                                    Copié !
                                </>
                            ) : (
                                <>
                                    <Copy className="h-4 w-4" />
                                    Copier le contenu
                                </>
                            )}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
};

export default WavesoftLogs; 