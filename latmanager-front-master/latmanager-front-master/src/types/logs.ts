export interface CurrencyDetails {
  last_update: string;
  last_rate: string;
  old_rate: string;
  new_rate: string;
  summary?: {
    currency: string;
    old_rate: string;
    new_rate: string;
    last_update: string;
    articles_updated: number;
    has_json_export: boolean;
  };
}

export interface ArticleModifie {
  code: string;
  designation: string;
  ancien_prix: string;
  nouveau_prix: string;
}

export interface ErrorDetail {
  type: string;
  message: string;
}

export interface ActivitesStats {
  total: number;
  created: number;
  updated: number;
  errors: number;
  error_details: ErrorDetail[];
}

export interface CreneauxStats {
  total: number;
  created: number;
  updated: number;
  deleted: number;
  errors: number;
  error_details: ErrorDetail[];
}

export interface LogStatistiques {
  total_analyses?: number;
  temps_execution: string;
  status_command: string;
  resultats: {
    status: string;
    message: string;
    details: Array<{
      currency?: string;
      error: string;
      trace?: string;
    }>;
  };
  total_articles?: number;
  total_a_mettre_a_jour?: number;
  pourcentage?: string;
  articles_modifies?: ArticleModifie[];
  activites?: ActivitesStats;
  creneaux?: CreneauxStats;
  total_devises?: number;
  mises_a_jour?: number;
  erreurs?: number;
  currencies?: Record<string, CurrencyDetails>;
}

export interface DelockDetail {
  pcdnum: string;
  status: 'success' | 'error';
}

export interface DelockResume {
  total_coupons: number;
  success_count: number;
  error_count: number;
  details: DelockDetail[];
}

export interface LogResume {
  date: string;
  statistiques: LogStatistiques;
}

export interface ApiLog {
  id: number;
  endpoint: string;
  method: string;
  statusCode: number;
  duration: number;
  createdAt: string;
  requestXml: string;
  responseXml: string;
}

export interface Log {
  id: number;
  status: string;
  output: string;
  error: string;
  startedAt: string;
  finishedAt: string | null;
  exitCode: number;
  stackTrace: string | null;
  apiLogs: ApiLog[];
  resume: LogResume | null;
}

export interface Command {
  id: number;
  name: string;
  scriptName: string;
} 