import { AlertCircle } from 'lucide-react';

interface ClosedOrderMessageProps {
    pcdnum: string;
}

export function ClosedOrderMessage({ pcdnum }: ClosedOrderMessageProps) {
    return (
        <div className="space-y-6">
            <div className="flex items-start gap-3 text-red-600">
                <AlertCircle className="h-5 w-5 mt-0.5" />
                <div>
                    <h3 className="font-medium">ODF clôturé</h3>
                    <p className="text-sm text-gray-600">
                        L'{pcdnum} est déjà clôturé dans WaveSoft et ne peut plus être modifié.
                    </p>
                </div>
            </div>

            <div className="bg-red-50 border border-red-200 rounded-lg p-4">
                <p className="text-sm text-red-600">
                    Veuillez contacter le service informatique si vous pensez qu'il s'agit d'une erreur.
                </p>
            </div>
        </div>
    );
} 