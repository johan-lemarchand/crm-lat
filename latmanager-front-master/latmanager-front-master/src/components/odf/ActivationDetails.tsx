import {Button} from '@/components/ui/button';
import {Copy, FileDown, QrCode} from 'lucide-react';
import {toast} from '@/components/ui/use-toast';

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
    passcodeFileContent?: {
        sn: string;
        codeList: string[];
    };
    passcodeFileType?: string;
    artcode?: string;
}

interface ActivationDetailsProps {
    activations: ActivationDetail[];
    serialNumber: string;
}

// Fonction pour formater les dates
const formatDate = (dateStr?: string): string => {
    if (!dateStr) return 'Non renseigné';
    
    try {
        // Convertir la date en objet Date
        const date = new Date(dateStr);
        
        // Vérifier si la date est valide
        if (isNaN(date.getTime())) {
            return dateStr; // Retourner la chaîne originale si la date est invalide
        }
        
        // Formater la date au format dd/mm/yyyy
        const day = date.getDate().toString().padStart(2, '0');
        const month = (date.getMonth() + 1).toString().padStart(2, '0');
        const year = date.getFullYear();

        return `${day}/${month}/${year}`;
    } catch (e) {
        return dateStr; // En cas d'erreur, retourner la chaîne originale
    }
};

// Fonction pour convertir une chaîne de date en objet Date
const parseDate = (dateStr?: string): Date => {
    if (!dateStr) return new Date(0); // Date par défaut si non définie
    
    try {
        const date = new Date(dateStr);
        return isNaN(date.getTime()) ? new Date(0) : date;
    } catch (e) {
        return new Date(0);
    }
};

// Fonction pour télécharger un fichier de licence
const downloadLicenseFile = (content: {
    sn: string;
    codeList: string[];
}, fileType: string, metadata: {
    artcode?: string;
    serialNumber?: string;
    startDate?: string;
    endDate?: string;
}) => {
    try {
        // Créer un blob avec le contenu du fichier
        const blob = new Blob([JSON.stringify(content)], { type: 'text/plain' });
        
        // Créer une URL pour le blob
        const url = URL.createObjectURL(blob);
        
        // Formater les dates pour le nom de fichier
        const formatDateForFileName = (dateStr?: string) => {
            if (!dateStr) return '';
            const parts = dateStr.split('-');
            if (parts.length === 3) {
                return `${parts[0]}${parts[1]}${parts[2]}`;
            }
            return dateStr.replace(/[^0-9]/g, '');
        };
        
        const startDate = formatDateForFileName(metadata.startDate);
        const endDate = formatDateForFileName(metadata.endDate);
        
        // Définir le nom du fichier
        let fileName = `license${fileType.startsWith('.') ? fileType : '.' + fileType}`;
        
        if (metadata.serialNumber && metadata.artcode && startDate && endDate) {
            fileName = `${metadata.serialNumber}_${metadata.artcode}_${startDate}_${endDate}${fileType.startsWith('.') ? fileType : '.' + fileType}`;
        }
        
        // Créer un élément <a> pour déclencher le téléchargement
        const a = document.createElement('a');
        a.href = url;
        a.download = fileName;
        
        // Ajouter l'élément au DOM, cliquer dessus, puis le supprimer
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        
        // Libérer l'URL
        URL.revokeObjectURL(url);
        
        toast({
            variant: "success",
            title: "Téléchargement démarré",
            description: `Le fichier ${fileName} est en cours de téléchargement`,
        });
    } catch (err) {
        console.error('Erreur lors du téléchargement du fichier :', err);
        toast({
            title: "Erreur",
            description: "Impossible de télécharger le fichier de licence",
            variant: "destructive",
        });
    }
};

