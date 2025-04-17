import { createBrowserRouter, Navigate } from 'react-router-dom';
import RootLayout from './layouts/RootLayout';
import OdfPage from '@/pages/OdfPage';
import OdfAuthPage from '@/pages/OdfAuthPage';
import ProjectsPage from '@/pages/ProjectsPage';
import BoardsPage from './pages/BoardsPage';
import { Board } from '@/components/kanban';
import HomePage from '@/pages/Home';
import { LoadingFallback } from './components/LoadingFallback';
import SettingsPage from '@/pages/Settings';
import AboAuthPage from "@/pages/AboAuthPage.tsx";
import AboPage from '@/pages/AboPage';
import LogsPage from '@/pages/LogsPage';
import WavesoftLogsPage from '@/pages/WavesoftLogsPage';
import React from "react";
import { EmailTemplatesPage } from './pages/EmailTemplates';
import { EmailTemplateEditor } from './pages/EmailTemplateEditor';

// Configuration des sous-domaines
interface SubdomainConfig {
  prefix: string;
  authComponent: React.ComponentType;
  mainComponent: React.ComponentType;
  basePath: string;
}

const subdomainConfigs: Record<string, SubdomainConfig> = {
  'odf': {
    prefix: 'odf-',
    authComponent: OdfAuthPage,
    mainComponent: OdfPage,
    basePath: '/odf'
  },
  'abo': {
    prefix: 'abo-',
    authComponent: AboAuthPage,
    mainComponent: AboPage,
    basePath: '/abo'
  }
};

// Fonction utilitaire pour détecter le sous-domaine actuel
const getCurrentSubdomain = (): string | null => {
  const hostname = window.location.hostname;
  for (const [key, config] of Object.entries(subdomainConfigs)) {
    if (hostname.startsWith(config.prefix)) {
      return key;
    }
  }
  return null;
};

// Création du router pour un sous-domaine spécifique
const createSubdomainRouter = (config: SubdomainConfig) => {
  return createBrowserRouter([
    {
      path: '/',
      errorElement: <LoadingFallback />,
      children: [
        {
          path: `${config.basePath}/auth`,
          element: <config.authComponent />,
        },
        {
          path: `${config.basePath}/:token`,
          element: <config.mainComponent />,
        },
        {
          path: '*',
          element: <Navigate to={`${config.basePath}/auth`} replace />,
        },
      ],
    },
  ]);
};

// Router pour l'application principale
const mainRouter = createBrowserRouter([
  {
    path: '/',
    element: <RootLayout />,
    errorElement: <LoadingFallback />,
    children: [
      {
        path: '/',
        element: <HomePage />,
      },
      {
        path: 'projects',
        element: <ProjectsPage />,
      },
      {
        path: 'logs/command/:id',
        lazy: async () => {
          const { default: CommandLogs } = await import('./pages/CommandLogs');
          return { Component: CommandLogs };
        },
      },
      {
        path: 'logs/odf',
        element: <LogsPage />,
      },
      {
        path: 'logs/wavesoft',
        element: <WavesoftLogsPage />,
      },
      {
        path: 'boards',
        element: <BoardsPage />,
      },
      {
        path: 'board/:boardId',
        element: <Board />,
      },
      {
        path: 'settings',
        element: <SettingsPage />,
      },
      {
        path: '/email-templates',
        element: <EmailTemplatesPage />
      },
      {
        path: '/email-templates/:name/edit',
        element: <EmailTemplateEditor initialTab="edit" />
      },
      {
        path: '/email-templates/:name/preview',
        element: <EmailTemplateEditor initialTab="preview" />
      },
      {
        path: '/email-templates/new',
        element: <EmailTemplateEditor initialTab="edit" />
      },
    ],
  },
]);

// Détection du sous-domaine et sélection du router approprié
const currentSubdomain = getCurrentSubdomain();
const router = currentSubdomain && subdomainConfigs[currentSubdomain]
  ? createSubdomainRouter(subdomainConfigs[currentSubdomain])
  : mainRouter;

export default router;