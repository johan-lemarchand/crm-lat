import { describe, it, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { http, HttpResponse } from 'msw';
import { server } from '../../test/setup';
import OdfPage from '../OdfPage';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { ToastProvider } from '@/components/ui/toast';

// Mock de l'environnement Vite
vi.stubGlobal('import.meta', {
  env: {
    VITE_API_URL: 'http://api-latmanager.local:8081',
  },
});

// Mock react-router-dom
vi.mock('react-router-dom', async () => {
  const actual = await vi.importActual('react-router-dom');
  return {
    ...actual,
    useParams: () => ({
      token: 'valid-token'
    })
  };
});

// Mock des composants
vi.mock('@/components/odf/VerificationTable', () => ({
  VerificationTable: () => <div data-testid="verification-table">Verification Table</div>
}));

vi.mock('@/components/odf/VerificationMessages', () => ({
  VerificationMessages: () => <div data-testid="verification-messages">Verification Messages</div>
}));

vi.mock('@/components/odf/VerificationProgress', () => ({
  VerificationProgress: () => <div data-testid="verification-progress">Verification Progress</div>
}));

vi.mock('@/components/odf/Timer', () => ({
  Timer: () => <div data-testid="timer">Timer</div>
}));

vi.mock('@/components/odf/ExistingOrderDetails', () => ({
  ExistingOrderDetails: () => <div data-testid="existing-order-details">Order Details</div>
}));

vi.mock('@/components/odf/ClosedOrderMessage', () => ({
  ClosedOrderMessage: () => <div data-testid="closed-order-message">Closed Order</div>
}));

// Mock useToast
vi.mock('@/components/ui/use-toast', () => ({
  useToast: () => ({
    toast: vi.fn()
  })
}));

// Setup du client React Query pour les tests
const createQueryClient = () => new QueryClient({
  defaultOptions: {
    queries: {
      retry: false,
    },
  },
});

// Wrapper pour les tests
const renderWithProviders = (ui: React.ReactElement) => {
  const queryClient = createQueryClient();
  return render(
    <MemoryRouter initialEntries={['/odf?token=valid-token']}>
      <Routes>
        <Route path="/odf" element={
          <QueryClientProvider client={queryClient}>
            <ToastProvider>
              {ui}
            </ToastProvider>
          </QueryClientProvider>
        } />
      </Routes>
    </MemoryRouter>
  );
};

describe('OdfPage', () => {
  beforeEach(() => {
    // Reset les mocks entre chaque test
    vi.clearAllMocks();
    localStorage.clear();
  });

  it('starts verification process with valid token', async () => {
    // Mock des réponses API
    server.use(
      http.get('http://api-latmanager.local:8081/api/token/verify', () => {
        return HttpResponse.json({ 
          valid: true,
          id: '123',
          pcdnum: '456',
          exp: Date.now() + 3600000
        });
      }),
      http.get('http://api-latmanager.local:8081/api/odf/check', () => {
        return HttpResponse.json({
          status: 'success',
          progress: 50,
          isClosed: false,
          uniqueId: '123',
          memoId: '456',
          message: 'Vérification en cours'
        });
      })
    );

    renderWithProviders(<OdfPage />);

    await waitFor(
      () => {
        const errorMessage = screen.queryByText('Accès non autorisé');
        const progress = screen.queryByTestId('verification-progress');
        if (errorMessage) throw new Error('Error message still present');
        if (!progress) throw new Error('Progress not found');
        return true;
      },
      { timeout: 2000 }
    );

    // Vérifier que les autres composants sont affichés
    expect(screen.getByTestId('verification-table')).toBeInTheDocument();
    expect(screen.getByTestId('timer')).toBeInTheDocument();
  });

  it('shows error for invalid token', async () => {
    server.use(
      http.get('http://api-latmanager.local:8081/api/token/verify', () => {
        return new HttpResponse(null, { status: 401 });
      })
    );

    renderWithProviders(<OdfPage />);

    await waitFor(() => {
      expect(screen.getByText('Accès non autorisé')).toBeInTheDocument();
      expect(screen.getByText(/Ce lien est invalide ou a expiré/)).toBeInTheDocument();
    });
  });
});
