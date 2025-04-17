import { LogStatistiques } from "@/types/logs";

interface LogDevisesProps {
    statistiques: LogStatistiques;
}

const formatRate = (rate: string) => {
    return rate.toString().replace(/^\./, '0.');
};

export function LogDevises({ statistiques }: LogDevisesProps) {
    if (!statistiques.currencies) return null;
    return (
        <div className="space-y-4 p-4">
            <div className="grid grid-cols-2 gap-4">
                <div className="bg-white p-4 rounded-lg shadow">
                    <h3 className="font-semibold text-gray-700 mb-2">Statistiques</h3>
                    <dl className="grid grid-cols-2 gap-2">
                        <dt className="text-gray-600">Total devises:</dt>
                        <dd className="font-medium">{statistiques.total_devises}</dd>
                        <dt className="text-gray-600">Mises à jour:</dt>
                        <dd className="font-medium">{statistiques.mises_a_jour}</dd>
                        <dt className="text-gray-600">Erreurs:</dt>
                        <dd className="font-medium">{statistiques.erreurs}</dd>
                        <dt className="text-gray-600">Temps d'exécution:</dt>
                        <dd className="font-medium">{statistiques.temps_execution}</dd>
                    </dl>
                </div>
                <div className="bg-white p-4 rounded-lg shadow">
                    <h3 className="font-semibold text-gray-700 mb-2">Statut</h3>
                    <div className="space-y-2">
                        <div className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-sm font-medium ${
                            statistiques.resultats.status === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'
                        }`}>
                            {statistiques.resultats.status === 'success' ? 'Succès' : 'Erreur'}
                        </div>
                        <p className="text-gray-700">{statistiques.resultats.message}</p>
                    </div>
                </div>
            </div>

            {Object.entries(statistiques.currencies).map(([currency, details]) => (
                <div key={currency} className="bg-white p-4 rounded-lg shadow mt-4">
                    <h3 className="font-semibold text-gray-700 mb-2">Devise {currency}</h3>
                    <dl className="grid grid-cols-2 gap-2">
                        <dt className="text-gray-600">Dernière mise à jour avant ce jour:</dt>
                        <dd className="font-medium">{new Date(details.last_update).toLocaleString()}</dd>
                        <dt className="text-gray-600">Ancien taux:</dt>
                        <dd className="font-medium">
                            1 {currency} = {details.old_rate} EUR
                        </dd>
                        <dt className="text-gray-600">Nouveau taux:</dt>
                        <dd className="font-medium">
                            1 {currency} = {formatRate(details.new_rate)} EUR
                        </dd>
                        {details.summary && details.summary.articles_updated > 0 && (
                            <>
                                <dt className="text-gray-600">Articles mis à jour:</dt>
                                <dd className="font-medium">{details.summary.articles_updated}</dd>
                            </>
                        )}
                    </dl>
                </div>
            ))}

            {statistiques.resultats.details.length > 0 && (
                <div className="bg-white p-4 rounded-lg shadow mt-4">
                    <h3 className="font-semibold text-red-600 mb-2">Erreurs</h3>
                    <ul className="text-sm text-red-600 space-y-1">
                        {statistiques.resultats.details.map((detail, index) => (
                            <li key={index}>
                                {detail.currency && `[${detail.currency}] `}{detail.error}
                            </li>
                        ))}
                    </ul>
                </div>
            )}
        </div>
    );
} 