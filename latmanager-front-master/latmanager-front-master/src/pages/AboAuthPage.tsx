import { useEffect, useState, useCallback, useRef } from 'react';
import { useNavigate, useSearchParams } from 'react-router-dom';
import { api } from '@/lib/api';

interface TokenResponse {
  token: string;
  type?: string;
  expires_in?: number;
}

interface TokenRequest {
  user: string | null;
  pcvnum: string | null;
}

const AboAuthPage = () => {
  const navigate = useNavigate();
  const [searchParams] = useSearchParams();
  const [error, setError] = useState<string | null>(null);
  const [isLoading, setIsLoading] = useState(false);
  const requestMadeRef = useRef(false);

  const getTokenAndRedirect = useCallback(async () => {
    if (isLoading || requestMadeRef.current) return;

    const user = searchParams.get('user');
    const pcvnum = searchParams.get('pcvnum');
    // Si un des paramètres est manquant, ne rien faire
    if (!user || !pcvnum) {
      setError('Paramètres d\'authentification manquants.');
      return;
    }

    setIsLoading(true);
    requestMadeRef.current = true;

    try {
      const tokenRequest: TokenRequest = {
        user,
        pcvnum
      };

      // Utiliser le point d'accès spécifique au type "abo"
      const response = await api.post<TokenResponse>('/api/token/abo', tokenRequest);

      if (!response || !response.token) {
        throw new Error('Token manquant dans la réponse');
      }

      // Rediriger vers la page ABO avec uniquement le token
      const targetUrl = `/abo/${response.token}`;

      navigate(targetUrl);
    } catch (error) {
      console.error('Erreur détaillée:', error);
      setError('Impossible de récupérer le token d\'accès. Veuillez réessayer.');
      setIsLoading(false);
      requestMadeRef.current = false;
    }
  }, [navigate, searchParams, isLoading]);

  useEffect(() => {
    getTokenAndRedirect();
  }, [getTokenAndRedirect]);

  if (error) {
    return (
      <div className="flex items-center justify-center min-h-screen bg-gray-100">
        <div className="p-8 bg-white rounded-lg shadow-md">
          <h1 className="text-2xl font-semibold text-red-600 mb-4">Erreur d'authentification</h1>
          <p className="text-gray-600">{error}</p>
        </div>
      </div>
    );
  }

  return (
    <div className="flex items-center justify-center min-h-screen bg-gray-100">
      <div className="p-8 bg-white rounded-lg shadow-md">
        <h1 className="text-2xl font-semibold text-gray-800 mb-4">Authentification en cours...</h1>
        <p className="text-gray-600">Veuillez patienter pendant que nous vérifions vos informations.</p>
      </div>
    </div>
  );
};

export default AboAuthPage;
