import { OdfSession, OdfExecution } from './types';

// Fonction pour formater le temps d'exécution en heures, minutes et secondes
export const formatExecutionTime = (time: number | null, isMilliseconds: boolean = false): string => {
    if (time === null) return 'N/A';
    if (time === 0) return '0s';
    
    // Convertir en secondes si le temps est en millisecondes
    let timeInSeconds = time;
    if (isMilliseconds) {
        timeInSeconds = Math.floor(time / 1000);
        // Si le temps est inférieur à 1 seconde, afficher en millisecondes
        if (timeInSeconds === 0) {
            return `${time}ms`;
        }
    }
    
    const hours = Math.floor(timeInSeconds / 3600);
    const minutes = Math.floor((timeInSeconds % 3600) / 60);
    const seconds = timeInSeconds % 60;
    
    let result = '';
    
    if (hours > 0) {
        result += `${hours}h `;
    }
    
    if (minutes > 0 || hours > 0) {
        result += `${minutes}m `;
    }
    
    result += `${seconds}s`;
    
    return result;
};

// Fonction pour obtenir la classe CSS en fonction du statut
export const getStatusClass = (status: string): string => {
    switch (status.toLowerCase()) {
        case 'success':
        case 'finish':
            return 'bg-green-100 text-green-800';
        case 'error':
            return 'bg-red-100 text-red-800';
        case 'running':
        case 'new':
            return 'bg-blue-100 text-blue-800';
        default:
            return 'bg-gray-100 text-gray-800';
    }
};

// Fonction pour obtenir la classe de statut pour les étapes
export const getStepStatusClass = (status: string | null): string => {
    if (!status) return 'bg-gray-100 text-gray-800';
    
    switch (status.toLowerCase()) {
        case 'success':
        case 'finish':
        case 'completed':
            return 'bg-green-100 text-green-800';
        case 'error':
        case 'failed':
            return 'bg-red-100 text-red-800';
        case 'pending':
            return 'bg-yellow-100 text-yellow-800';
        default:
            return 'bg-gray-100 text-gray-800';
    }
};

// Fonction pour obtenir la classe CSS du badge en fonction du numéro d'étape
export const getStepNumberClass = (statusText: string | null): string => {
    if (!statusText) return 'bg-gray-100 text-gray-800';

    if (statusText === 'success') return 'bg-green-100 text-green-800';
    if (statusText === 'info') return 'bg-orange-100 text-orange-800';
    return 'bg-red-100 text-red-800';
};

// Fonction pour obtenir le nom de l'étape en fonction du stepStatus
export const getStepName = (stepStatus: number | null): string => {
    if (!stepStatus) return "Étape inconnue";
    
    switch (stepStatus) {
        case 1:
            return "Vérification";
        case 2:
            return "Création commande";
        case 3:
            return "Récupération commande";
        case 4:
            return "Activation";
        case 5:
            return "Création BDF";
        case 6:
            return "Commande Terminée";
        default:
            return `Étape ${stepStatus}`;
    }
};

// Fonction pour calculer le temps total d'exécution d'une session à partir de ses exécutions
export const calculateSessionExecutionTime = (session: OdfSession): number => {
    if (session.executionTime !== null) {
        return session.executionTime;
    }
    
    return session.executions.reduce((total: number, execution: OdfExecution) => {
        return total + (execution.executionTime || 0);
    }, 0);
}; 