import React from 'react';
import { useQueryClient } from '@tanstack/react-query';
import { logsApi } from '../api/logsApi';
import type { Log } from '../types';

const LogsList: React.FC = () => {
  const [logs, setLogs] = React.useState<Log[]>([]);
  const queryClient = useQueryClient();

  React.useEffect(() => {
    const fetchLogs = async () => {
      try {
        const response = await logsApi.getLogs(1);
        setLogs(response.data);
      } catch (error) {
        console.error('Error fetching logs:', error);
      }
    };

    fetchLogs();
    return () => {
      queryClient.invalidateQueries({ queryKey: ['logs'] });
    };
  }, [queryClient]);

  const groupLogsByDate = React.useCallback((logs: Log[]) => {
    return logs.reduce<Record<string, Log[]>>((acc, log: Log) => {
      const date = new Date(log.created_at).toLocaleDateString();
      if (!acc[date]) acc[date] = [];
      acc[date].push(log);
      return acc;
    }, {});
  }, []);

  const groupedLogs = React.useMemo(() => groupLogsByDate(logs), [logs, groupLogsByDate]);

  return (
    <div className="space-y-8">
      {Object.entries(groupedLogs).map(([date, logs]) => (
        <div key={date} className="space-y-4">
          <h3 className="text-lg font-semibold">{date}</h3>
          <div className="space-y-2">
            {logs.map((log) => (
              <div key={log.id} className="rounded-lg border p-4">
                <h4 className="font-medium">{log.application}</h4>
                {log.section && (
                  <p className="text-sm text-gray-500">{log.section}</p>
                )}
                <div className="mt-2 flex items-center space-x-2">
                  <span className={`inline-block w-2 h-2 rounded-full ${
                    log.status === 'success' ? 'bg-green-500' :
                    log.status === 'error' ? 'bg-red-500' :
                    'bg-yellow-500'
                  }`} />
                  <span className="text-sm">{log.status}</span>
                </div>
                <p className="mt-2 text-sm">{log.message}</p>
                {log.user && (
                  <p className="mt-2 text-xs text-gray-500">Par {log.user}</p>
                )}
                {log.duration && (
                  <p className="mt-1 text-xs text-gray-500">Durée : {log.duration}s</p>
                )}
                {log.data?.steps && (
                  <div className="mt-4 space-y-2">
                    {log.data.steps.map((step, index) => (
                      <div key={step.id || index} className="text-sm">
                        <span className={`inline-block w-2 h-2 rounded-full mr-2 ${
                          step.status === 'success' ? 'bg-green-500' :
                          step.status === 'error' ? 'bg-red-500' :
                          'bg-yellow-500'
                        }`} />
                        {step.message}
                      </div>
                    ))}
                  </div>
                )}
              </div>
            ))}
          </div>
        </div>
      ))}
    </div>
  );
};

export default LogsList;
