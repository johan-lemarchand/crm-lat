import { Clock, RefreshCw, CheckCircle, AlertTriangle, Loader2, Info } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useState, useEffect } from 'react';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from "@/components/ui/dialog";
interface Message {
    type: string;
    message: string;
    status: string;
    isCreationError?: boolean;
    showMailto?: boolean;
}

interface VerificationMessagesProps {
    messages: Message[];
    onRetry?: () => void;
    onLaunchOrder?: () => void;
    onTimerResume?: () => void;
    isVerifying?: boolean;
    currentStep?: number;
}

const formatMailtoLink = (message: string) => {
    // Extraire les informations importantes du message
    const idMatch = message.match(/ID: (\d+)/);
    const refMatch = message.match(/\[BDFODF_(\d+)\]/);
    const refMatch2 = message.match(/\[BTRODF_(\d+)\]/);
    
    const id = idMatch ? idMatch[1] : '';
    const reference = refMatch ? `BDFODF_${refMatch[1]}` : (refMatch2 ? `BTRODF_${refMatch2[1]}` : '');

    const emailSubject = encodeURIComponent("Erreur BDFODF - Référence existante");
    const emailBody = encodeURIComponent(`Bonjour,

Je rencontre une erreur lors de la création d'un BDFODF.

ID: ${id}
Référence: ${reference}
Message complet: ${message}

Cordialement`);

    const mailtoUrl = `mailto:informatique@latitudegps.com?subject=${emailSubject}&body=${emailBody}`;

    // Séparer le message en parties avant/après l'email
    const parts = message.split('informatique@latitudegps.com');
    
    if (parts.length === 2) {
        return {
            formattedMessage: (
                <>
                    {parts[0]}
                    <a 
                        href={mailtoUrl}
                        className="text-blue-600 underline hover:text-blue-800"
                        target="_blank"
                        rel="noopener noreferrer"
                    >
                        informatique@latitudegps.com
                    </a>
                    {parts[1]}
                </>
            ),
            mailtoUrl
        };
    }

    return {
        formattedMessage: message,
        mailtoUrl: `mailto:informatique@latitudegps.com?subject=${emailSubject}&body=${emailBody}`
    };
};

