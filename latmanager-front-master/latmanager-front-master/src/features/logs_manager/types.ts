import { z } from 'zod';

// Types pour les applications
export const ApplicationLastFileStatsSchema = z.object({
  last_update: z.string(),
  size: z.string()
});

export const ApplicationStatsSchema = z.object({
  total_logs: z.number(),
  last_file: ApplicationLastFileStatsSchema.nullable()
});

export const ApplicationSchema = z.object({
  name: z.string(),
  path: z.string(),
  error: z.string().nullable(),
  stats: ApplicationStatsSchema.nullable()
});

// Types pour les statistiques
export const LogStatsSchema = z.object({
  total_logs: z.number(),
  total_errors: z.number(),
  total_success: z.number(),
  avg_duration: z.number(),
  stats_by_type: z.array(z.object({
    type: z.string(),
    count: z.number()
  })),
  stats_by_duration: z.array(z.object({
    avg_duration: z.number().nullable(),
    count: z.number()
  }))
});

// Types pour la pagination
export const PaginationSchema = z.object({
  current_page: z.number(),
  total_pages: z.number(),
  total_items: z.number(),
  items_per_page: z.number(),
  has_previous: z.boolean(),
  has_next: z.boolean()
});

// Types pour les logs
export const LogSchema = z.object({
  id: z.number(),
  application: z.string(),
  section: z.string().nullable(),
  type: z.string(),
  status: z.string(),
  message: z.string(),
  user: z.string().nullable(),
  created_at: z.string(),
  duration: z.number().nullable(),
  data: z.object({
    steps: z.array(z.object({
      id: z.string(),
      status: z.string(),
      message: z.string(),
      timestamp: z.string(),
      duration: z.number().optional()
    })).optional()
  }).optional()
});

// Types pour la réponse de la liste des logs
export const LogsResponseSchema = z.object({
  data: z.array(LogSchema),
  total: z.number(),
  per_page: z.number(),
  current_page: z.number(),
  last_page: z.number()
});

// Types pour les analyses
export const AnalysisSchema = z.object({
  id: z.number(),
  created_at: z.string(),
  data: z.record(z.any()),
  statistiques: z.object({
    total_analyses: z.number(),
    total_a_mettre_a_jour: z.number(),
    pourcentage: z.string(),
    temps_execution: z.string(),
    status_command: z.string(),
    resultats: z.object({
      status: z.string(),
      message: z.string().optional(),
      details: z.array(z.object({
        reference: z.string(),
        status: z.string(),
        code: z.number(),
        message: z.string(),
        exception: z.object({
          class: z.string(),
          message: z.string(),
          file: z.string(),
          line: z.number(),
          trace: z.string()
        }).optional()
      }))
    })
  })
});

// Export des types inférés
export type ApplicationLastFileStats = z.infer<typeof ApplicationLastFileStatsSchema>;
export type ApplicationStats = z.infer<typeof ApplicationStatsSchema>;
export type Application = z.infer<typeof ApplicationSchema>;
export type LogStats = z.infer<typeof LogStatsSchema>;
export type Pagination = z.infer<typeof PaginationSchema>;
export type Log = z.infer<typeof LogSchema>;
export type LogsResponse = z.infer<typeof LogsResponseSchema>;
export type Analysis = z.infer<typeof AnalysisSchema>;

export interface LogFilters {
  application?: string;
  type?: string;
  status?: string;
  user?: string;
  section?: string;
  start_date?: string;
  end_date?: string;
}
