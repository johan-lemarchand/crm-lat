import {useEffect, useState} from 'react';
import {useMutation, useQueryClient} from '@tanstack/react-query';
import {VerificationMessages} from './VerificationMessages';
import {api} from '@/lib/api';

interface OrderCreationProps {
  pcdid: string;
  user: string;
  onComplete: (success: boolean) => void;
  onProgressChange: (progress: number, step: number) => void;
}

// On se concentre sur l'étape 2 uniquement
const ORDER_CREATION_STEPS = [
  { step: 2, progress: 15 },  // Début de création
  { step: 2, progress: 20 },  // Initialisation
  { step: 2, progress: 25 },  // Création en cours
  { step: 2, progress: 30 }   // Finalisation de l'étape
];

export function OrderCreation({ pcdid, user, onComplete, onProgressChange }: OrderCreationProps) {
  const [messages, setMessages] = useState<any[]>([]);
  const queryClient = useQueryClient();

  const createOrderMutation = useMutation({
    mutationFn: async () => {
      try {
        return await api.post(`/api/odf/${pcdid}/create-order`, {
          user: user
        });
      } catch (error: any) {
        // Récupérer le message d'erreur de la réponse de l'API
        if (error.response?.data) {
          throw new Error(
            error.response.data.messages?.[0]?.message || 
            error.response.data.message || 
            'Erreur lors de la création de la commande'
          );
        }
        throw error;
      }
    },
    onMutate: () => {
      // Démarrer avec le début de l'étape 2
      onProgressChange(ORDER_CREATION_STEPS[0].progress, ORDER_CREATION_STEPS[0].step);
      
      let currentStepIndex = 0;
      
      const interval = setInterval(() => {
        if (currentStepIndex < ORDER_CREATION_STEPS.length - 1) {
          currentStepIndex++;
          onProgressChange(
            ORDER_CREATION_STEPS[currentStepIndex].progress, 
            ORDER_CREATION_STEPS[currentStepIndex].step
          );
        } else {
          clearInterval(interval);
        }
      }, 800);
      
      return () => clearInterval(interval);
    },
    onSuccess: (data) => {
      // S'assurer que nous sommes au maximum de l'étape 2
      onProgressChange(30, 2);
      
      setMessages(data.messages || [{
        type: 'success',
        message: 'Création de la commande terminée',
        status: 'success'
      }]);
      
      queryClient.invalidateQueries({ queryKey: ['odf', pcdid] });
      onComplete(true);
    },
    onError: (error: Error) => {
      setMessages([{
        type: 'error',
        message: error.message,
        status: 'error',
        isCreationError: true // Pour identifier que c'est une erreur de création
      }]);
      onComplete(false);
      // Remettre la progression à 0 en cas d'erreur
      onProgressChange(0, 1);
    }
  });

  useEffect(() => {
    createOrderMutation.mutate();
  }, []);

  return (
    <div className="space-y-6">
      {createOrderMutation.isPending && (
        <div className="flex justify-center py-8">
          <div className="flex flex-col items-center gap-4">
            <div className="animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-blue-500"></div>
            <p className="text-sm text-gray-600">Création de la commande en cours...</p>
          </div>
        </div>
      )}

      {(createOrderMutation.isSuccess || createOrderMutation.isError) && (
        <VerificationMessages 
          messages={messages} 
          onRetry={() => createOrderMutation.mutate()}
        />
      )}
    </div>
  );
} 