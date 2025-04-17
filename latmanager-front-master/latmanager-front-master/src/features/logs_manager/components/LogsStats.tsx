import React from 'react';
import { Card } from '@/components/ui/card';
import type { LogStats } from '../types';

interface LogsStatsProps {
  stats: LogStats;
}

export const LogsStats: React.FC<LogsStatsProps> = ({ stats }) => {
  return (
    <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
      <Card className="p-4">
        <h3 className="text-sm font-medium text-gray-500">Total des logs</h3>
        <p className="mt-2 text-3xl font-semibold">
          {stats.total_logs}
        </p>
      </Card>

      <Card className="p-4">
        <h3 className="text-sm font-medium text-gray-500">Erreurs</h3>
        <p className="mt-2 text-3xl font-semibold text-red-600">
          {stats.total_errors}
        </p>
      </Card>

      <Card className="p-4">
        <h3 className="text-sm font-medium text-gray-500">Succès</h3>
        <p className="mt-2 text-3xl font-semibold text-green-600">
          {stats.total_success}
        </p>
      </Card>

      <Card className="p-4">
        <h3 className="text-sm font-medium text-gray-500">Durée moyenne</h3>
        <p className="mt-2 text-3xl font-semibold">
          {stats.avg_duration}ms
        </p>
      </Card>

      <Card className="p-4 md:col-span-2">
        <h3 className="mb-4 text-sm font-medium text-gray-500">Distribution par type</h3>
        <div className="space-y-2">
          {stats.stats_by_type.map((stat) => (
            <div key={stat.type}>
              <div className="flex justify-between text-sm">
                <span>{stat.type}</span>
                <span>{stat.count}</span>
              </div>
              <div className="mt-1 h-2 rounded-full bg-gray-100">
                <div
                  className="h-2 rounded-full bg-blue-500"
                  style={{
                    width: `${(stat.count / stats.total_logs) * 100}%`,
                  }}
                />
              </div>
            </div>
          ))}
        </div>
      </Card>

      <Card className="p-4 md:col-span-2">
        <h3 className="mb-4 text-sm font-medium text-gray-500">Distribution par durée</h3>
        <div className="space-y-2">
          {stats.stats_by_duration.map((stat, index) => (
            <div key={index}>
              <div className="flex justify-between text-sm">
                <span>
                  {stat.avg_duration === null
                    ? 'Non mesurée'
                    : `${stat.avg_duration}ms`}
                </span>
                <span>{stat.count}</span>
              </div>
              <div className="mt-1 h-2 rounded-full bg-gray-100">
                <div
                  className="h-2 rounded-full bg-blue-500"
                  style={{
                    width: `${(stat.count / stats.total_logs) * 100}%`,
                  }}
                />
              </div>
            </div>
          ))}
        </div>
      </Card>
    </div>
  );
};
