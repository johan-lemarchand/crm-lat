import {useQuery} from '@tanstack/react-query';
import {useNavigate, useParams} from 'react-router-dom';
import {Card, CardContent, CardHeader, CardTitle} from '@/components/ui/card';
import {Button} from '@/components/ui/button';
import {ArrowLeft, Folder, Loader2} from 'lucide-react';

interface Script {
    name: string;
    lastModified: string;
    path: string;
}

export default function Scripts() {
    const { appName } = useParams<{ appName: string }>();
    const navigate = useNavigate();

    const { data: scripts, isLoading, error } = useQuery<Script[]>({
        queryKey: ['scripts', appName],
        queryFn: async () => {
            try {
                const response = await fetch(`${import.meta.env.VITE_API_URL}/api/applications/${appName}/scripts`);
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return await response.json();
            } catch (error) {
                throw error;
            }
        },
    });

    if (error) {
        return (
            <div className="container mx-auto py-8">
                <div className="text-red-500">
                    Erreur: {error.message}
                </div>
            </div>
        );
    }

    if (isLoading) {
        return (
            <div className="flex items-center justify-center min-h-screen">
                <Loader2 className="h-8 w-8 animate-spin text-primary" />
            </div>
        );
    }

    return (
        <div className="container mx-auto py-8">
            <div className="flex items-center mb-8 gap-4">
                <Button 
                    variant="outline" 
                    onClick={() => navigate('/')}
                    className="flex items-center gap-2 border-[#4281C3] text-[#4281C3] hover:bg-[#4281C3] hover:text-white"
                >
                    <ArrowLeft className="h-4 w-4" />
                    Retour
                </Button>
                <h1 className="text-3xl font-bold text-[#4281C3]">Scripts de {appName}</h1>
            </div>

            {scripts && scripts.length > 0 ? (
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    {scripts.map((script) => (
                        <Card key={script.name} className="hover:shadow-lg transition-shadow duration-200 border-[#98B1DC] hover:border-[#4281C3]">
                            <CardHeader>
                                <div className="flex items-start gap-4">
                                    <Folder className="h-6 w-6 text-[#87B04A]" />
                                    <div>
                                        <CardTitle className="text-xl text-[#4281C3]">{script.name}</CardTitle>
                                        <div className="text-sm text-[#1B3856]/60">
                                            <div>Modifié le: {new Date(script.lastModified).toLocaleString()}</div>
                                        </div>
                                    </div>
                                </div>
                            </CardHeader>
                            <CardContent>
                                <Button 
                                    className="w-full bg-[#4281C3] hover:bg-[#98B1DC]"
                                    onClick={() => {
                                        console.log('Ouvrir le dossier:', script.name);
                                    }}
                                >
                                    Ouvrir
                                </Button>
                            </CardContent>
                        </Card>
                    ))}
                </div>
            ) : (
                <div className="text-center text-[#1B3856]/60">
                    Aucun dossier de scripts trouvé pour cette application.
                </div>
            )}
        </div>
    );
}