// Fonction pour télécharger un QR code en tant qu'image PNG
const downloadQRCode = (qrCodeLink: string, metadata: {
    artcodeparent?: string;
    serialNumber?: string;
    startDate?: string;
    endDate?: string;
}) => {
    try {
        const img = new Image();
        img.crossOrigin = "Anonymous";
        img.src = qrCodeLink;
        
        img.onload = function() {
            const canvas = document.createElement('canvas');
            const ctx = canvas.getContext('2d');
            
            if (!ctx) {
                throw new Error("Impossible de créer le contexte de canvas");
            }
            
            canvas.width = img.width;
            canvas.height = img.height;
            
            ctx.fillStyle = 'white';
            ctx.fillRect(0, 0, canvas.width, canvas.height);
            
            ctx.drawImage(img, 0, 0, img.width, img.height);
            
            // Formater les dates pour le nom de fichier
            const formatDateForFileName = (dateStr?: string) => {
                if (!dateStr) return '';
                const parts = dateStr.split('-');
                if (parts.length === 3) {
                    return `${parts[0]}${parts[1]}${parts[2]}`;
                }
                return dateStr.replace(/[^0-9]/g, ''); // Fallback: supprimer tous les caractères non numériques
            };
            
            const startDate = formatDateForFileName(metadata.startDate);
            const endDate = formatDateForFileName(metadata.endDate);
            
            // Définir le nom du fichier
            let fileName = 'qrcode.png';
            
            if (metadata.serialNumber && metadata.artcodeparent && startDate && endDate) {
                fileName = `${metadata.serialNumber}_${metadata.artcodeparent}_${startDate}_${endDate}.png`;
            }
            
            const link = document.createElement('a');
            link.download = fileName;
            link.href = canvas.toDataURL('image/png');
            link.click();
            
            toast({
                variant: "success",
                title: "Téléchargement démarré",
                description: `Le fichier ${fileName} est en cours de téléchargement`,
            });
        };
        
        img.onerror = function() {
            throw new Error("Erreur lors du chargement de l'image QR code");
        };
    } catch (err) {
        console.error('Erreur lors du téléchargement du QR code :', err);
        toast({
            title: "Erreur",
            description: "Impossible de télécharger le QR code",
            variant: "destructive",
        });
    }
};

// Fonction pour comparer les numéros de série de manière flexible
const serialNumbersMatch = (serial1: string, serial2: string): boolean => {
    if (!serial1 || !serial2) return false;
    
    // Nettoyage des numéros de série (supprimer espaces, tirets, etc.)
    const cleanSerial1 = serial1.replace(/[\s\-.]/g, '').toLowerCase();
    const cleanSerial2 = serial2.replace(/[\s\-.]/g, '').toLowerCase();

    // Vérifier si l'un contient l'autre (pour gérer les préfixes/suffixes)
    return cleanSerial1.includes(cleanSerial2) || cleanSerial2.includes(cleanSerial1);
};

export function ActivationDetails({ activations, serialNumber }: ActivationDetailsProps) {
    // Si aucun numéro de série ne correspond, afficher toutes les activations
    let serialActivations = serialNumber ? 
        activations.filter(act => serialNumbersMatch(act.serialNumber, serialNumber)) : 
        activations;
    
    if (serialActivations.length === 0) {
        return null;
    }
    
    // Trier les activations par date de début (la plus proche en premier)
    serialActivations = [...serialActivations].sort((a, b) => {
        const dateA = parseDate(a.serviceStartDate || a.activationDate);
        const dateB = parseDate(b.serviceStartDate || b.activationDate);
        return dateA.getTime() - dateB.getTime();
    });
    
    const copyToClipboard = (text: string) => {
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text)
            .then(() => {
                toast({
                    variant: "success",
                    title: "Copié !",
                    description: "Le passcode a été copié dans le presse-papier",
                });
            })
            .catch(err => {
                console.error("Erreur lors de la copie via Clipboard API :", err);
                fallbackCopyTextToClipboard(text);
            });
    } else {
        fallbackCopyTextToClipboard(text);
    }
};

