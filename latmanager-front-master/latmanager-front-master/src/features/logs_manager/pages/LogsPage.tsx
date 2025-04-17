import React from 'react';
import { useQuery } from '@tanstack/react-query';
import { logsApi } from '../api/logsApi';
import { LogsStats } from '../components/LogsStats';
import LogsOverview from '../components/LogsOverview';
import LogsList from '../components/LogsList';

export const LogsPage: React.FC = () => {
  const { data: stats } = useQuery({
    queryKey: ['logs', 'stats'],
    queryFn: () => logsApi.getStats(),
  });

  const { data: latestAnalysis } = useQuery({
    queryKey: ['analyses', 'latest'],
    queryFn: () => logsApi.getLatestAnalysis(),
  });

  return (
    <div className="container mx-auto py-6 space-y-6">
      <h1 className="text-2xl font-bold">Gestion des Logs</h1>
      
      {stats && <LogsStats stats={stats} />}
      
      {latestAnalysis && <LogsOverview latestAnalysis={latestAnalysis} />}
      
      <LogsList />
    </div>
  );
};
