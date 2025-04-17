import { useParams } from 'react-router-dom';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { useToast } from "@/components/ui/use-toast";
import { Toaster } from "@/components/ui/toaster";
import { Loader2 } from "lucide-react";
import { useState, useEffect } from 'react';

import { LogHeader } from '@/components/logs/LogHeader';
import { DeleteLogsDialog } from '@/components/logs/DeleteLogsDialog';
import { LogTabs } from '@/components/logs/LogTabs';
import { Command, Log } from '@/types/logs';

export default function CommandLogsPage() {
    const { id } = useParams<{ id: string }>();
    const { toast } = useToast();
    const queryClient = useQueryClient();
    const [isDeleteDialogOpen, setIsDeleteDialogOpen] = useState(false);
    const [startDate, setStartDate] = useState<Date>();
    const [endDate, setEndDate] = useState<Date>();
    const [deleteType, setDeleteType] = useState<'all' | 'api' | 'history'>('all');

    // Queries
    const { data: command, isLoading: isLoadingCommand } = useQuery<Command>({
        queryKey: ['command', id],
        queryFn: async () => {
            const response = await fetch(`${import.meta.env.VITE_API_URL}/api/commands/${id}`);
            if (!response.ok) throw new Error('Failed to fetch command');
            return response.json();
        },
    });

    const { data: logs, isLoading: isLoadingLogs } = useQuery<Log[]>({
        queryKey: ['command-logs', id],
        queryFn: async () => {
            const response = await fetch(`${import.meta.env.VITE_API_URL}/api/commands/${id}/logs`);
            if (!response.ok) throw new Error('Failed to fetch logs');
            return response.json();
        },
    });

    // Date range effect
    useEffect(() => {
        if (logs && logs.length > 0) {
            const sortedLogs = [...logs].sort((a, b) => 
                new Date(a.startedAt).getTime() - new Date(b.startedAt).getTime()
            );

            if (sortedLogs[0]?.startedAt) {
                const oldestDate = new Date(sortedLogs[0].startedAt);
                oldestDate.setHours(0, 0, 0, 0);
                setStartDate(oldestDate);
            }

            if (sortedLogs[sortedLogs.length - 1]?.startedAt) {
                const newestDate = new Date(sortedLogs[sortedLogs.length - 1].startedAt);
                newestDate.setHours(23, 59, 59, 999);
                setEndDate(newestDate);
            }
        }
    }, [logs]);

    // Mutations
    const clearLogsMutation = useMutation({
        mutationFn: async ({ 
            commandId, 
            startDate, 
            endDate, 
            type = 'all' 
        }: { 
            commandId: number;
            startDate?: Date;
            endDate?: Date;
            type?: 'all' | 'api' | 'history';
        }) => {
            const params = new URLSearchParams();
            if (startDate && endDate) {
                params.append('startDate', startDate.toISOString());
                params.append('endDate', endDate.toISOString());
            }

            const response = await fetch(
                `${import.meta.env.VITE_API_URL}/api/commands/${commandId}/logs/clear/${type}${params.toString() ? `?${params.toString()}` : ''}`,
                { method: 'DELETE' }
            );

            if (!response.ok) throw new Error('Failed to clear logs');
            return response.json();
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['command-logs', id] });
            toast({
                title: "Succès",
                description: "Les logs ont été supprimés",
                variant: "success",
            });
            setIsDeleteDialogOpen(false);
            setStartDate(undefined);
            setEndDate(undefined);
        },
        onError: () => {
            toast({
                title: "Erreur",
                description: "Échec de la suppression des logs",
                variant: "destructive",
            });
        },
    });

    const handleClearLogs = (type: 'all' | 'api' | 'history' = 'all') => {
        if (!id) return;
        clearLogsMutation.mutate({
            commandId: parseInt(id),
            startDate,
            endDate,
            type
        });
    };

    if (isLoadingCommand || isLoadingLogs) {
        return (
            <div className="flex items-center justify-center h-screen">
                <Loader2 className="h-8 w-8 animate-spin" />
            </div>
        );
    }

    return (
        <div className="p-8">
            <Toaster />
            {command && (
                <LogHeader
                    commandName={command.name}
                    scriptName={command.scriptName}
                    startDate={startDate}
                    endDate={endDate}
                    onDeleteClick={() => setIsDeleteDialogOpen(true)}
                />
            )}

            {logs && (
                <LogTabs 
                    logs={logs} 
                    commandId={parseInt(id)} 
                    scriptName={command?.scriptName}
                />
            )}

            <DeleteLogsDialog
                open={isDeleteDialogOpen}
                onOpenChange={setIsDeleteDialogOpen}
                startDate={startDate}
                endDate={endDate}
                onStartDateChange={setStartDate}
                onEndDateChange={setEndDate}
                onConfirm={() => handleClearLogs(deleteType)}
                isDeleting={clearLogsMutation.isPending}
                deleteType={deleteType}
                onDeleteTypeChange={setDeleteType}
            />
        </div>
    );
}