import { Badge } from '@/components/ui/badge';
import { AlertTriangle, Ticket, Sparkles, ChevronDown, ChevronUp } from 'lucide-react';
import React, { useState, useEffect } from 'react';
import { ActivationDetails } from './ActivationDetails';

interface VerificationDetail {
    ligne: string | number;
    article: {
        code: string;
        designation: string;
        coupon: string | null;
        eligible: boolean;
        hasCoupon?: boolean;
    } | string;
    quantite: number | string;
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
    lastEnterStockDate?: string | null;
    isLastEnterStockDateValid?: boolean;
    refPiece?: string | null;
    estimate_start_date?: string;
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

interface VerificationTableProps {
    details: VerificationDetail[];
    currentStep?: number;
    activationDetails?: ActivationDetail[];
    isExistingOrder?: boolean;
}

// Fonction utilitaire pour formater les nombres
const formatNumber = (num: number | string |undefined): string => {
    if (num === undefined) return '';
    
    // Convertir en nombre pour s'assurer que c'est bien un nombre
    const value = Number(num);
    
    // Si c'est un entier, retourner simplement l'entier
    if (Number.isInteger(value)) {
        return value.toString();
    }
    
    // Sinon, formater avec parseFloat pour supprimer les zéros inutiles
    return parseFloat(value.toString()).toString();
};

// Fonction utilitaire pour formater les dates
const formatDate = (dateStr: string | null | undefined): string => {
    if (!dateStr) return 'Non disponible';
    try {
        // Vérifier si la date est au format MM/DD/YYYY
        if (dateStr.includes('/')) {
            const [day, month, year] = dateStr.split('/');
            return `${day}/${month}/${year}`;
        }
        
        const date = new Date(dateStr);
        if (isNaN(date.getTime())) {
            return dateStr;
        }
        
        return date.toLocaleDateString('fr-FR');
    } catch (e) {
        return dateStr;
    }
};

// Fonction pour comparer les numéros de série de manière flexible
const serialNumbersMatch = (serial1: string, serial2: string): boolean => {
    if (!serial1 || !serial2) return false;
    
    const cleanSerial1 = serial1.replace(/[\s\-.]/g, '').toLowerCase();
    const cleanSerial2 = serial2.replace(/[\s\-.]/g, '').toLowerCase();
    
    return cleanSerial1.includes(cleanSerial2) || cleanSerial2.includes(cleanSerial1);
};

export function VerificationTable({ 
    details, 
    currentStep = 1, 
    activationDetails = [],
    isExistingOrder = false
}: VerificationTableProps) {

    const isStep3OrHigher = currentStep >= 3;
    const isStep4OrHigher = currentStep >= 4;

    useEffect(() => {
    }, [isStep4OrHigher, activationDetails, details]);

    // À l'étape 4, toutes les lignes avec activations sont automatiquement développées
    const [expandedRows, setExpandedRows] = useState<Set<string>>(() => {
        const serials = new Set<string>();
        if (isStep4OrHigher) {
            activationDetails.forEach(act => {
                if (act.serialNumber) {
                    serials.add(act.serialNumber);
                }
            });
        }
        return serials;
    });
    
    // Mettre à jour expandedRows quand le currentStep ou les activationDetails changent
    useEffect(() => {
        if (isStep4OrHigher) {
            const serials = new Set<string>();
            activationDetails.forEach(act => {
                if (act.serialNumber) {
                    serials.add(act.serialNumber);
                }
            });
            setExpandedRows(serials);
        }
    }, [isStep4OrHigher, activationDetails]);
    
    const toggleRowExpansion = (serialNumber: string) => {
        // À l'étape 4, ne pas permettre de masquer les activations
        if (isStep4OrHigher) return;
        
        const newExpandedRows = new Set(expandedRows);
        if (newExpandedRows.has(serialNumber)) {
            newExpandedRows.delete(serialNumber);
        } else {
            newExpandedRows.add(serialNumber);
        }
        setExpandedRows(newExpandedRows);
    };
    
    const safeDetails = details.map(detail => {
        if (!detail.article || typeof detail.article !== 'object') {
            detail.article = {
                code: detail.article as string || '',
                designation: detail.designation || '',
                coupon: null,
                eligible: true,
                hasCoupon: false
            };
        }
        
        if (!detail.serie || typeof detail.serie !== 'object') {
            detail.serie = {
                numero: detail.n_serie || '',
                status: 'success',
                message: 'Numéro de série valide',
                message_api: null,
                manufacturerModel: detail.modele || null
            };
        }
        
        return detail;
    });

    return (
        <div className="overflow-x-auto">
            <table className="w-full border-collapse">
                <thead>
                    <tr className="bg-gray-50">
                        <th className="p-3 text-center text-sm font-medium text-gray-600">Ligne</th>
                        <th className="p-3 text-center text-sm font-medium text-gray-600">Article</th>
                        <th className="p-3 text-center text-sm font-medium text-gray-600">Désignation</th>
                        <th className="p-3 text-center text-sm font-medium text-gray-600">Quantité</th>
                        <th className="p-3 text-center text-sm font-medium text-gray-600">N° Série</th>
                        <th className="p-3 text-center text-sm font-medium text-gray-600">
                            {isStep3OrHigher ? (
                                <div className="relative inline-flex items-center justify-center">
                                    <div className="absolute inset-0 animate-pulse bg-gradient-to-r from-pink-400 via-yellow-400 to-purple-400 blur-md rounded-full w-full h-full"></div>
                                    <div className="relative z-10 flex items-center justify-center gap-3">
                                        <Sparkles className="w-6 h-6 text-amber-500" />
                                        <span>Nouvelle date de fin d'abonnement</span>
                                        <Sparkles className="w-6 h-6 text-amber-500" />
                                    </div>
                                </div>
                            ) : (
                                "Date de fin d'abonnement"
                            )}
                            <br />
                            <span className="text-xs text-gray-500">(source Trimble)</span>
                        </th>
                        {currentStep < 3 && (
                            <th className="p-3 text-center text-sm font-medium text-gray-600">Dernière entrée en stock</th>
                        )}
                    </tr>
                </thead>
                <tbody>
                    {safeDetails.map((detail, index) => {
                        const rowKey = `${detail.ligne}-${typeof detail.article === 'object' ? detail.article.code : ''}-${index}`;
                        const errorKey = `error-${rowKey}`;
                        const serialNumber = typeof detail.serie === 'object' ? detail.serie.numero : '';
                        
                        // Vérifier si ce numéro de série a des activations avec la fonction de comparaison flexible
                        const matchingActivations = activationDetails.filter(act => 
                            serialNumbersMatch(act.serialNumber, serialNumber)
                        );
                        
                        const hasActivations = isStep4OrHigher && matchingActivations.length > 0;
                        // Toujours considérer la ligne comme développée si nous avons des activations
                        const isExpanded = hasActivations ? true : expandedRows.has(serialNumber);
                        
                        return (
                            <React.Fragment key={rowKey}>
                                <tr 
                                    className={`border-b ${
                                        typeof detail.serie === 'object' && detail.serie.status === 'error' 
                                            ? 'bg-red-50 border-red-100' 
                                            : 'bg-green-50 border-green-100'
                                    } ${hasActivations ? 'cursor-pointer hover:bg-green-100' : ''}`}
                                    onClick={hasActivations ? () => toggleRowExpansion(serialNumber) : undefined}
                                >
                                    <td className="p-3 text-right">{detail.ligne}</td>
                                    <td className="p-3 text-left">
                                        <div className="flex flex-col gap-1">
                                            <div className="inline-flex">
                                                <Badge 
                                                    variant={typeof detail.article === 'object' && detail.article.eligible ? "success" : "destructive"}
                                                    className="whitespace-nowrap"
                                                >
                                                    {typeof detail.article === 'object' ? detail.article.code : detail.article}
                                                </Badge>
                                            </div>
                                            {typeof detail.article === 'object' && detail.article.coupon && (
                                                <div className="flex items-center gap-1 text-xs text-gray-500">
                                                    <Ticket className="h-3 w-3" />
                                                    <span>{detail.article.coupon}</span>
                                                </div>
                                            )}
                                        </div>
                                    </td>
                                    <td className="p-3 text-left">{typeof detail.article === 'object' ? detail.article.designation : detail.designation}</td>
                                    <td className="p-3 text-right">{formatNumber(detail.quantite)}</td>
                                    <td className="p-3 text-left">
                                        <div className="flex flex-col gap-1">
                                            {typeof detail.serie === 'object' && detail.serie.numero ? (
                                                <>
                                                    <div className="inline-flex">
                                                        <Badge 
                                                            variant={detail.serie.status === 'error' ? "destructive" : "success"}
                                                            className="whitespace-nowrap"
                                                        >
                                                            {detail.serie.numero}
                                                        </Badge>
                                                        {hasActivations && !isStep4OrHigher && (
                                                            <span className="ml-2 text-blue-600 text-xs flex items-center gap-1">
                                                                {isExpanded ? <ChevronUp size={14} /> : <ChevronDown size={14} />}
                                                                {matchingActivations.length} activation(s)
                                                            </span>
                                                        )}
                                                        {hasActivations && isStep4OrHigher && (
                                                            <span className="ml-2 text-blue-600 text-xs font-medium bg-blue-50 px-2 py-0.5 rounded-full">
                                                                {matchingActivations.length} activation(s)
                                                            </span>
                                                        )}
                                                    </div>
                                                    {detail.serie.manufacturerModel && (
                                                        <span className="text-xs text-gray-500 italic">
                                                            Modèle (Source Trimble) : {detail.serie.manufacturerModel}
                                                        </span>
                                                    )}
                                                </>
                                            ) : (
                                                <div className="inline-flex">
                                                    <Badge variant="warning" className="whitespace-nowrap">
                                                        Non renseigné
                                                    </Badge>
                                                </div>
                                            )}
                                        </div>
                                    </td>
                                    <td className="p-3 text-center">
                                        <div className="flex flex-col">
                                            <span>
                                                {currentStep === 3 ? 'En cours de récupération...' : formatDate(detail.date_end_subs)}
                                            </span>
                                            {detail.partDescription && currentStep !== 3 && (
                                                <span className="text-xs text-gray-500 italic">
                                                    {detail.partDescription}
                                                </span>
                                            )}
                                        </div>
                                    </td>
                                    {currentStep < 3 && (
                                        <td className="p-3 text-center">
                                            {detail.isLastEnterStockDateValid ? (
                                                <Badge variant="destructive" className="text-xs">
                                                    {formatDate(detail.lastEnterStockDate)}
                                                </Badge>
                                            ) : (
                                                formatDate(detail.lastEnterStockDate)
                                            )}

                                            {detail.isLastEnterStockDateValid && (
                                                <div className="flex items-center justify-center gap-1 text-xs text-red-600 font-semibold">
                                                    <AlertTriangle size={12} className="text-red-600" />
                                                    Dernière entrée datant de moins de 10 mois
                                                </div>
                                            )}

                                            {!detail.isLastEnterStockDateValid && <br />}

                                            <span className="text-xs text-gray-500 italic">
                                                (ref. pièce : {detail.refPiece})
                                            </span>
                                        </td>
                                    )}
                                </tr>
                                
                                {hasActivations && isExpanded && (
                                    <tr>
                                        <td colSpan={6} className="border-b border-gray-200 p-0">
                                            <div className="px-4 py-2 bg-gray-50">
                                                {(() => {
                                                    const matchingActivations = activationDetails.filter(activation => 
                                                        serialNumbersMatch(activation.serialNumber, serialNumber)
                                                    );
                                                    
                                                    return matchingActivations.length > 0 ? (
                                                        <ActivationDetails 
                                                            activations={matchingActivations} 
                                                            serialNumber={serialNumber} 
                                                        />
                                                    ) : (
                                                        <p className="text-sm text-gray-500 py-2">Aucune activation trouvée pour ce numéro de série.</p>
                                                    );
                                                })()}
                                            </div>
                                        </td>
                                    </tr>
                                )}
                                
                                {typeof detail.serie === 'object' && detail.serie.message_api && (
                                    <tr key={errorKey} className="bg-red-50">
                                        <td colSpan={6} className="p-3 text-sm text-red-700">
                                            <div className="flex items-center gap-2">
                                                {detail.serie.message_api}
                                            </div>
                                        </td>
                                    </tr>
                                )}
                            </React.Fragment>
                        );
                    })}
                </tbody>
            </table>
        </div>
    );
} 