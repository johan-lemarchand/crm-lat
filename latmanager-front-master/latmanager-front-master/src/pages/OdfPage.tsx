import {useEffect, useState} from 'react';
import {useParams} from 'react-router-dom';
import {Card, CardContent, CardHeader, CardTitle} from '@/components/ui/card';
import {useToast} from '@/components/ui/use-toast';
import {VersionInfo} from '@/components/odf/VersionInfo';
import {api} from '@/lib/api';
import {useMutation, useQueryClient} from '@tanstack/react-query';
import {VerificationProgress} from '@/components/odf/VerificationProgress';
import {Timer} from '@/components/odf/Timer';
import {InvalidTokenMessage} from '@/components/odf/InvalidTokenMessage';
import {VerificationContent} from '@/components/odf/VerificationContent';
import {ExistingOrderDetails} from '@/components/odf/ExistingOrderDetails';
import Confetti from 'react-confetti';
import { base64UrlDecode } from '@/lib/utils';

// Définir les types pour une meilleure sécurité de type
type MessageType = 'error' | 'success' | 'warning' | 'info' | 'retry';
type MessageStatus = 'error' | 'success' | 'warning' | 'info' | 'pending' | 'retry';
type ProgressStatus = 'error' | 'success' | 'pending';
type ResponseStatus = 'error' | 'success' | 'pending' | 'processing';

// Interface pour les messages de réponse
interface ResponseMessage {
    type: MessageType;
    message: string;
    status: MessageStatus;
    isCreationError?: boolean;
    showMailto?: boolean;
}

// Interface pour les messages de réponse de l'API
interface ApiResponseMessage extends ResponseMessage {
    isFinal?: boolean;
    showConfetti?: boolean;
}

// Interface pour les détails de vérification
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
    date_fin?: string;
    coupon?: string;
}

// Interface pour les détails d'activation
interface ActivationDetail {
    partNumber: string;
    partDescription: string;
    serialNumber: string;
    passcode: string;
    activationDate: string;
    expirationDate: string;
    status: string;
    isQR: string;
    serviceStartDate?: string;
    serviceEndDate?: string;
    qrCodeLink?: string;
    passcodeFileType?: string;
    passcodeFileContent?: {
        sn: string;
        codeList: string[];
    };
    artcode?: string;
}

// Utiliser la même interface pour DetailItem que VerificationDetail
type DetailItem = VerificationDetail;

// Définir les interfaces des données d'activation avec la structure correcte
interface ActivationDetailApiResponse {
    article?: string;
    designation?: string;
    n_serie?: string;
    passcode?: string;
    activation_date?: string;
    expiration_date?: string;
    status?: string;
    serialNumber?: string;
    artcode?: string;
    partDescription?: string;
    serviceStartDate?: string;
    serviceEndDate?: string;
    activationRequestType?: string;
    qrCodeLink?: string;
    passcodeActivationStatus?: string;
    passcodeActivationErrorMsg?: string;
    otaActivationStatus?: string;
    otaActivationErrorMsg?: string;
    passcodeFileType?: string;
    passcodeFileContent?: {
        sn: string;
        codeList: string[];
    };
    isQR?: string;
}

// Définir le type de réponse de l'API
interface ApiResponse {
    status: ResponseStatus;
    messages: ResponseMessage[];
    isClosed: boolean;
    uniqueId?: string;
    memoId?: string;
    apiVersion?: string;
    trimbleVersion?: string;
    currentStep?: string;
    progress?: number;
    details?: DetailItem[] | {
        status: ResponseStatus;
        currentStep?: string;
        progress?: number;
        isClosed?: boolean;
        messages?: ResponseMessage[];
    };
    retry?: boolean;
    message?: string;
    retryCount?: number;
    maxRetries?: number;
    sessionId?: string;
    data?: {
        orderNumber?: string;
        pcdid?: string;
        eventDataGetOrder?: {
            orderHdr?: {
                items?: DetailItem[];
            };
        };
        activationDetails?: ActivationDetailApiResponse[];
    };
}

// Mettre à jour l'interface ProgressNotification
interface ProgressNotification {
    status: ProgressStatus;
    messages: ResponseMessage[];
}

// Mettre à jour l'interface CheckResponse
interface CheckResponse extends Omit<ProgressNotification, 'status'> {
    status: ResponseStatus;
    isClosed: boolean;
    uniqueId?: string;
    memoId?: string;
    apiVersion?: string;
    trimbleVersion?: string;
    details?: DetailItem[] | {
        status: ResponseStatus;
        currentStep?: string;
        progress?: number;
        isClosed?: boolean;
        messages?: ResponseMessage[];
    };
    sessionId?: string;
    data?: {
        orderNumber?: string;
        pcdid?: string;
        eventDataGetOrder?: {
            orderHdr?: {
                items?: DetailItem[];
            };
        };
        activationDetails?: ActivationDetailApiResponse[];
    };
}

interface TokenParams {
    id: string;
    pcdnum: string;
    user: string;
    exp: number;
}

interface TokenResponse {
    valid: boolean;
    data?: TokenParams;
    type?: string;
    expires_at?: number;
}

// Ajout des types pour les étapes
type ProcessStep = {
  id: number;
  name: string;
  progress: number;
}

// Définition des étapes du processus
const PROCESS_STEPS: ProcessStep[] = [
  { id: 1, name: 'Vérification des données', progress: 15 },
  { id: 2, name: 'Création commande', progress: 30 },
  { id: 3, name: 'Récupération de la commande', progress: 45 },
  { id: 4, name: 'Récupération des infos d\'activation', progress: 60 },
  { id: 5, name: 'Construction du bon de fabrication', progress: 75 },
  { id: 6, name: 'Finalisation commande', progress: 100 },
];

declare global {
    interface Window {
        _currentStep?: number;
        _isFromExistingOrder?: boolean;
        _orderDetails?: VerificationDetail[];
        _orderNumber?: string;
        _retryCount?: number;
    }
}

// Fonction pour transformer ActivationDetailApiResponse en ActivationDetail
const transformActivationDetails = (apiDetails?: ActivationDetailApiResponse[]): ActivationDetail[] => {
    if (!apiDetails) return [];
    
    return apiDetails.map(item => ({
        partNumber: item.artcode || item.article || '',
        partDescription: item.partDescription || item.designation || '',
        serialNumber: item.serialNumber || item.n_serie || '',
        passcode: item.passcode || '',
        activationDate: item.serviceStartDate || item.activation_date || '',
        expirationDate: item.serviceEndDate || item.expiration_date || '',
        status: item.passcodeActivationStatus || item.status || '',
        isQR: item.isQR || '',
        passcodeFileType: item.passcodeFileType || '',
        passcodeFileContent: item.passcodeFileContent || undefined,
        // Conserver les champs originaux
        serviceStartDate: item.serviceStartDate,
        serviceEndDate: item.serviceEndDate,
        qrCodeLink: item.qrCodeLink,
        artcode: item.artcode
    }));
};

