import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { Button } from "@/components/ui/button";
import { Download } from "lucide-react";
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from "@/components/ui/table";

interface ApiCurrencyFile {
    name: string;
    size: number;
    modified: string;
}

interface CurrencyFilesProps {
    commandId: number;
}

export function CurrencyFiles({ commandId }: CurrencyFilesProps) {
    const [isDownloadingAll, setIsDownloadingAll] = useState(false);

    const { data: files, isLoading } = useQuery<ApiCurrencyFile[]>({
        queryKey: ['currency-files', commandId],
        queryFn: async () => {
            const response = await fetch(`${import.meta.env.VITE_API_URL}/api/commands/${commandId}/currency-files`);
            if (!response.ok) throw new Error('Failed to fetch currency files');
            return response.json();
        },
    });

    const downloadFile = async (filename: string) => {
        const encodedUrl = encodeURI(`${import.meta.env.VITE_API_URL}/api/files/currency/${filename}`);
        const response = await fetch(encodedUrl);
        
        if (!response.ok) {
            throw new Error('Failed to download file');
        }
        
        const blob = await response.blob();
        const downloadUrl = window.URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = downloadUrl;
        link.download = filename;
        document.body.appendChild(link);
        link.click();
        link.remove();
        window.URL.revokeObjectURL(downloadUrl);
    };

    const downloadAllFiles = async () => {
        if (!files) return;
        setIsDownloadingAll(true);
        try {
            await Promise.all(files.map(file => downloadFile(file.name)));
        } finally {
            setIsDownloadingAll(false);
        }
    };

    const formatFileSize = (size: number): string => {
        if (size < 1024) return `${size} B`;
        if (size < 1024 * 1024) return `${(size / 1024).toFixed(1)} KB`;
        return `${(size / (1024 * 1024)).toFixed(1)} MB`;
    };

    const formatDate = (dateString: string): string => {
        return new Date(dateString).toLocaleString('fr-FR', {
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit'
        });
    };

    if (isLoading) return <div>Chargement des fichiers...</div>;
    if (!files?.length) return <div>Aucun fichier disponible</div>;

    return (
        <div className="space-y-4">
            <div className="flex justify-end">
                <Button 
                    onClick={downloadAllFiles} 
                    disabled={isDownloadingAll}
                    variant="outline"
                >
                    {isDownloadingAll ? "Téléchargement..." : "Tout télécharger"}
                </Button>
            </div>
            
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead className="text-left">Fichier</TableHead>
                        <TableHead className="text-left">Date de modification</TableHead>
                        <TableHead className="text-left">Taille</TableHead>
                        <TableHead className="w-[100px] text-left">Action</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {files.map((file) => (
                        <TableRow key={`${file.name}-${file.modified}`}>
                            <TableCell className="text-left">{file.name}</TableCell>
                            <TableCell className="text-left">{formatDate(file.modified)}</TableCell>
                            <TableCell className="text-left">{formatFileSize(file.size)}</TableCell>
                            <TableCell>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    onClick={() => downloadFile(file.name)}
                                >
                                    <Download className="h-4 w-4" />
                                </Button>
                            </TableCell>
                        </TableRow>
                    ))}
                </TableBody>
            </Table>
        </div>
    );
} 