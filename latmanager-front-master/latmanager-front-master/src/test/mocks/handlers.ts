import { http, HttpResponse } from 'msw';

// L'URL de test correcte
const BASE_URL = 'http://api-latmanager.local:8081';

export const handlers = [
  // Mock pour la vérification du token
  http.get(`${BASE_URL}/api/token/verify`, async ({ request }) => {
    const url = new URL(request.url);
    const token = url.searchParams.get('token');
    
    if (token === 'valid-token') {
      return HttpResponse.json({ 
        valid: true,
        id: '123',
        pcdnum: '456',
        exp: Date.now() + 3600000 // expire dans 1 heure
      });
    }
    
    return new HttpResponse(null, { status: 401 });
  }),

  // Mock pour la vérification ODF
  http.get(`${BASE_URL}/api/odf/check`, async ({ request }) => {
    const url = new URL(request.url);
    const pcdid = url.searchParams.get('pcdid');
    const pcdnum = url.searchParams.get('pcdnum');

    if (!pcdid || !pcdnum) {
      return new HttpResponse(
        JSON.stringify({ status: 'error', message: 'Paramètres manquants' }),
        { status: 400 }
      );
    }

    return HttpResponse.json({
      status: 'success',
      progress: 50,
      isClosed: false,
      uniqueId: '123',
      memoId: '456',
      apiVersion: '1.0',
      trimbleVersion: '2.0',
      message: 'Vérification en cours'
    });
  })
];
