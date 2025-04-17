import React from 'react';
import { Button } from '@/components/ui/button';
import { UseMutationResult } from '@tanstack/react-query';
import { Loader2 } from 'lucide-react';

interface ExistingOrderDetailsProps {
    uniqueId: string;
    pcdnum: string;
    setCurrentStep: React.Dispatch<React.SetStateAction<number>>;
    setProgress: React.Dispatch<React.SetStateAction<number>>;
    getOrderMutation: UseMutationResult<any, Error, void, unknown>;
    setVerificationResults: React.Dispatch<React.SetStateAction<any>>;
    setHasError: React.Dispatch<React.SetStateAction<boolean>>;
    setIsTimerPaused: React.Dispatch<React.SetStateAction<boolean>>;
    setIsPauseTimerActive: React.Dispatch<React.SetStateAction<boolean>>;
}

export function ExistingOrderDetails({ 
    uniqueId, 
    pcdnum,
    setCurrentStep,
    setProgress,
    getOrderMutation,
    setVerificationResults,
    setHasError,
    setIsTimerPaused,
    setIsPauseTimerActive
}: ExistingOrderDetailsProps) {
    const handleGetOrder = () => {
        // Réinitialiser complètement l'état
        setVerificationResults({
            status: 'processing',
            messages: [
                {
                    type: 'info',
                    message: 'Récupération de la commande en cours...',
                    status: 'pending'
                }
            ],
            isClosed: false
        });
        
        // Mettre à jour l'état global
        setCurrentStep(3);
        setProgress(30);
        setHasError(false);
        setIsTimerPaused(false);
        setIsPauseTimerActive(false);
        
        // Lancer la récupération de la commande
        getOrderMutation.mutate();
    };

    return (
        <div className="bg-yellow-50 border border-yellow-200 rounded-md p-4 mb-4">
            <div className="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <div>
                    <h3 className="text-lg font-medium text-yellow-800">
                        Commande déjà existante
                    </h3>
                    <p className="text-sm text-yellow-700 mt-1">
                        L'{pcdnum} a déjà été validé (ID: {uniqueId}). Vous pouvez récupérer les détails de la commande.
                    </p>
                </div>
                <Button 
                    onClick={handleGetOrder}
                    disabled={getOrderMutation.isPending}
                    className="bg-yellow-600 hover:bg-yellow-700"
                >
                    {getOrderMutation.isPending ? (
                        <>
                            <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                            Récupération en cours...
                        </>
                    ) : (
                        "Récupérer les détails de la commande"
                    )}
                </Button>
            </div>
        </div>
    );
}
