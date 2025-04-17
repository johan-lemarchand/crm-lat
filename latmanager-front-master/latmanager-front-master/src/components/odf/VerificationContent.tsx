import { VerificationTable } from '@/components/odf/VerificationTable';
import { VerificationMessages } from '@/components/odf/VerificationMessages';
import { ClosedOrderMessage } from '@/components/odf/ClosedOrderMessage';
import { CheckCircle, X } from 'lucide-react';
import { Button } from '@/components/ui/button';

interface ResponseMessage {
    type: 'error' | 'success' | 'warning' | 'info' | 'retry';
    message: string;
    status: 'error' | 'success' | 'warning' | 'info' | 'pending' | 'retry';
    isCreationError?: boolean;
    showMailto?: boolean;
}

interface VerificationDetail {
    ligne: string;
    article: {
        code: string;
        designation: string;
        coupon: string | null;
        eligible: boolean;
        hasCoupon: boolean;
    } | string;
    quantite: number;
    serie: {
        numero: string;
        status: 'success' | 'error';
        message: string;
        message_api: string | null;
        manufacturerModel: string | null;
    } | string;
    date_end_subs: string;
    n_serie?: string;
    designation?: string;
    modele?: string;
    partDescription?: string;
}

interface ActivationDetail {
    partNumber: string;
    partDescription: string;
    serialNumber: string;
    passcode: string;
    activationDate: string;
    expirationDate: string;
    status: string;
    isQR: string;
}

interface VerificationContentProps {
    results: {
        status: string;
        messages: ResponseMessage[];
        isClosed: boolean;
        uniqueId?: string;
        details?: VerificationDetail[] | any;
        data?: {
            orderNumber?: string;
            activationDetails?: ActivationDetail[];
        };
    };
    pcdnum: string;
    onCreateOrder: () => void;
    onRetry: () => void;
    onLaunchOrder?: () => void;
    onTimerResume: () => void;
    currentStep: number;
}

export function VerificationContent({ 
    results, 
    pcdnum, 
    onCreateOrder,
    onRetry,
    onLaunchOrder,
    onTimerResume,
    currentStep
}: VerificationContentProps) {
    // Vérifier si results est défini et contient des messages
    const isVerifying = results && results.status === 'processing';

    const hasDetails = results && results.details && 
        (Array.isArray(results.details) ? results.details.length > 0 : false);
    
    // S'assurer que les messages existent et sont un tableau
    const messagesArray = results && results.messages && Array.isArray(results.messages) ? results.messages : [];
    const hasMessages = messagesArray.length > 0;
    
    // Vérifier si l'ODF est déjà clôturé
    const isClosedOdf = results && (
        results.isClosed || 
        (hasMessages && messagesArray.some(msg => 
            msg.message && (
                msg.message.includes('déjà clôturé') || 
                msg.message.includes('ODF est déjà clôturé') ||
                msg.message.includes('Cet ODF est déjà clôturé')
            )
        ))
    );
    
    if (isClosedOdf && currentStep !== 6) {
        return (
            <ClosedOrderMessage 
                pcdnum={pcdnum}
            />
        );
    }
    
    // Préparer les messages à afficher
    let displayMessages = messagesArray;
    
    // Si nous n'avons pas de messages mais que le statut est success, ajouter un message par défaut
    if (!hasMessages && results && results.status === 'success') {
        displayMessages = [{
            type: 'success',
            message: 'Vérification réussie',
            status: 'success'
        }];
    }
    
    return (
        <div className="space-y-6">
            {/* Afficher les messages s'ils existent et que nous ne sommes pas à l'étape finale */}
            {currentStep !== 6 && (
                <VerificationMessages 
                    messages={displayMessages}
                    onRetry={onRetry}
                    onLaunchOrder={onLaunchOrder}
                    onTimerResume={onTimerResume}
                    isVerifying={isVerifying}
                    currentStep={currentStep}
                />
            )}
            
            {currentStep === 6 && (
                <div className="bg-white rounded-lg shadow-lg p-8 text-center">
                    <div className="text-6xl text-green-500 mb-4">
                        <CheckCircle className="h-16 w-16 mx-auto" />
                    </div>
                    <h2 className="text-2xl font-bold text-green-600 mb-4">Commande traitée avec succès!</h2>
                    <p className="text-gray-600 mb-2">Le bon de fabrication a été créé et la commande a été finalisée.</p>
                    <p className="text-gray-800 font-medium mb-6">Numéro de commande Trimble: {results?.data?.orderNumber}</p>
                </div>
            )}
            
            {hasDetails && (
                <div className="mt-6">
                    {/* Garder la référence externe visible à toutes les étapes */}
                    {results.data?.orderNumber && (
                        <div className="mb-4 text-left">
                            <p className="text-gray-700 font-medium">
                                Ref. externe : <span className="font-bold">Order Trimble : {results.data.orderNumber}</span>
                            </p>
                        </div>
                    )}
                    
                    {results.details && Array.isArray(results.details) && (
                        <VerificationTable 
                            details={results.details} 
                            isExistingOrder={!!results.data?.orderNumber}
                            currentStep={currentStep}
                            activationDetails={results.data?.activationDetails || []}
                        />
                    )}
                </div>
            )}
        </div>
    );
} 