export default function OdfPage() {
    const { token } = useParams<{ token: string }>();
    const { toast } = useToast();
    const [params, setParams] = useState<TokenParams | null>(null);
    const [isTimerPaused, setIsTimerPaused] = useState(false);
    const [hasError, setHasError] = useState(false);
    const [progress, setProgress] = useState(0);
    const [verificationResults, setVerificationResults] = useState<CheckResponse | null>(null);
    const [shouldResetTimer, setShouldResetTimer] = useState(false);
    const queryClient = useQueryClient();
    const [isPauseTimerActive, setIsPauseTimerActive] = useState(false);
    const [currentStep, setCurrentStep] = useState<number>(1);
    const [elapsedTime, setElapsedTime] = useState(0);
    const [pauseTime, setPauseTime] = useState(0);
    const [isLoading, setIsLoading] = useState(false);
    const [showConfetti, setShowConfetti] = useState<boolean>(false);
    const [sessionId, setSessionId] = useState<string | null>(null);
    const [activationCountdown, setActivationCountdown] = useState<number>(60);

    const { mutate: checkOdf, isPending } = useMutation({
        mutationFn: async () => {
            if (!params?.id || !params?.pcdnum || !params?.user) {
                throw new Error('Paramètres manquants');
            }
            const apiParams: Record<string, string> = {
                pcdid: params.id,
                pcdnum: params.pcdnum,
                user: params.user
            };
            
            // Ajouter le sessionId s'il existe
            if (sessionId) {
                apiParams.sessionId = sessionId;
            }
            const response = await api.get<any>('/api/odf/check', {
                params: apiParams
            });
            
            // Créer une nouvelle réponse correctement structurée
            const formattedResponse: CheckResponse = {
                status: response.status,
                messages: response.messages || [],
                isClosed: response.isClosed || false,
                sessionId: response.sessionId,
                uniqueId: response.uniqueId,
                memoId: response.memoId,
                apiVersion: response.apiVersion,
                trimbleVersion: response.trimbleVersion
            };
            
            // Traiter la structure imbriquée des détails
            if (response.details) {
                if (!Array.isArray(response.details) && 'details' in response.details && Array.isArray(response.details.details)) {
                    // Extraire les messages des détails si présents
                    if ('messages' in response.details && Array.isArray(response.details.messages)) {
                        formattedResponse.messages = [...formattedResponse.messages, ...response.details.messages];
                    }
                    
                    // Extraire le tableau details et le mettre au niveau supérieur
                    formattedResponse.details = response.details.details;
                } else {
                    formattedResponse.details = response.details;
                }
            }
            
            // Stocker le sessionId s'il est présent dans la réponse
            if (response.sessionId) {
                setSessionId(response.sessionId);
            }
            
            return formattedResponse;
        },
        onMutate: () => {
            // Initialiser l'état au début de la mutation
            setIsTimerPaused(false);
            setIsPauseTimerActive(false);
            setProgress(0);
        },
        onSuccess: (response) => {
            // S'assurer que response.messages est un tableau
            if (!response.messages || !Array.isArray(response.messages)) {
                console.warn('response.messages n\'est pas un tableau dans onSuccess, initialisation à []');
                response.messages = [];
            }
            
            // Préserver les propriétés spéciales des messages comme showMailto
            if (response.messages && Array.isArray(response.messages)) {
                response.messages = response.messages.map(msg => ({
                    ...msg,
                    // Conserver showMailto s'il existe
                    showMailto: msg.showMailto
                }));
            }
            
            // Vérifier si l'ODF est déjà clôturé
            const isClosedOdf = 
                (response.isClosed === true) || 
                (response.details && 
                !Array.isArray(response.details) && 
                'messages' in response.details && 
                Array.isArray(response.details.messages) &&
                response.details.messages.some(msg => 
                    msg.message && (
                        msg.message.includes('déjà clôturé') || 
                        msg.message.includes('ODF est déjà clôturé') ||
                        msg.message.includes('Cet ODF est déjà clôturé')
                    )
                )) ||
                (response.messages && 
                Array.isArray(response.messages) && 
                response.messages.some(msg => 
                    msg.message && (
                        msg.message.includes('déjà clôturé') || 
                        msg.message.includes('ODF est déjà clôturé') ||
                        msg.message.includes('Cet ODF est déjà clôturé')
                    )
                ));
            
            if (isClosedOdf) {
                // Forcer le statut à error et isClosed à true
                response.status = 'error';
                response.isClosed = true;
                
                // Si les détails contiennent des messages, les utiliser comme messages principaux
                if (response.details && 
                    !Array.isArray(response.details) && 
                    'messages' in response.details && 
                    Array.isArray(response.details.messages)) {
                    response.messages = response.details.messages;
                }
            }
            // S'assurer qu'il y a au moins un message de succès si le statut est success
            else if (response.status === 'success' && (!response.messages || response.messages.length === 0)) {
                response.messages = [{
                    type: 'success',
                    message: 'Vérification réussie',
                    status: 'success'
                }];
            }
            
            if (response.status === 'error') {
                setProgress(0);
                setCurrentStep(1);
                setHasError(true);
                setIsTimerPaused(true);
                setIsPauseTimerActive(false);
            } else {
                // Mise à jour du progrès en fonction de la réponse de l'API
                setCurrentStep(1);
                setProgress(15); // Étape 1 complétée
                
                // Activer le timer de pause pour la première étape
                setIsTimerPaused(true);
                setIsPauseTimerActive(true);
            }
            
            // Transformer les détails si nécessaire
            const processedResponse = processVerificationResults(response);

            // Stocker les résultats de la vérification
            setVerificationResults(processedResponse);
        }
    });

    const getOrderMutation = useMutation({
        mutationFn: async () => {
            if (!params?.id || !params?.pcdnum || !params?.user) {
                throw new Error('Paramètres manquants pour la récupération de la commande');
            }

            const apiParams: Record<string, string> = {
                pcdid: params.id,
                pcdnum: params.pcdnum,
                user: params.user
            };
            
            // Ajouter le sessionId s'il existe
            if (sessionId) {
                apiParams.sessionId = sessionId;
            }

            return await api.get<ApiResponse>(`/api/odf/get-order`, {
                params: apiParams
            });
        },
        onMutate: () => {
            setCurrentStep(3);
            setProgress(30);
        },
        onSuccess: (data: ApiResponse) => {
            // S'assurer que data.messages est un tableau
            if (!data.messages || !Array.isArray(data.messages)) {
                data.messages = [];
            }
            
            // Stocker le sessionId s'il est présent dans la réponse
            if (data.sessionId) {
                setSessionId(data.sessionId);
            }
            
            // Vérifier si nous avons un message de type "retry"
            if (data.status === 'pending' && data.retry) {
                // S'assurer que verificationResults.messages est un tableau
                const currentMessages = verificationResults?.messages && Array.isArray(verificationResults.messages) 
                    ? verificationResults.messages 
                    : [];

                // Mettre à jour les messages pour indiquer la tentative en cours
                setVerificationResults(prev => ({
                    ...prev!,
                    status: 'processing',
                    messages: [
                        ...currentMessages.filter(msg => msg.type !== 'info' && msg.type !== 'retry'),
                        {
                            type: 'retry',
                            message: data.message || `Tentative ${data.retryCount}/${data.maxRetries}`,
                            status: 'pending'
                        } as ResponseMessage
                    ]
                }));

                // Incrémenter le compteur de tentatives
                window._retryCount = data.retryCount;
                
                // Relancer après un délai
                setTimeout(() => {
                    getOrderMutation.mutate();
                }, 2000);
                
                return;
            }
            
            if (data.status === 'error') {
                setProgress(0);
                setCurrentStep(1);
                setHasError(true);
                setIsTimerPaused(true);
                setIsPauseTimerActive(false);
                setIsLoading(false);
                setVerificationResults(prev => ({
                    ...prev!,
                    status: 'error',
                    messages: [{
                        type: 'error',
                        message: data.message || 'Erreur lors de la récupération de la commande',
                        status: 'error'
                    }]
                }));
            } else {
                setProgress(45);
                setCurrentStep(4);
                
                // Ajouter un message pour indiquer que nous passons à l'étape 4
                setVerificationResults(prev => ({
                    ...prev!,
                    status: 'processing',
                    messages: [
                        {
                            type: 'info',
                            message: 'Récupération des informations d\'activation en cours...',
                            status: 'info'
                        }
                    ]
                }));

                // Extraire les détails de la commande
                let orderDetails: DetailItem[] = [];
                if (data.data?.eventDataGetOrder?.orderHdr?.items) {
                    orderDetails = data.data.eventDataGetOrder.orderHdr.items.map(item => {
                        // Transformer les données pour correspondre à l'interface VerificationDetail
                        return {
                            ligne: item.ligne,
                            article: typeof item.article === 'string' ? {
                                code: item.article,
                                designation: item.designation || '',
                                coupon: item.coupon || null,
                                eligible: true,
                                hasCoupon: !!item.coupon
                            } : item.article,
                            quantite: item.quantite,
                            serie: typeof item.n_serie === 'string' ? {
                                numero: item.n_serie,
                                status: 'success',
                                message: 'Numéro de série valide',
                                message_api: null,
                                manufacturerModel: item.modele || null
                            } : item.serie,
                            date_end_subs: item.date_fin || '',
                            n_serie: item.n_serie,
                            designation: item.designation,
                            modele: item.modele,
                            date_fin: item.date_fin
                        };
                    });
                }
                
                // Ne pas désactiver le chargement ici car on enchaîne avec getActivationMutation
                setVerificationResults(prev => {
                    // S'assurer que prev existe et que prev.messages est un tableau
                    const prevMessages = prev && prev.messages && Array.isArray(prev.messages) 
                        ? prev.messages 
                        : [];
                    
                    // Créer un message de succès explicite pour l'étape de récupération de commande
                    const successMessage = {
                        type: 'success' as MessageType,
                        message: data.message || `Commande Trimble N°: ${data.data?.orderNumber} récupérée avec succès.`,
                        status: 'success' as MessageStatus
                    };

                    return {
                        ...(prev || { isClosed: false }),
                        status: 'processing' as ResponseStatus,
                        messages: [
                            ...prevMessages.filter(msg => msg.type !== 'info' && msg.type !== 'retry'),
                            successMessage
                        ],
                        details: orderDetails.length > 0 ? orderDetails : prev?.details,
                        data: {
                            ...(prev?.data || {}),
                            orderNumber: data.data?.orderNumber,
                            eventDataGetOrder: data.data?.eventDataGetOrder
                        },
                        isClosed: false
                    } as CheckResponse;
                });
                
                // Récupérer le numéro de commande depuis les données
                const orderNumber = data.data?.orderNumber;
                if (orderNumber) {
                    getActivationMutation.mutate(orderNumber);
                } else {
                    // Gérer le cas où le numéro de commande est manquant
                    setVerificationResults(prev => ({
                        ...prev!,
                        status: 'error',
                        messages: [...(prev?.messages || []), {
                            type: 'error',
                            message: 'Numéro de commande manquant pour la récupération des informations d\'activation',
                            status: 'error'
                        }]
                    }));
                    setHasError(true);
                    setIsLoading(false);
                }
            }
        },
        onError: (error: Error) => {
            setProgress(30);
            setCurrentStep(3);
            setHasError(true);
            setIsTimerPaused(true);
            setIsPauseTimerActive(true);
            setIsLoading(false);
            setVerificationResults(prev => ({
                ...prev!,
                status: 'error',
                messages: [{
                    type: 'error',
                    message: error.message,
                    status: 'error'
                }]
            }));
        }
    });

    const createOrderMutation = useMutation({
        mutationFn: async () => {
            // Marquer comme en cours de chargement
            setIsLoading(true);
            
            if (!params?.id || !params?.pcdnum || !params?.user) {
                throw new Error('Paramètres manquants pour la création de la commande');
            }

            const apiParams: Record<string, string> = {
                pcdid: params.id,
                pcdnum: params.pcdnum,
                user: params.user
            };
            
            // Ajouter le sessionId s'il existe
            if (sessionId) {
                apiParams.sessionId = sessionId;
            }

            return await api.get<ApiResponse>(`/api/odf/create-order`, {
                params: apiParams
            });
        },
        onMutate: () => {
            setCurrentStep(2);
            setProgress(15);
            setHasError(false);
        },
        onSuccess: (data: ApiResponse) => {
            // Stocker le sessionId s'il est présent dans la réponse
            if (data.sessionId) {
                setSessionId(data.sessionId);
            }
            
            // Vérifier s'il y a des erreurs dans les détails, même si le statut global est "success"
            if (data.status === 'success' && 
                data.details && 
                !Array.isArray(data.details) && 
                data.details.status === 'error') {
                
                console.log("Erreur détectée dans les détails de création de commande:", data);
                
                // Forcer le statut à error
                data.status = 'error';
                
                // Si les détails contiennent des messages d'erreur, les utiliser comme messages principaux
                if (data.details.messages && Array.isArray(data.details.messages)) {
                    // Filtrer pour éviter les doublons d'erreurs API Trimble
                    const trimbleErrorMessages = data.details.messages.filter(msg => 
                        msg.message && msg.message.includes('Erreur API Trimble')
                    );
                    
                    if (trimbleErrorMessages.length > 1) {
                        const nonTrimbleErrors = data.details.messages.filter(msg => 
                            !msg.message || !msg.message.includes('Erreur API Trimble')
                        );
                        
                        // Marquer explicitement les messages comme des erreurs de création
                        data.messages = [
                            ...nonTrimbleErrors,
                            trimbleErrorMessages[0] // Ne garder que la première erreur API Trimble
                        ].map(msg => ({
                            ...msg,
                            type: msg.type || 'error',
                            status: msg.status || 'error',
                            isCreationError: true
                        }));
                    } else {
                        // Marquer explicitement les messages comme des erreurs de création
                        data.messages = data.details.messages.map(msg => ({
                            ...msg,
                            type: msg.type || 'error',
                            status: msg.status || 'error',
                            isCreationError: true
                        }));
                    }
                }
            }
            
            // Traiter la réponse avec processVerificationResults
            const processedData = processVerificationResults(data);
            
            if (processedData.status === 'error') {
                setProgress(0);
                setCurrentStep(1);
                setHasError(true);
                setIsTimerPaused(true);
                setIsPauseTimerActive(false);
                setIsLoading(false);
                setVerificationResults(prev => ({
                    ...prev!,
                    status: 'error',
                    messages: processedData.messages || [{
                        type: 'error',
                        message: processedData.message || 'Erreur lors de la création de la commande',
                        status: 'error',
                        isCreationError: true
                    }]
                }));
            } else {
                setProgress(30);
                setCurrentStep(3);
                // Ne pas désactiver le chargement ici car on enchaîne avec getOrderMutation
                setVerificationResults(prev => ({
                    ...prev!,
                    status: 'processing',
                    messages: processedData.messages || [{
                        type: 'success',
                        message: 'Commande créée avec succès',
                        status: 'success'
                    }]
                }));
                getOrderMutation.mutate();
            }
            queryClient.invalidateQueries({queryKey: ['odf', params?.id]}).then(r => r);
        },
        onError: (error: Error) => {
            setProgress(0);
            setCurrentStep(1);
            setHasError(true);
            setIsTimerPaused(true);
            setIsPauseTimerActive(false);
            setIsLoading(false);
            setVerificationResults(prev => ({
                ...prev!,
                status: 'error',
                messages: [{
                    type: 'error',
                    message: error.message,
                    status: 'error'
                }]
            }));
        }
    });

    const getActivationMutation = useMutation({
        mutationFn: async (orderNumber: string) => {
            setIsLoading(true);
            
            if (!params?.id || !params?.user) {
                throw new Error('Paramètres manquants pour la récupération des informations d\'activation');
            }
            
            const retryCount = window._retryCount || 0;
            
            const apiParams: Record<string, string> = {
                pcdid: params.id,
                user: params.user,
                orderNumber: orderNumber,
                retryCount: retryCount.toString(),
                pcdnum: params.pcdnum || ''
            };
            
            if (sessionId) {
                apiParams.sessionId = sessionId;
            }

            // Mettre à jour le message après une minute
            setVerificationResults(prev => ({
                ...prev!,
                messages: [
                    ...prev!.messages.filter(msg => msg.type !== 'info'),
                    {
                        type: 'info',
                        message: 'Récupération activation en cours...',
                        status: 'info'
                    }
                ]
            }));
            
            const response = await api.get<ApiResponse>(`/api/odf/get-activation`, {
                params: apiParams
            });

            if (response.status === 'error') {
                const errorMessage = response.messages && response.messages.length > 0 
                    ? response.messages[0].message 
                    : (response.message || 'Erreur lors de la récupération des informations d\'activation');
                
                throw new Error(errorMessage);
            }

            return response;
        },
        onMutate: () => {
            setIsLoading(true);
            setProgress(60);
            setCurrentStep(4);
            setHasError(false);
            
            // Ajouter un message pour indiquer que nous récupérons les informations d'activation
            // tout en conservant les messages de succès précédents
            setVerificationResults(prev => {
                if (!prev) return {
                    status: 'processing' as ResponseStatus,
                    messages: [{
                        type: 'info' as MessageType,
                        message: 'Récupération des informations d\'activation en cours...',
                        status: 'info' as MessageStatus
                    }],
                    isClosed: false
                } as CheckResponse;
                
                // Filtrer pour conserver les messages de succès de l'étape précédente
                const successMessages = prev.messages && Array.isArray(prev.messages) 
                    ? prev.messages.filter(msg => msg.type === 'success') 
                    : [];
                
                return {
                    ...prev,
                    status: 'processing' as ResponseStatus,
                    messages: [
                        ...successMessages,
                        {
                            type: 'info' as MessageType,
                            message: 'Récupération des informations d\'activation en cours...',
                            status: 'info' as MessageStatus
                        }
                    ]
                } as CheckResponse;
            });
        },
        onSuccess: (data: ApiResponse) => {
            // Stocker le sessionId s'il est présent dans la réponse
            if (data.sessionId) {
                setSessionId(data.sessionId);
            }
            
            setIsLoading(false);
            
            // Ajouter le traitement des retries ici
            if (data.status === 'pending' && data.retry) {
                // Mettre à jour les messages pour indiquer la tentative en cours
                setVerificationResults(prev => ({
                    ...prev!,
                    status: 'processing',
                    messages: [
                        ...prev!.messages.filter(msg => msg.type !== 'info' && msg.type !== 'retry'),
                        {
                            type: 'retry',
                            message: data.message || `Tentative ${data.retryCount}/${data.maxRetries}`,
                            status: 'pending'
                        } as ResponseMessage
                    ]
                }));

                // Incrémenter le compteur de tentatives
                window._retryCount = data.retryCount;
                
                // Relancer après un délai
                setTimeout(() => {
                    const orderNumber = verificationResults?.data?.orderNumber;
                    if (orderNumber) {
                        getActivationMutation.mutate(orderNumber);
                    }
                }, 2000);
                
                return;
            }

            setCurrentStep(5);
            setProgress(75);
            
            // Vérifier si les données d'activation sont présentes
            if (data.data && data.data.activationDetails && data.data.activationDetails.length > 0) {
                // Récupérer les messages de succès précédents
                const previousSuccessMessages = verificationResults?.messages.filter(msg => msg.type === 'success') || [];
                
                // Créer un nouvel objet pour éviter les problèmes de référence
                const newResults = {
                    ...verificationResults!,
                    status: 'success' as ResponseStatus,
                    messages: [
                        ...previousSuccessMessages,
                        {
                            type: 'success' as MessageType,
                            message: 'Informations d\'activation récupérées avec succès',
                            status: 'success' as MessageStatus
                        }
                    ],
                    data: {
                        ...verificationResults!.data,
                        activationDetails: data.data.activationDetails.map((item: ActivationDetailApiResponse): ActivationDetail => ({
                            partNumber: item.artcode || item.article || '',
                            partDescription: item.partDescription || item.designation || '',
                            serialNumber: item.serialNumber || item.n_serie || '',
                            passcode: item.passcode || '',
                            activationDate: item.serviceStartDate || item.activation_date || '',
                            expirationDate: item.serviceEndDate || item.expiration_date || '',
                            status: item.passcodeActivationStatus || item.status || '',
                            isQR: item.isQR || '',
                            passcodeFileType: item.passcodeFileType || '',
                            passcodeFileContent: item.passcodeFileContent || undefined,
                            // Conserver les champs originaux
                            serviceStartDate: item.serviceStartDate,
                            serviceEndDate: item.serviceEndDate,
                            qrCodeLink: item.qrCodeLink,
                            artcode: item.artcode
                        }))
                    }
                } as CheckResponse;
                
                // Mettre à jour l'état avec le nouvel objet
                setVerificationResults(newResults);
                
                // Lancer automatiquement la création du BDF après un court délai
                setTimeout(() => {
                    createBdfMutation.mutate();
                }, 1500);
            } else {
                // Gérer le cas où aucune donnée d'activation n'est disponible
                // Récupérer les messages de succès précédents
                const previousSuccessMessages = verificationResults?.messages.filter(msg => msg.type === 'success') || [];
                
                setVerificationResults(prev => ({
                    ...prev!,
                    status: 'error',
                    messages: [
                        ...previousSuccessMessages,
                        {
                            type: 'error',
                            message: 'Aucune information d\'activation disponible',
                            status: 'error'
                        }
                    ]
                }));
                setHasError(true);
            }
        },
        onError: (error: Error) => {
            setIsLoading(false);
            setHasError(true);
            setProgress(45);
            setCurrentStep(4);
            
            // Récupérer les messages de succès précédents
            const previousSuccessMessages = verificationResults?.messages.filter(msg => msg.type === 'success') || [];
            
            // Conserver les détails de la commande et le numéro de commande
            setVerificationResults(prev => {
                // Ne pas modifier les détails ni le numéro de commande
                return {
                    ...prev!,
                    status: 'error',
                    messages: [
                        ...previousSuccessMessages,
                        {
                            type: 'error',
                            message: error.message,
                            status: 'error'
                        }
                    ],
                    // Conserver les détails de la commande
                    details: prev?.details || []
                };
            });
        },
        onSettled: () => {
            setIsLoading(false);
        }
    });

    const createBdfMutation = useMutation({
        mutationFn: async () => {
            setIsLoading(true);
            
            if (!params?.id || !params?.user) {
                throw new Error('Paramètres manquants pour la création du bon de fabrication');
            }
            
            const orderNumber = verificationResults?.data?.orderNumber;
            if (!orderNumber) {
                throw new Error('Numéro de commande manquant pour la création du bon de fabrication');
            }
            
            const activationResult = verificationResults?.data?.activationDetails ?? [];
            
            const response = await api.post<ApiResponse>('/api/odf/create-bdf', {
              pcdid: params.id,
              user: params.user,
              pcdnum: params.pcdnum,
              orderNumber: orderNumber,
              activationResult: activationResult
            });

            if (response.status === 'error') {
                // Récupérer le message d'erreur depuis le premier message si disponible
                const errorMessage = response.messages && response.messages.length > 0 
                    ? response.messages[0].message 
                    : (response.message || 'Erreur lors de la création du bon de fabrication');
                
                throw new Error(errorMessage);
            }

            return response;
        },
        onMutate: () => {
            setIsLoading(true);
            setProgress(75);
            setCurrentStep(5);
            setHasError(false);
            setIsTimerPaused(false);
            setIsPauseTimerActive(false);
            
            // Mettre à jour les messages pour indiquer que la création du BDF est en cours
            setVerificationResults(prev => ({
                ...prev!,
                status: 'processing',
                messages: [{
                    type: 'retry',
                    message: 'Création du bon de fabrication en cours...',
                    status: 'pending'
                }]
            }));
        },
        onSuccess: () => {
            setIsLoading(false);
            setCurrentStep(6);
            setProgress(100);
            
            // Activer les confettis
            setShowConfetti(true);
            
            // Arrêter le timer et le figer
            setIsTimerPaused(true);
            setIsPauseTimerActive(false);
            
            // Figer le temps écoulé pour qu'il ne change plus
            const finalElapsedTime = elapsedTime;
            const finalPauseTime = pauseTime;
            
            // Remplacer les fonctions de mise à jour du temps pour qu'elles ne fassent rien
            setElapsedTime(finalElapsedTime);
            setPauseTime(finalPauseTime);
            
            // Envoyer les temps d'exécution au backend
            if (params?.pcdnum) {
                api.post('/api/odf/save-execution-time', {
                    pcdnum: params.pcdnum,
                    user: params.user,
                    executionTime: finalElapsedTime,
                    executionTimePause: finalPauseTime
                }).catch(error => {
                    console.error('Erreur lors de l\'enregistrement des temps d\'exécution:', error);
                });
            }
            
            // Arrêter les confettis après 10 secondes
            setTimeout(() => {
                setShowConfetti(false);
            }, 5000);
            
            setVerificationResults(prev => ({
                ...prev!,
                status: 'success' as ResponseStatus,
                messages: [{
                    type: 'success',
                    message: 'Bon de fabrication créé avec succès',
                    status: 'success'
                }],
            }));
        },
        onError: (error: Error) => {
            setIsLoading(false);
            setHasError(true);
            setProgress(75);
            setCurrentStep(5);
            
            // Conserver les détails de la commande et le numéro de commande
            setVerificationResults(prev => ({
                ...prev!,
                status: 'error',
                messages: [{
                    type: 'error',
                    message: error.message,
                    status: 'error',
                    showMailto: true
                }]
            }));
        }
    });

    useEffect(() => {
        if (params?.id) {
            checkOdf();
        }
    }, [params?.id]);

    useEffect(() => {
        const initializeParams = async () => {
            if (token) {
                try {
                    const response = await api.get<TokenResponse>(`/api/token/check/verify?token=${encodeURIComponent(token)}`);

                    if (!response.valid) {
                        throw new Error('Token invalide ou expiré');
                    }

                    // Vérifier si response.data existe
                    if (!response.data) {
                        throw new Error('Données du token manquantes');
                    }

                    // Utiliser directement les données décodées par le backend
                    const decodedParams = response.data as TokenParams;
                    
                    // Vérifier que tous les champs requis sont présents
                    if (!decodedParams.id || !decodedParams.pcdnum || !decodedParams.user) {
                        throw new Error('Champs requis manquants dans les données du token');
                    }
                    
                    if (decodedParams.exp < Date.now() / 1000) {
                        throw new Error('Token expiré');
                    }
                    setParams(decodedParams);
                } catch (error) {
                    console.error("Erreur complète lors de la vérification du token:", error);
                    toast({
                        title: "Erreur",
                        description: error instanceof Error ? error.message : "Une erreur est survenue",
                        variant: "destructive",
                    });
                }
            } else {
                console.warn("Aucun token disponible pour l'initialisation");
            }
        };

        initializeParams().then(r => r).catch(e => e);
    }, [token, toast]);

    const handleRetry = () => {
        const isCreationError = verificationResults?.messages.some(msg => msg.isCreationError === true);
        
        setHasError(false);
        
        if (currentStep === 4) {
            // Pour l'étape 4, on garde la progression
            setCurrentStep(4);
            setProgress(60); // Utiliser la progression correcte pour l'étape 4
            setIsTimerPaused(false);
            setIsPauseTimerActive(false);
            
            // Réinitialiser les messages mais garder le status
            setVerificationResults(prev => ({
                ...prev!,
                status: 'processing',
                messages: [{
                    type: 'retry',
                    message: 'Récupération des informations d\'activation en cours...',
                    status: 'pending'
                }]
            }));
            
            // Récupérer le orderNumber du résultat précédent
            const orderNumber = verificationResults?.data?.orderNumber;
            if (orderNumber) {
                // Réinitialiser le compteur de tentatives
                window._retryCount = 0;
                getActivationMutation.mutate(orderNumber);
            } else {
                setVerificationResults(prev => ({
                    ...prev!,
                    status: 'error',
                    messages: [{
                        type: 'error',
                        message: 'Numéro de commande manquant pour la récupération des informations d\'activation',
                        status: 'error'
                    }]
                }));
                setHasError(true);
            }
        } else if (currentStep === 3) {
            // Pour l'étape 3, on garde la progression et les étapes précédentes
            setCurrentStep(3);
            setProgress(30);
            setIsTimerPaused(false);
            setIsPauseTimerActive(false);
            
            // Réinitialiser les messages mais garder le status
            setVerificationResults(prev => ({
                ...prev!,
                status: 'processing',
                messages: [{
                    type: 'retry',
                    message: 'Récupération de la commande en cours...',
                    status: 'pending'
                }]
            }));
            
            getOrderMutation.mutate();
        } else if (isCreationError) {
            // Pour une erreur de création (étape 2), on garde les timers
            setIsTimerPaused(false);
            setIsPauseTimerActive(false);
            createOrderMutation.mutate();
        } else if (currentStep === 5) {
            // Pour l'étape 5, on garde la progression
            setCurrentStep(5);
            setProgress(75);
            setIsTimerPaused(false);
            setIsPauseTimerActive(false);
            
            // Réinitialiser les messages mais garder le status
            setVerificationResults(prev => ({
                ...prev!,
                status: 'processing',
                messages: [{
                    type: 'retry',
                    message: 'Création du bon de fabrication en cours...',
                    status: 'pending'
                }]
            }));
            
            createBdfMutation.mutate();
        } else {
            // Pour l'étape 1, on réinitialise tout et on relance
            setProgress(0);
            setCurrentStep(1);
            setElapsedTime(0);
            setPauseTime(0);
            setShouldResetTimer(prev => !prev);
            setVerificationResults(null);
            // Démarrer les timers et le loader
            setIsTimerPaused(false);
            setIsPauseTimerActive(false);
            // Réinitialiser l'état de création
            setIsCreatingOrder(false);
            // Lancer la vérification
            checkOdf();
        }
    };

    const handleCreateOrder = async () => {
        try {
            setIsLoading(true);
            
            // Appel API existant...
            const response = await api.post('/api/odf/create-manufacturing-order', {
                pcdid: verificationResults?.data?.pcdid,
                orderNumber: verificationResults?.data?.orderNumber,
                activationResult: verificationResults?.data?.activationDetails || []
            });
            
            // Vérifier si la réponse contient des messages et s'assurer qu'ils sont un tableau
            if (!response.data.messages || !Array.isArray(response.data.messages)) {
                console.warn('response.data.messages n\'est pas un tableau, initialisation à []');
                response.data.messages = [];
            }
            
            // Vérifier s'il y a des erreurs dans les détails, même si le statut global est "success"
            if (response.data.status === 'success' && 
                response.data.details && 
                response.data.details.status === 'error') {
                
                // Traiter comme une erreur si les détails contiennent des erreurs
                console.error("Erreur détectée dans les détails malgré un statut global success:", response.data);
                
                // S'assurer que les messages des détails sont un tableau
                const errorMessages = response.data.details.messages && Array.isArray(response.data.details.messages) 
                    ? response.data.details.messages 
                    : [];
                
                // Mettre à jour les résultats avec les messages d'erreur des détails
                setVerificationResults(prev => ({
                    ...prev!,
                    status: 'error' as ResponseStatus,
                    messages: [
                        ...errorMessages.map((msg: any) => ({
                            type: msg.type || 'error',
                            message: msg.message,
                            status: msg.status || 'error',
                            isCreationError: msg.isCreationError || true
                        }))
                    ]
                }));
                
                setIsLoading(false);
                setHasError(true);
                return;
            }
            
            if (response.data.status === 'success') {
                // S'assurer que verificationResults.messages est un tableau
                const currentMessages = verificationResults?.messages && Array.isArray(verificationResults.messages) 
                    ? verificationResults.messages 
                    : [];
                
                // Mettre à jour les résultats avec les nouveaux messages
                const newResults = {
                    ...verificationResults,
                    status: 'success' as ResponseStatus,
                    successfullyClosed: true,
                    isClosed: false,
                    messages: [
                        ...currentMessages,
                        ...response.data.messages.map((msg: ApiResponseMessage) => ({
                            type: msg.type,
                            message: msg.message,
                            status: msg.status,
                            isFinal: msg.isFinal,
                            showConfetti: msg.showConfetti
                        }))
                    ],
                    data: {
                        ...verificationResults?.data,
                        orderNumber: response.data.data.orderNumber
                    }
                } as CheckResponse;
                
                setVerificationResults(newResults);
            }
        } catch (error) {
            console.log('Erreur lors de la création de la commande', error);
        } finally {
            setIsLoading(false);
        }
    };

    const handleTimerResume = () => {
        setIsTimerPaused(false);
        setIsPauseTimerActive(false);
    };

    const handleTimeExceeded = () => {
        setIsTimerPaused(true);
        setIsPauseTimerActive(false);
        setHasError(true);
        setProgress(0);
        setCurrentStep(1);
        setVerificationResults(prev => prev ? {
            ...prev,
            status: 'error',
            messages: [{
                type: 'error',
                message: 'Temps de pause dépassé (12 minutes)',
                status: 'error'
            }]
        } : null);
    };

    const processVerificationResults = (response: ApiResponse): ApiResponse => {
        if (!response.messages || !Array.isArray(response.messages)) {
            response.messages = [];
        }
        
        // Vérifier si nous avons un message indiquant que l'ODF est déjà clôturé
        const isClosedOdf = response.details && 
            !Array.isArray(response.details) && 
            'messages' in response.details && 
            Array.isArray(response.details.messages) &&
            response.details.messages.some(msg => 
                msg.message && (
                    msg.message.includes('déjà clôturé') || 
                    msg.message.includes('ODF est déjà clôturé') ||
                    msg.message.includes('Cet ODF est déjà clôturé')
                )
        );
        
        // Si l'ODF est déjà clôturé, forcer le statut à error et isClosed à true
        if (isClosedOdf) {
            response.status = 'error';
            response.isClosed = true;
            
            // Si les détails contiennent des messages, les utiliser comme messages principaux
            if (response.details && 
                !Array.isArray(response.details) && 
                'messages' in response.details && 
                Array.isArray(response.details.messages)) {
                response.messages = response.details.messages;
            }
            
            // Sortir de la fonction immédiatement pour éviter tout autre traitement
            return response;
        }
        
        // S'assurer qu'il y a au moins un message de succès si le statut est success et qu'il n'y a pas de messages
        if (response.status === 'success' && response.messages.length === 0) {
            response.messages.push({
                type: 'success',
                message: 'Vérification réussie',
                status: 'success'
            });
        }
        
        // Vérifier si nous avons une erreur de création de commande (étape 2)
        // Cas où le statut global est "success" mais details.status est "error" et contient des messages avec isCreationError
        const hasCreationError = response.status === 'success' && 
            response.details && 
            !Array.isArray(response.details) && 
            response.details.status === 'error' &&
            response.details.messages && 
            Array.isArray(response.details.messages) &&
            response.details.messages.some(msg => msg.isCreationError === true || 
                (msg.message && msg.message.includes('Erreur API Trimble')));
        
        if (hasCreationError) {
            // Forcer le statut global à "error"
            response.status = 'error';
            
            // Remplacer les messages principaux par les messages d'erreur des détails
            if (response.details && 
                !Array.isArray(response.details) && 
                'messages' in response.details && 
                Array.isArray(response.details.messages)) {
                
                // Fusionner les messages en évitant les doublons
                const allMessages = [...response.messages];
                
                response.details.messages.forEach(newMsg => {
                    // Vérifier si un message similaire existe déjà
                    const isDuplicate = allMessages.some(existingMsg => 
                        existingMsg.message === newMsg.message || 
                        (newMsg.message?.includes('manufacturerModel') && 
                         existingMsg.message?.includes('manufacturerModel') &&
                         existingMsg.message?.includes(newMsg.message.split('produit:')[1]?.trim() || ''))
                    );
                    
                    if (!isDuplicate) {
                        allMessages.push(newMsg);
                    }
                });
                
                response.messages = allMessages;
            }
            
            // Sortir de la fonction immédiatement pour éviter tout autre traitement
            return response;
        }
        
        // Vérifier si les détails contiennent des erreurs, même si le statut global est "success"
        if (response.status === 'success' && 
            response.details && 
            !Array.isArray(response.details) && 
            response.details.status === 'error') {

            // Remonter le statut d'erreur au niveau principal
            response.status = 'error';
            
            // Si les détails contiennent des messages d'erreur, les ajouter aux messages principaux
            if (response.details.messages && Array.isArray(response.details.messages)) {
                // Fusionner les messages en évitant les doublons
                const allMessages = [...response.messages];
                
                response.details.messages.forEach(newMsg => {
                    // Vérifier si un message similaire existe déjà
                    const isDuplicate = allMessages.some(existingMsg => 
                        existingMsg.message === newMsg.message || 
                        (newMsg.message?.includes('manufacturerModel') && 
                         existingMsg.message?.includes('manufacturerModel') &&
                         existingMsg.message?.includes(newMsg.message.split('produit:')[1]?.trim() || ''))
                    );
                    
                    if (!isDuplicate) {
                        allMessages.push(newMsg);
                    }
                });
                
                response.messages = allMessages;
            }
        }
        
        // Cas spécial où details contient un tableau details et d'autres propriétés
        if (response.details && 
            !Array.isArray(response.details) && 
            'details' in response.details && 
            Array.isArray(response.details.details)) {
            
            // Extraire les messages des détails si présents
            if ('messages' in response.details && Array.isArray(response.details.messages)) {
                // Ajouter les messages des détails aux messages principaux
                response.messages = [...response.messages, ...response.details.messages];
            }
            
            // Extraire le tableau details et le mettre au niveau supérieur
            const nestedDetails = response.details.details;
            response.details = nestedDetails;
        }
        
        // Cas où details contient des messages mais pas de tableau details
        else if (response.details && 
            !Array.isArray(response.details) && 
            'messages' in response.details && 
            Array.isArray(response.details.messages)) {
            
            // Si details.currentStep contient "ODF déjà validé", remplacer les messages principaux
            // par les messages de details.messages
            if (response.details.currentStep && 
                response.details.currentStep.includes('ODF déjà validé')) {
                
                if ((response.details as any).uniqueId && !response.uniqueId) {
                    response.uniqueId = (response.details as any).uniqueId;
                }
                
                // Remplacer les messages principaux par les messages de details.messages
                // pour afficher uniquement "L'ODF est déjà validé avec l'ID : XXXX"
                // et supprimer "Vérification réussie"
                response.messages = response.details.messages;
            }
            // Dans les autres cas, ajouter les messages des détails aux messages principaux
            else {
                response.messages = [...response.messages, ...response.details.messages];
            }
        }
        
        // Vérifier si nous avons un cas spécial : statut global "success" mais details.status "error" et isClosed true
        if (response.status === 'success' && response.details && 
            !Array.isArray(response.details) && 
            'status' in response.details && 
            response.details.status === 'error' && 
            response.details.isClosed === true) {
            
            // Remplacer le statut global par celui des détails
            response.status = 'error';
            response.isClosed = true;
            
            // Si les détails contiennent des messages, les ajouter aux messages principaux
            if ('messages' in response.details && Array.isArray(response.details.messages)) {
                response.messages = response.details.messages;
            }
        }
        
        // Si les détails existent et sont au format plat, les transformer
        if (response.details && Array.isArray(response.details)) {
            response.details = response.details.map(detail => {
                // Si le détail est déjà au bon format, le retourner tel quel
                if (detail.article && typeof detail.article === 'object') {
                    // S'assurer que les dates sont définies
                    if (!detail.date_end_subs && detail.date_fin) {
                        detail.date_end_subs = detail.date_fin;
                    }
                    return detail as DetailItem;
                }
                
                // Vérifier si nous sommes dans le cas d'une commande existante
                const isExistingOrder = window && window._isFromExistingOrder;
                
                // Pour une commande existante, ne pas générer de coupon automatiquement
                const coupon = isExistingOrder 
                    ? (detail.coupon || null) // Utiliser le coupon existant s'il existe
                    : `${detail.article}-C`; // Sinon, générer un coupon pour une nouvelle commande
                
                // Transformer le détail au format attendu
                const transformedDetail: DetailItem = {
                    ligne: detail.ligne || '1',
                    quantite: detail.quantite || 1,
                    article: {
                        code: detail.article as string || '',
                        designation: detail.designation || '',
                        coupon: coupon,
                        eligible: true,
                        hasCoupon: isExistingOrder ? !!coupon : true
                    },
                    serie: {
                        numero: detail.n_serie || '',
                        status: 'success',
                        message: 'Numéro de série valide',
                        message_api: null,
                        manufacturerModel: detail.modele || null
                    },
                    date_end_subs: detail.date_fin || '',
                    partDescription: detail.designation || '',
                    date_fin: detail.date_fin,
                    coupon: detail.coupon
                };
                
                return transformedDetail;
            });
        }
        
        return response;
    };
    const setIsCreatingOrder = (value: boolean) => {
        if (value) {
            console.log('Début de la création de commande');
        } else {
            console.log('Fin de la création de commande');
        }
    };

    const onProgressChange = (newProgress: number, step?: number) => {
        setProgress(newProgress);
        if (step) {
            setCurrentStep(step);
        }
    };

    const handleLaunchOrder = () => {
        // Pour lancer la commande après l'étape 1, on passe à l'étape 2
        setIsTimerPaused(false);
        setIsPauseTimerActive(false);
        setCurrentStep(2);
        setProgress(15);
        
        // Mettre à jour le statut pour indiquer que nous créons maintenant la commande
        setVerificationResults(prev => ({
            ...prev!,
            status: 'processing',
            messages: [{
                type: 'retry',
                message: 'Création de la commande en cours...',
                status: 'pending'
            }]
        }));
        
        createOrderMutation.mutate();
    };

    return (
        <div className="min-h-screen bg-slate-50 p-4">
            <div className="mx-auto space-y-6">
                {!params ? (
                    <InvalidTokenMessage />
                ) : (
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-2xl font-semibold text-blue-600">
                                Ordre de Fabrication {params.pcdnum}
                            </CardTitle>
                            <div className="text-xl text-gray-500">
                                {hasError ? (
                                    "Erreur lors de la vérification"
                                ) : isPauseTimerActive ? (
                                    `Prochaine étape : ${PROCESS_STEPS[currentStep - 1]?.name}`
                                ) : (
                                    `Étape actuelle : ${PROCESS_STEPS[currentStep - 1]?.name}`
                                )}
                            </div>
                            <div className="flex items-center gap-2 text-sm justify-center">
                                <Timer
                                    isPaused={isTimerPaused}
                                    shouldReset={shouldResetTimer}
                                    isActive={isPauseTimerActive}
                                    elapsedTime={elapsedTime}
                                    pauseTime={pauseTime}
                                    onElapsedTimeChange={setElapsedTime}
                                    onPauseTimeChange={setPauseTime}
                                    onTimeExceeded={handleTimeExceeded}
                                />
                            </div>
                        </CardHeader>
                        <CardContent>
                            <div className="space-y-6">
                                <VerificationProgress 
                                    progress={progress}
                                    isLoading={isPending || isLoading}
                                    currentStep={currentStep}
                                    steps={PROCESS_STEPS}
                                    isPaused={isTimerPaused || isPauseTimerActive}
                                    hasError={hasError}
                                    onProgressChange={onProgressChange}
                                />

                                {verificationResults?.uniqueId && !hasError && (
                                    <ExistingOrderDetails 
                                        uniqueId={verificationResults.uniqueId}
                                        pcdnum={params.pcdnum}
                                        setCurrentStep={setCurrentStep}
                                        setProgress={setProgress}
                                        getOrderMutation={getOrderMutation}
                                        setVerificationResults={setVerificationResults}
                                        setHasError={setHasError}
                                        setIsTimerPaused={setIsTimerPaused}
                                        setIsPauseTimerActive={setIsPauseTimerActive}
                                    />
                                )}

                                {verificationResults && (
                                    <VerificationContent 
                                        results={{
                                            ...verificationResults,
                                            data: verificationResults.data ? {
                                                orderNumber: verificationResults.data.orderNumber,
                                                activationDetails: transformActivationDetails(verificationResults.data.activationDetails)
                                            } : undefined
                                        }}
                                        pcdnum={params?.pcdnum || ''}
                                        onCreateOrder={handleCreateOrder}
                                        onRetry={handleRetry}
                                        onLaunchOrder={verificationResults.uniqueId ? undefined : handleLaunchOrder}
                                        onTimerResume={handleTimerResume}
                                        currentStep={currentStep}
                                    />
                                )}

                                <div className="mt-8 pt-6 border-t flex items-center justify-center">
                                    <div className="flex items-center gap-6">
                                        <VersionInfo />
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                )}
            </div>
            
            {/* Confettis */}
            {showConfetti && (
                <Confetti
                    width={window.innerWidth}
                    height={window.innerHeight}
                    recycle={false}
                    numberOfPieces={1000}
                    gravity={0.1}
                />
            )}
        </div>
    );
}