export function VerificationMessages({ 
    messages = [], // Fournir une valeur par défaut pour éviter les erreurs
    onRetry, 
    onLaunchOrder,
    onTimerResume,
    isVerifying = false,
    currentStep = 1
}: VerificationMessagesProps) {
    const [timeLeft, setTimeLeft] = useState(12 * 60);
    const [isExpired, setIsExpired] = useState(false);
    const [isConfirmOpen, setIsConfirmOpen] = useState(false);

    // Vérifier si messages est défini avant d'appeler filter
    // S'assurer que messages est un tableau
    const messagesArray = Array.isArray(messages) ? messages : [];
    
    // Filtrer les messages pour éviter les doublons d'erreurs API Trimble
    const filteredMessages = messagesArray.reduce((acc: Message[], current) => {
        // Vérifier que current est un objet valide avec les propriétés nécessaires
        if (!current || typeof current !== 'object' || !current.type || !current.message) {
            return acc;
        }
        
        // Si c'est une erreur API Trimble et qu'on a déjà une erreur similaire, ne pas l'ajouter
        if (current.type === 'error' && current.message.includes('Erreur API Trimble')) {
            const alreadyHasTrimbleError = acc.some(msg => 
                msg.type === 'error' && msg.message.includes('Erreur API Trimble')
            );
            if (alreadyHasTrimbleError) {
                return acc;
            }
        }
        
        // Déduplication des erreurs concernant le champ manufacturerModel
        if (current.type === 'error' && current.message.includes('manufacturerModel')) {
            const alreadyHasManufacturerModelError = acc.some(msg => 
                msg.type === 'error' && 
                msg.message.includes('manufacturerModel') &&
                msg.message.includes(current.message.split('produit:')[1]?.trim() || '')
            );
            if (alreadyHasManufacturerModelError) {
                return acc;
            }
        }
        
        // Déduplication générale des messages identiques
        const isDuplicate = acc.some(msg => 
            msg.type === current.type && 
            msg.message === current.message
        );
        if (isDuplicate) {
            return acc;
        }
        
        return [...acc, current];
    }, []);
    
    const errorMessages = filteredMessages.filter(msg => msg.type === 'error');
    const successMessages = filteredMessages.filter(msg => msg.type === 'success');
    const warningMessages = filteredMessages.filter(msg => msg.type === 'warning');
    const infoMessages = filteredMessages.filter(msg => msg.type === 'info');
    const retryMessages = filteredMessages.filter(msg => msg.type === 'retry');
    
    // Vérifier s'il y a des erreurs de création
    const hasCreationError = errorMessages.some(msg => msg.isCreationError);
    
    // Mettre en évidence les erreurs de création de commande
    const formattedErrorMessages = errorMessages.map(msg => {
        // Si c'est une erreur de création, ajouter un préfixe pour la rendre plus visible
        if (msg.isCreationError) {
            return {
                ...msg,
                message: msg.message.startsWith('Erreur de création') 
                    ? msg.message 
                    : `Erreur de création : ${msg.message}`
            };
        }
        return msg;
    });
    
    // Déterminer le texte du bouton en fonction de l'étape
    const getRetryButtonText = () => {
        if (currentStep === 1) return "Réessayer la vérification";
        if (currentStep === 2 && hasCreationError) return "Réessayer la création";
        if (currentStep === 3) return "Réessayer la récupération";
        if (currentStep === 4) return "Réessayer la récupération des infos d'activation";
        if (currentStep === 5) return "Réessayer la création du bon de fabrication";
        return "Réessayer";
    };

    // Ne démarrer le timer que pour l'étape 1 ou 2
    useEffect(() => {
        if (successMessages.length > 0 && (currentStep === 1 || currentStep === 2)) {
            const timer = setInterval(() => {
                setTimeLeft(prev => {
                    if (prev <= 1) {
                        setIsExpired(true);
                        clearInterval(timer);
                        return 0;
                    }
                    return prev - 1;
                });
            }, 1000);

            return () => clearInterval(timer);
        }
    }, [successMessages, currentStep]);

    const formatTime = (seconds: number): string => {
        const minutes = Math.floor(seconds / 60);
        const remainingSeconds = seconds % 60;
        return `${minutes}:${remainingSeconds.toString().padStart(2, '0')}`;
    };

    const handleConfirm = () => {
        setIsConfirmOpen(false);
        onLaunchOrder?.();
    };

    // Déterminer si on peut continuer (aucune erreur et pas expiré)
    const canContinue = errorMessages.length === 0 && !isExpired;
    
    // Déterminer si on doit montrer le bouton de relance
    const showRetry = errorMessages.length > 0 || isExpired;

    // Déterminer si on doit montrer le bouton "Lancer la commande"
    // Ne pas l'afficher à l'étape 3 ou 4 (récupération et vérification des stocks)
    // Ne pas l'afficher si nous sommes dans le contexte d'un ODF déjà validé
    const isAlreadyValidatedMessage = successMessages.some(msg => 
        msg.message.includes('déjà validé') || msg.message.includes('déjà été validé')
    );
    
    // Ne pas afficher le bouton si un message indique que la création de commande est en cours
    const isCreatingOrderMessage = messages.some(msg => 
        msg.message.includes('Création de la commande en cours')
    );
    
    const showLaunchButton = canContinue && onLaunchOrder && currentStep < 3 && !isAlreadyValidatedMessage && !isCreatingOrderMessage;

    return (
        <>
            {/* Message de validation principal */}
            {successMessages.length > 0 && (
                <div className={`border rounded-lg p-6 mb-6 text-center ${isExpired ? 'bg-yellow-50 border-yellow-200' : 'bg-green-50 border-green-200'}`}>
                    <div className="flex flex-col items-center justify-center">
                        {isExpired ? (
                            <AlertTriangle className="h-12 w-12 text-yellow-500 mb-3" />
                        ) : (
                            <CheckCircle className="h-12 w-12 text-green-500 mb-3" />
                        )}
                        <h3 className={`text-xl font-semibold ${isExpired ? 'text-yellow-800' : 'text-green-800'}`}>
                            {isExpired 
                                ? "Le délai de validation est expiré" 
                                : successMessages[0].message}
                        </h3>
                        {/* N'afficher le timer que pour les étapes 1 et 2 */}
                        {(currentStep === 1 || currentStep === 2) && !isExpired ? (
                            <div className={`mt-2 flex items-center gap-2 text-green-700`}>
                                <Clock className="h-5 w-5" />
                                <span className="font-mono text-lg">{formatTime(timeLeft)}</span>
                            </div>
                        ) : null}
                        {/* N'afficher le message de temps que pour les étapes 1 et 2 */}
                        {(currentStep === 1 || currentStep === 2) && (
                            <p className={`text-sm mt-2 ${isExpired ? 'text-yellow-600' : 'text-green-600'}`}>
                                {isExpired 
                                    ? "Veuillez relancer la vérification pour continuer." 
                                    : `Vous avez ${Math.floor(timeLeft / 60)} minutes pour lancer la commande.`}
                            </p>
                        )}
                    </div>
                </div>
            )}

            {/* Messages d'information */}
            {infoMessages.length > 0 && (
                <div className="border rounded-lg p-4 mb-4 bg-blue-50 border-blue-200">
                    {infoMessages.map((msg, index) => (
                        <div key={index} className="flex items-center gap-3 text-blue-800">
                            <Info className="h-5 w-5 flex-shrink-0" />
                            <p>{msg.message}</p>
                        </div>
                    ))}
                </div>
            )}

            {/* Messages d'erreur et d'avertissement */}
            <div className="space-y-4">
                {/* Afficher les messages de type retry */}
                {retryMessages.map((message, index) => (
                    <div 
                        key={`retry-${index}`} 
                        className="p-4 rounded-lg border bg-blue-50 border-blue-200 text-blue-800"
                    >
                        <div className="flex items-start">
                            <div className="flex-shrink-0 mt-0.5">
                                <Loader2 className="h-5 w-5 text-blue-500 animate-spin" />
                            </div>
                            <div className="ml-3 flex-grow">
                                <div className="text-sm font-medium break-words whitespace-pre-line max-w-full overflow-auto p-2 bg-white bg-opacity-50 rounded border border-blue-100 mb-2">
                                    {message.message}
                                </div>
                            </div>
                        </div>
                    </div>
                ))}

                {formattedErrorMessages.map((message, index) => {
                    const mailtoInfo = message.showMailto ? formatMailtoLink(message.message) : { formattedMessage: message.message, mailtoUrl: '' };
                    
                    return (
                        <div 
                            key={index} 
                            className={`p-4 rounded-lg border ${
                                message.type === 'error'
                                    ? 'bg-red-50 border-red-200 text-red-800'
                                    : message.type === 'warning'
                                    ? 'bg-yellow-50 border-yellow-200 text-yellow-800'
                                    : message.type === 'info'
                                    ? 'bg-blue-50 border-blue-200 text-blue-800'
                                    : 'bg-green-50 border-green-200 text-green-800'
                            } ${message.showMailto ? 'mb-6' : ''}`}
                        >
                            <div className="flex items-start">
                                <div className="flex-shrink-0 mt-0.5">
                                    {message.type === 'error' ? (
                                        <AlertTriangle className="h-5 w-5 text-red-500" />
                                    ) : message.type === 'warning' ? (
                                        <AlertTriangle className="h-5 w-5 text-yellow-500" />
                                    ) : message.type === 'info' && message.status === 'pending' ? (
                                        <Loader2 className="h-5 w-5 text-blue-500 animate-spin" />
                                    ) : (
                                        <CheckCircle className="h-5 w-5 text-green-500" />
                                    )}
                                </div>
                                <div className="ml-3 flex-grow">
                                    {message.type === 'error' && (
                                        <h4 className="font-bold text-red-700 mb-2">Détails de l'erreur :</h4>
                                    )}
                                    <div className="text-sm font-medium break-words whitespace-pre-line max-w-full overflow-auto p-2 bg-white bg-opacity-50 rounded border border-red-100 mb-2">
                                        {mailtoInfo.formattedMessage}
                                    </div>
                                    {message.showMailto && (
                                        <div className="mt-2 text-center">
                                            <a
                                                href={mailtoInfo.mailtoUrl}
                                                className="inline-block px-4 py-2 bg-blue-100 text-blue-700 rounded-md hover:bg-blue-200 transition-colors"
                                            >
                                                Contacter le support
                                            </a>
                                        </div>
                                    )}
                                </div>
                            </div>
                        </div>
                    );
                })}

                <div className="flex justify-center space-x-3 mt-6">
                    {showRetry && onRetry && (
                        <Button 
                            variant="outline" 
                            onClick={onRetry}
                            className="flex items-center gap-2"
                        >
                            <RefreshCw className="h-4 w-4" />
                            {getRetryButtonText()}
                        </Button>
                    )}
                    
                    {showLaunchButton && (
                        <Button 
                            onClick={() => setIsConfirmOpen(true)}
                            className="flex items-center gap-2"
                        >
                            <CheckCircle className="h-4 w-4" />
                            Lancer la commande
                        </Button>
                    )}
                </div>
            </div>
            
            {/* Boîte de dialogue de confirmation */}
            <Dialog open={isConfirmOpen} onOpenChange={setIsConfirmOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Confirmer le lancement de la commande</DialogTitle>
                        <DialogDescription>
                            Êtes-vous sûr de vouloir lancer la commande ? Cette action est irréversible.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setIsConfirmOpen(false)}>
                            Annuler
                        </Button>
                        <Button onClick={handleConfirm}>
                            Confirmer
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
} 