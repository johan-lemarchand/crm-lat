import { z } from 'zod';

export const dashboardDataSchema = z.object({
  applications_count: z.number(),
  logs_today: z.number(),
  errors_count: z.number(),
});

export type DashboardData = z.infer<typeof dashboardDataSchema>;