const fallbackCopyTextToClipboard = (text: string) => {
    const textArea = document.createElement("textarea");
    textArea.value = text;
    textArea.style.position = "fixed"; // Empêche le scroll du viewport
    textArea.style.opacity = "0"; // Rend invisible

    document.body.appendChild(textArea);
    textArea.select();

    try {
        const successful = document.execCommand("copy");
        if (successful) {
            toast({
                variant: "success",
                title: "Copié !",
                description: "Le passcode a été copié dans le presse-papier",
            });
        } else {
            throw new Error("Échec de la copie");
        }
    } catch (err) {
        console.error("Erreur lors de la copie via execCommand :", err);
        toast({
            title: "Erreur",
            description: "Impossible de copier le passcode",
            variant: "destructive",
        });
    }
        document.body.removeChild(textArea);
    };
    
    return (
        <table className="w-full text-sm border-collapse border-spacing-0">
            <thead className="bg-blue-100/50">
                <tr>
                    <th className="p-2 text-xs font-medium text-gray-600 text-center w-[15%]">Date début</th>
                    <th className="p-2 text-xs font-medium text-gray-600 text-center w-[15%]">Date fin</th>
                    <th className="p-2 text-xs font-medium text-gray-600 text-left w-[30%]">Description</th>
                    <th className="p-2 text-xs font-medium text-gray-600 text-left w-[40%]">Passcode</th>
                </tr>
            </thead>
            <tbody>
                {serialActivations.map((activation, index) => (
                    <tr key={index} className="bg-blue-50/30 border-t border-blue-100">
                        <td className="p-2 text-xs text-left">
                            {formatDate(activation.serviceStartDate || activation.activationDate)}
                        </td>
                        <td className="p-2 text-xs text-left">
                            {formatDate(activation.serviceEndDate || activation.expirationDate)}
                        </td>
                        <td className="p-2 text-xs text-left">
                            {activation.partDescription}
                        </td>
                        <td className="p-2">
                            <div className="flex flex-wrap items-center gap-3">
                                {/* Passcode */}
                                <div className="flex-grow min-w-[200px] max-w-md">
                                    <div className="flex items-center justify-between mb-1">
                                        <span className="font-medium text-xs text-gray-600">Passcode :</span>
                                        <Button 
                                            variant="outline" 
                                            size="sm"
                                            className="flex items-center justify-center gap-1 h-6 py-0 px-2 text-xs rounded-md border-blue-200 hover:border-blue-400 hover:bg-blue-50"
                                            onClick={() => copyToClipboard(activation.passcode)}
                                        >
                                            <Copy className="h-3 w-3" />
                                            <span className="hidden sm:inline">Copier</span>
                                        </Button>
                                    </div>
                                    <div className="relative">
                                        <div className="bg-blue-50 p-1 border border-blue-200 rounded text-xs font-mono break-all">
                                            {activation.passcode}
                                        </div>
                                    </div>
                                </div>
                                
                                {/* Téléchargement */}
                                {activation.isQR == 'O' && (
                                    <div className="flex items-center gap-1">
                                        <Button 
                                            variant="outline" 
                                            size="sm"
                                            className="flex items-center justify-center gap-1 h-6 py-0 px-2 text-xs rounded-md border-blue-200 hover:border-blue-400 hover:bg-blue-50"
                                            onClick={() => 
                                                downloadQRCode(activation.qrCodeLink || '', {
                                                    artcodeparent: activation.artcode,
                                                    serialNumber: activation.serialNumber,
                                                    startDate: activation.serviceStartDate || activation.activationDate,
                                                    endDate: activation.serviceEndDate || activation.expirationDate
                                                })
                                            }
                                        >
                                            <QrCode className="h-3 w-3 text-blue-600" />
                                            <span>QR Code</span>
                                        </Button>
                                    </div>
                                )}
                                
                                {/* Téléchargement passcode */}
                                {activation.passcodeFileContent && (
                                    <div className="flex items-center gap-1">
                                        <Button
                                            variant="outline" 
                                            size="sm"
                                            className="flex items-center justify-center gap-1 h-6 py-0 px-2 text-xs rounded-md border-blue-200 hover:border-blue-400 hover:bg-blue-50"
                                            onClick={() => {
                                                if (activation.passcodeFileContent) {
                                                    downloadLicenseFile(
                                                        activation.passcodeFileContent,
                                                        activation.passcodeFileType || '.license',
                                                        {
                                                            artcode: activation.artcode,
                                                            serialNumber: activation.serialNumber,
                                                            startDate: activation.serviceStartDate || activation.activationDate,
                                                            endDate: activation.serviceEndDate || activation.expirationDate
                                                        }
                                                    );
                                                }
                                            }}
                                        >
                                            <FileDown className="h-3 w-3 text-blue-600" />
                                            <span>Télécharger le .licence</span>
                                        </Button>
                                    </div>
                                )}
                            </div>
                        </td>
                    </tr>
                ))}
            </tbody>
        </table>
    );
} 