const baseURL = import.meta.env.VITE_API_URL;

export const api = {
    get: async <T>(url: string, config?: { params?: Record<string, string> }): Promise<T> => {
        const queryParams = config?.params 
            ? '?' + new URLSearchParams(config.params).toString() 
            : '';
            
        const response = await fetch(`${baseURL}${url}${queryParams}`, {
            method: 'GET',
            credentials: 'include',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            },
        });
        
        // Pour les erreurs 401 des endpoints ODF, traiter comme une réponse valide
        if (response.status === 401 && url.includes('/api/odf/')) {
            const data = await response.json();
            return data as T;
        }
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        return response.json();
    },

    post: async <T = any>(endpoint: string, data: any): Promise<T> => {
        const response = await fetch(`${baseURL}${endpoint}`, {
            method: 'POST',
            credentials: 'include',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        });
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    },

    put: async <T = any>(endpoint: string, data: any): Promise<T> => {
        const response = await fetch(`${baseURL}${endpoint}`, {
            method: 'PUT',
            credentials: 'include',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        });
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    },

    delete: async <T = any>(endpoint: string): Promise<T> => {
        const response = await fetch(`${baseURL}${endpoint}`, {
            method: 'DELETE',
            credentials: 'include',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            },
        });
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    }
};

/**
 * Récupère un template Twig depuis le backend
 * @param templateName Nom du template à récupérer
 * @returns Le contenu du template
 */
export const getTwigTemplate = async (templateName: string): Promise<string> => {
  try {
    // Charger directement le contenu du fichier Twig
    // Les templates sont disponibles sur le backend mais pas via l'API standard
    // Utilisation d'un endpoint spécifique pour les templates Twig
    const response = await fetch(`${baseURL}/api/email-templates/twig-template/${templateName}`);
    
    if (!response.ok) {
      throw new Error(`Impossible de charger le template: ${response.statusText}`);
    }
    
    // Le contenu brut du template est retourné
    return await response.text();
  } catch (error) {
    console.error('Erreur lors de la récupération du template Twig:', error);
    throw error;
  }
};

/**
 * Récupère la liste des templates Twig disponibles
 * @returns Liste des templates
 */
export const getAvailableTwigTemplates = async (): Promise<{id: string, name: string, description: string}[]> => {
  // Liste statique des templates disponibles dans le dossier emails
  return [
    {
      id: 'sync_currencies_report',
      name: 'Synchronisation des Devises',
      description: 'Rapport de synchronisation des devises'
    },
    {
      id: 'sync_articles_report',
      name: 'Synchronisation des Articles',
      description: 'Rapport de synchronisation des articles'
    },
    {
      id: 'sync_activities_report',
      name: 'Synchronisation des Activités',
      description: 'Rapport de synchronisation des activités'
    },
    {
      id: 'delock_coupon_report',
      name: 'Rapport de Coupons Débloqués',
      description: 'Rapport des coupons débloqués'
    },
    {
      id: 'command_scheduler_alert',
      name: 'Alerte de Planificateur',
      description: 'Notification d\'alerte du planificateur de commandes'
    }
  ];
};
