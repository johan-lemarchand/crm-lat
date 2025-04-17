import { Badge } from "@/components/ui/badge";
import { format } from "date-fns";
import { LogResume as ILogResume, DelockResume } from "@/types/logs";
import { LogArticles } from "./LogArticles";
import { LogDevises } from "./LogDevises";
import { LogActivites } from "./LogActivites";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";

interface LogResumeProps {
    resume: ILogResume | DelockResume | null | any;
    startedAt: string;
    status: string;
}

export function LogResume({ resume, startedAt, status }: LogResumeProps) {
    // Vérification pour resumé null, tableau vide ou objet vide
    if (!resume || Array.isArray(resume) || Object.keys(resume).length === 0) {
        return (
            <div className="space-y-4">
                <div className="flex items-center justify-between">
                    <div className="text-sm">
                        Date d'exécution : {format(new Date(startedAt), "dd/MM/yyyy HH:mm:ss")}
                    </div>
                    <Badge variant={status === 'success' ? 'success' : 'destructive'}>
                        {status}
                    </Badge>
                </div>
                <div className="text-center text-muted-foreground p-4">
                    Aucun résumé disponible
                </div>
            </div>
        );
    }

    const getResumeComponent = () => {
        // Cas pour les résumés de déverrouillage des coupons
        if ('total_coupons' in resume) {
            const delockResume = resume as DelockResume;
            return (
                <div className="space-y-4 p-4">
                    <div className="bg-white p-4 rounded-lg shadow">
                        <h3 className="font-semibold text-gray-700 mb-2">Résumé du déverrouillage</h3>
                        <dl className="grid grid-cols-3 gap-4 mb-4">
                            <div>
                                <dt className="text-gray-600">Total des coupons</dt>
                                <dd className="font-medium text-lg">{delockResume.total_coupons}</dd>
                            </div>
                            <div>
                                <dt className="text-gray-600">Succès</dt>
                                <dd className="font-medium text-lg text-green-600">{delockResume.success_count}</dd>
                            </div>
                            <div>
                                <dt className="text-gray-600">Erreurs</dt>
                                <dd className="font-medium text-lg text-red-600">{delockResume.error_count}</dd>
                            </div>
                        </dl>
                        {delockResume.details && delockResume.details.length > 0 && (
                            <div className="mt-4">
                                <h4 className="font-semibold text-gray-700 mb-2">Détails des coupons</h4>
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Numéro de coupon</TableHead>
                                            <TableHead>Statut</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {delockResume.details.map((detail, index) => (
                                            <TableRow key={index}>
                                                <TableCell>{detail.pcdnum}</TableCell>
                                                <TableCell>
                                                    <Badge variant={detail.status === 'success' ? 'success' : 'destructive'}>
                                                        {detail.status}
                                                    </Badge>
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            </div>
                        )}
                    </div>
                </div>
            );
        }

        try {
            const logResume = resume as ILogResume;
            
            // Vérifier si statistiques existe
            if (!logResume.statistiques) {
                throw new Error("Statistiques manquantes");
            }
            
            const { statistiques } = logResume;

            if (statistiques.total_articles !== undefined) {
                return <LogArticles statistiques={statistiques} />;
            }
            
            if (statistiques.currencies) {
                return <LogDevises statistiques={statistiques} />;
            }
            
            if (statistiques.activites || statistiques.creneaux) {
                return <LogActivites statistiques={statistiques} />;
            }

            // Cas par défaut
            return (
                <div className="space-y-4 p-4">
                    <div className="bg-white p-4 rounded-lg shadow">
                        <h3 className="font-semibold text-gray-700 mb-2">Informations générales</h3>
                        <dl className="grid grid-cols-2 gap-2">
                            <dt className="text-gray-600">Total analysés:</dt>
                            <dd className="font-medium">{statistiques.total_analyses}</dd>
                            <dt className="text-gray-600">Temps d'exécution:</dt>
                            <dd className="font-medium">{statistiques.temps_execution}</dd>
                        </dl>
                        {statistiques.resultats?.message && (
                            <div className="mt-4">
                                <h4 className="font-semibold text-gray-700 mb-2">Message</h4>
                                <p className="text-gray-700">{statistiques.resultats.message}</p>
                            </div>
                        )}
                    </div>
                </div>
            );
        } catch (error) {
            // En cas d'erreur, afficher un message générique
            return (
                <div className="text-center text-muted-foreground p-4">
                    Format de résumé non reconnu
                </div>
            );
        }
    };

    return (
        <div className="space-y-4">
            <div className="flex items-center justify-between">
                <div className="text-sm">
                    Date d'exécution : {format(new Date(startedAt), "dd/MM/yyyy HH:mm:ss")}
                </div>
                <Badge variant={status === 'success' ? 'success' : 'destructive'}>
                    {status}
                </Badge>
            </div>
            {getResumeComponent()}
        </div>
    );
} 