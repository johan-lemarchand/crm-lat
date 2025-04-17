import { useQuery } from "@tanstack/react-query";
import { api } from "@/lib/api";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { Badge } from "@/components/ui/badge";
import { format } from "date-fns";
import { fr } from "date-fns/locale";
import { Loader2 } from "lucide-react";

interface LogDelockProps {
    commandId: number;
    showHeader?: boolean;
}

interface DelockLog {
    id: number;
    date: string;
    resume: {
        total_coupons: number;
        success_count: number;
        error_count: number;
        details: Array<{
            pcdnum: string;
            status: 'success' | 'error';
            message?: string;
        }>;
    };
}

export function LogDelock({ commandId, showHeader = true }: LogDelockProps) {
    const { data: logs, isLoading } = useQuery<DelockLog[]>({
        queryKey: ['delock-logs', commandId],
        queryFn: () => api.get(`/api/commands/${commandId}/logs/resume`),
    });

    if (isLoading) {
        return (
            <div className="flex items-center justify-center p-8">
                <Loader2 className="h-8 w-8 animate-spin" />
            </div>
        );
    }

    if (!logs?.length) {
        return (
            <div className="text-center p-8 text-muted-foreground">
                Aucun log disponible
            </div>
        );
    }

    return (
        <Card>
            {showHeader && (
                <CardHeader>
                    <CardTitle>Logs de déverrouillage des coupons</CardTitle>
                </CardHeader>
            )}
            <CardContent>
                {logs.map((log) => (
                    <div key={log.id} className="mb-6 last:mb-0">
                        <div className="mb-2 flex items-center justify-between">
                            <div className="font-medium">
                                Exécution du {format(new Date(log.date), 'dd/MM/yyyy HH:mm:ss')}
                            </div>
                            <div className="flex items-center gap-2">
                                <Badge variant={log.resume.error_count > 0 ? 'destructive' : 'success'}>
                                    {log.resume.error_count > 0 ? 'Erreurs' : 'Succès'}
                                </Badge>
                            </div>
                        </div>
                        <div className="mb-4 text-sm text-muted-foreground">
                            <div>Total des coupons traités : {log.resume.total_coupons}</div>
                            <div>Succès : {log.resume.success_count}</div>
                            <div>Erreurs : {log.resume.error_count}</div>
                        </div>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Numéro de coupon</TableHead>
                                    <TableHead>Statut</TableHead>
                                    <TableHead>Message</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {log.resume.details.map((detail, index) => (
                                    <TableRow key={`${detail.pcdnum}-${index}`}>
                                        <TableCell>{detail.pcdnum}</TableCell>
                                        <TableCell>
                                            <Badge variant={detail.status === 'success' ? 'success' : 'destructive'}>
                                                {detail.status === 'success' ? 'Succès' : 'Erreur'}
                                            </Badge>
                                        </TableCell>
                                        <TableCell>{detail.message || '-'}</TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                ))}
            </CardContent>
        </Card>
    );
} 