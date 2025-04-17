import { LogStatistiques } from "@/types/logs";

interface LogActivitesProps {
    statistiques: LogStatistiques;
}

export function LogActivites({ statistiques }: LogActivitesProps) {
    if (!statistiques.activites && !statistiques.creneaux) return null;

    return (
        <div className="space-y-4 p-4">
            <div className="grid grid-cols-2 gap-4">
                {statistiques.activites && (
                    <div className="bg-white p-4 rounded-lg shadow">
                        <h3 className="font-semibold text-gray-700 mb-2">Activités</h3>
                        <dl className="grid grid-cols-2 gap-2">
                            <dt className="text-gray-600">Total analysées:</dt>
                            <dd className="font-medium">{statistiques.activites.total}</dd>
                            <dt className="text-gray-600">Créées:</dt>
                            <dd className="font-medium">{statistiques.activites.created}</dd>
                            <dt className="text-gray-600">Mises à jour:</dt>
                            <dd className="font-medium">{statistiques.activites.updated}</dd>
                            <dt className="text-gray-600">Erreurs:</dt>
                            <dd className="font-medium">{statistiques.activites.errors}</dd>
                        </dl>
                        {statistiques.activites.error_details && statistiques.activites.error_details.length > 0 && (
                            <div className="mt-4">
                                <h4 className="font-semibold text-red-600 mb-2">Erreurs</h4>
                                <ul className="text-sm text-red-600 space-y-1">
                                    {statistiques.activites.error_details.map((error, index) => (
                                        <li key={index}>[{error.type}] {error.message}</li>
                                    ))}
                                </ul>
                            </div>
                        )}
                    </div>
                )}
                {statistiques.creneaux && (
                    <div className="bg-white p-4 rounded-lg shadow">
                        <h3 className="font-semibold text-gray-700 mb-2">Créneaux</h3>
                        <dl className="grid grid-cols-2 gap-2">
                            <dt className="text-gray-600">Total analysés:</dt>
                            <dd className="font-medium">{statistiques.creneaux.total}</dd>
                            <dt className="text-gray-600">Créés:</dt>
                            <dd className="font-medium">{statistiques.creneaux.created}</dd>
                            <dt className="text-gray-600">Mis à jour:</dt>
                            <dd className="font-medium">{statistiques.creneaux.updated}</dd>
                            <dt className="text-gray-600">Supprimés:</dt>
                            <dd className="font-medium">{statistiques.creneaux.deleted}</dd>
                            <dt className="text-gray-600">Erreurs:</dt>
                            <dd className="font-medium">{statistiques.creneaux.errors}</dd>
                        </dl>
                        {statistiques.creneaux.error_details && statistiques.creneaux.error_details.length > 0 && (
                            <div className="mt-4">
                                <h4 className="font-semibold text-red-600 mb-2">Erreurs</h4>
                                <ul className="text-sm text-red-600 space-y-1">
                                    {statistiques.creneaux.error_details.map((error, index) => (
                                        <li key={index}>[{error.type}] {error.message}</li>
                                    ))}
                                </ul>
                            </div>
                        )}
                    </div>
                )}
            </div>
            <div className="bg-white p-4 rounded-lg shadow">
                <h3 className="font-semibold text-gray-700 mb-2">Informations générales</h3>
                <dl className="grid grid-cols-2 gap-2">
                    <dt className="text-gray-600">Total analysés:</dt>
                    <dd className="font-medium">{statistiques.total_analyses}</dd>
                    <dt className="text-gray-600">Temps d'exécution:</dt>
                    <dd className="font-medium">{statistiques.temps_execution}</dd>
                    <dt className="text-gray-600">Statut:</dt>
                    <dd className="font-medium">
                        <div className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-sm font-medium ${
                            statistiques.resultats.status === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'
                        }`}>
                            {statistiques.resultats.status === 'success' ? 'Succès' : 'Erreur'}
                        </div>
                    </dd>
                </dl>
                {statistiques.resultats.message && (
                    <div className="mt-4">
                        <h4 className="font-semibold text-gray-700 mb-2">Message</h4>
                        <p className="text-gray-700">{statistiques.resultats.message}</p>
                    </div>
                )}
            </div>
        </div>
    );
} 