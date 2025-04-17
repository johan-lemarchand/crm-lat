import { z } from 'zod';
import { Analysis, AnalysisSchema, LogStats, LogStatsSchema, LogsResponse, LogsResponseSchema } from '../types';
import { API_URL } from '@/config/constants';

export type LogFilters = {
  application?: string;
  type?: string;
  status?: string;
  user?: string;
  section?: string;
  start_date?: string;
  end_date?: string;
};

export const LogFile = z.object({
  name: z.string(),
  path: z.string(),
  size: z.number(),
  modified: z.number(),
  app: z.string(),
  subApp: z.string().optional(),
});

export type LogFile = z.infer<typeof LogFile>;

export interface LogContent {
  file: string;
  content: any;
}

export const logsApi = {
  getStats: async (filters?: LogFilters): Promise<LogStats> => {
    const searchParams = new URLSearchParams();
    if (filters) {
      Object.entries(filters).forEach(([key, value]) => {
        if (value) searchParams.append(key, value);
      });
    }

    const response = await fetch(`${API_URL}/stats?${searchParams}`);
    if (!response.ok) {
      throw new Error('Failed to fetch stats');
    }
    const data = await response.json();
    return LogStatsSchema.parse(data);
  },

  getLogs: async (page: number = 1, filters?: LogFilters): Promise<LogsResponse> => {
    const searchParams = new URLSearchParams({ page: page.toString() });
    if (filters) {
      Object.entries(filters).forEach(([key, value]) => {
        if (value) searchParams.append(key, value);
      });
    }

    const response = await fetch(`${API_URL}/list?${searchParams}`);
    if (!response.ok) {
      throw new Error('Failed to fetch logs');
    }
    const data = await response.json();
    return LogsResponseSchema.parse(data);
  },

  getApplications: async () => {
    const response = await fetch(`${API_URL}/logs/apps`);
    if (!response.ok) {
      throw new Error('Erreur lors de la récupération des applications');
    }
    const data = await response.json();
    return z.array(z.object({
      name: z.string(),
      subApps: z.array(z.string()).optional()
    })).parse(data);
  },

  getTypes: async (): Promise<string[]> => {
    const response = await fetch(`${API_URL}/types`);
    if (!response.ok) {
      throw new Error('Failed to fetch types');
    }
    const data = await response.json();
    return data.types;
  },

  getLogFiles: async (appName: string, subApp?: string) => {
    const url = subApp 
      ? `${API_URL}/logs/${appName}/${subApp}/files`
      : `${API_URL}/logs/${appName}/files`;
        
    const response = await fetch(url);
    if (!response.ok) {
      throw new Error('Erreur lors de la récupération des fichiers de logs');
    }
    const data = await response.json();
    return z.array(LogFile).parse(data);
  },

  getLogContent: async (filePath: string) => {
    const response = await fetch(`${API_URL}/logs/content`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({ path: filePath }),
    });
    
    if (!response.ok) {
      throw new Error('Erreur lors de la récupération du contenu du fichier');
    }
    
    return response.json();
  },

  clearLogs: async (appName: string, subApp?: string): Promise<void> => {
    const url = subApp 
      ? `${API_URL}/logs/${appName}/${subApp}/clear`
      : `${API_URL}/logs/${appName}/clear`;
        
    const response = await fetch(url, {
      method: 'POST',
    });
    
    if (!response.ok) {
      throw new Error('Erreur lors du nettoyage des logs');
    }
  },

  getLatestAnalysis: async (): Promise<Analysis> => {
    const response = await fetch(`${API_URL}/analyses/latest`);
    if (!response.ok) {
      throw new Error('Failed to fetch latest analysis');
    }
    const data = await response.json();
    return AnalysisSchema.parse(data);
  },

  getAllAnalyses: async (): Promise<Analysis[]> => {
    const response = await fetch(`${API_URL}/analyses`);
    if (!response.ok) {
      throw new Error('Failed to fetch analyses');
    }
    const data = await response.json();
    return z.array(AnalysisSchema).parse(data);
  },
};
