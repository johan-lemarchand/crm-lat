export function InvalidTokenMessage() {
    return (
        <div className="flex items-center justify-center min-h-screen bg-slate-50">
            <div className="p-8 bg-white rounded-lg shadow-lg max-w-md w-full mx-4">
                <h1 className="text-2xl font-semibold text-red-600 mb-4">
                    Accès non autorisé
                </h1>
                <p className="text-gray-600">
                    Ce lien est invalide ou a expiré. Veuillez utiliser un lien valide depuis WaveSoft.
                </p>
            </div>
        </div>
    );
} 