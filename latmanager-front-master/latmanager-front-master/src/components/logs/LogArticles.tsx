import { LogStatistiques } from "@/types/logs";

interface LogArticlesProps {
    statistiques: LogStatistiques;
}

export function LogArticles({ statistiques }: LogArticlesProps) {
    return (
        <div className="space-y-4 p-4">
            <div className="grid grid-cols-2 gap-4">
                <div className="bg-white p-4 rounded-lg shadow">
                    <h3 className="font-semibold text-gray-700 mb-2">Statistiques</h3>
                    <dl className="grid grid-cols-2 gap-2">
                        <dt className="text-gray-600">Total analysés:</dt>
                        <dd className="font-medium">{statistiques.total_analyses}</dd>
                        <dt className="text-gray-600">À mettre à jour:</dt>
                        <dd className="font-medium">{statistiques.total_a_mettre_a_jour}</dd>
                        <dt className="text-gray-600">Pourcentage:</dt>
                        <dd className="font-medium">{statistiques.pourcentage}</dd>
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
            {statistiques.articles_modifies && statistiques.articles_modifies.length > 0 && (
                <div className="bg-white p-4 rounded-lg shadow mt-4">
                    <h3 className="font-semibold text-gray-700 mb-4">Articles Modifiés</h3>
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-gray-200">
                            <thead className="bg-gray-50">
                                <tr>
                                    <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Code</th>
                                    <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Désignation</th>
                                    <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ancien Prix</th>
                                    <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nouveau Prix</th>
                                </tr>
                            </thead>
                            <tbody className="bg-white divide-y divide-gray-200">
                                {statistiques.articles_modifies.map((article, index) => (
                                    <tr key={index} className={index % 2 === 0 ? 'bg-white' : 'bg-gray-50'}>
                                        <td className="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{article.code}</td>
                                        <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{article.designation}</td>
                                        <td className="px-6 py-4 whitespace-nowrap text-sm text-red-500">{article.ancien_prix}</td>
                                        <td className="px-6 py-4 whitespace-nowrap text-sm text-green-500">{article.nouveau_prix}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            )}
        </div>
    );
} 