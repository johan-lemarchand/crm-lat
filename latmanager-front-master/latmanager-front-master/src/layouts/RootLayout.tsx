import { Link, Outlet } from 'react-router-dom';
import { Settings, Home, LayoutDashboard, Mail } from "lucide-react";
import { Button } from "@/components/ui/button.tsx";
import { useQuery } from '@tanstack/react-query';
import { Badge } from "@/components/ui/badge";
import { api } from '@/lib/api';

export default function RootLayout() {
  // Vérifier si on est sur une page ODF
  const isOdfPage = window.location.pathname.startsWith('/odf');
  
  const { data: versions } = useQuery({
    queryKey: ['versions'],
    queryFn: () => api.get('/api/versions'),
    enabled: !isOdfPage // Désactive la requête uniquement sur les pages ODF
  });

  return (
    <div className="min-h-screen">
      {/* Header */}
      <header className="bg-white dark:bg-gray-900 border-b border-gray-200 dark:border-gray-800">
        <div className="container-fluid px-4 h-16 flex items-center justify-between">
          <Link to="/" className="flex items-center space-x-2">
            <i className="ri-image-line text-blue-600 text-2xl"></i>
            <span className="text-xl font-semibold text-gray-900 dark:text-white">Manager</span>
            {versions?.manager_version && (
              <Badge variant="outline" className="ml-2">
                {versions.manager_version}
              </Badge>
            )}
          </Link>
          <div className="flex items-center space-x-4">
            <Button
              variant="ghost"
              size="icon"
              asChild
              title="Accueil"
            >
              <Link to="/">
                <Home className="h-4 w-4" />
              </Link>
            </Button>
            <Button
              variant="ghost"
              size="icon"
              asChild
              title="Tableaux"
            >
              <Link to="/boards">
                <LayoutDashboard className="h-4 w-4" />
              </Link>
            </Button>
            <Button
              variant="ghost"
              size="icon"
              asChild
              title="Templates d'emails"
            >
              <Link to="/email-templates">
                <Mail className="h-4 w-4" />
              </Link>
            </Button>
            <Button
              variant="ghost"
              size="icon"
              onClick={() => window.location.href = '/settings'}
              title="Paramètres système"
            >
              <Settings className="h-4 w-4" />
            </Button>
          </div>
        </div>
      </header>

      {/* Main Content */}
      <main className="container-fluid px-4">
        <Outlet />
      </main>
    </div>
  );
}
