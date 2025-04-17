import './App.css'
import { RouterProvider } from 'react-router-dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { Suspense } from 'react';
import { HelmetProvider } from 'react-helmet-async';
import router from './router';
import { LoadingFallback } from './components/LoadingFallback';
import { Toaster } from '@/components/ui/toaster';

const queryClient = new QueryClient({
    defaultOptions: {
        queries: {
            retry: 1,
            refetchOnWindowFocus: false,
            staleTime: 5 * 60 * 1000,
        },
    },
});

function App() {
    return (
        <QueryClientProvider client={queryClient}>
            <HelmetProvider>
                <Suspense fallback={<LoadingFallback />}>
                    <RouterProvider router={router} />
                </Suspense>
                <Toaster />
            </HelmetProvider>
        </QueryClientProvider>
    );
}

export default App;
