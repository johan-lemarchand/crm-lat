import React from 'react';
import { Card } from '@/components/ui/card';
import type { Analysis } from '../types';

interface Props {
  latestAnalysis: Analysis;
}

const LogsOverview: React.FC<Props> = ({ latestAnalysis }) => {
  const stats = latestAnalysis.statistiques;
  const results = stats.resultats;

  return (
    <div className="space-y-6">
      <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
        <Card className="p-4">
          <h3 className="text-sm font-medium text-gray-500">Total des analyses</h3>
          <p className="mt-2 text-3xl font-semibold">
            {stats.total_analyses}
          </p>
        </Card>

        <Card className="p-4">
          <h3 className="text-sm font-medium text-gray-500">À mettre à jour</h3>
          <p className="mt-2 text-3xl font-semibold text-amber-600">
            {stats.total_a_mettre_a_jour}
          </p>
        </Card>

        <Card className="p-4">
          <h3 className="text-sm font-medium text-gray-500">Pourcentage</h3>
          <p className="mt-2 text-3xl font-semibold">
            {stats.pourcentage}
          </p>
        </Card>

        <Card className="p-4">
          <h3 className="text-sm font-medium text-gray-500">Temps d'exécution</h3>
          <p className="mt-2 text-3xl font-semibold">
            {stats.temps_execution}
          </p>
        </Card>
      </div>

      <Card className="p-6">
        <div className={`rounded-lg p-4 ${
          results.status === 'success'
            ? 'bg-green-50 text-green-700 border border-green-200'
            : 'bg-red-50 text-red-700 border border-red-200'
        }`}>
          <h3 className={`text-lg font-semibold ${
            results.status === 'success'
              ? 'text-green-800'
              : 'text-red-800'
          }`}>
            {results.message || 'Analyse terminée'}
          </h3>
        </div>

        {results.details.length > 0 && (
          <div className="mt-6 space-y-4">
            <h4 className="text-lg font-semibold">Détails</h4>
            <div className="space-y-2">
              {results.details.map((detail, index) => (
                <div
                  key={index}
                  className={`p-3 rounded-lg ${
                    detail.status === 'success'
                      ? 'bg-green-50 border border-green-200'
                      : 'bg-red-50 border border-red-200'
                  }`}
                >
                  <div className="flex items-center justify-between">
                    <span className="font-medium">{detail.reference}</span>
                    <span className={`text-sm ${
                      detail.status === 'success'
                        ? 'text-green-600'
                        : 'text-red-600'
                    }`}>
                      {detail.status}
                    </span>
                  </div>
                  {detail.message && (
                    <p className="mt-1 text-sm">{detail.message}</p>
                  )}
                  {detail.exception && (
                    <div className="mt-2 text-sm">
                      <p className="font-medium">{detail.exception.class}</p>
                      <p>{detail.exception.message}</p>
                      <p className="mt-1 text-xs text-gray-500">
                        {detail.exception.file}:{detail.exception.line}
                      </p>
                    </div>
                  )}
                </div>
              ))}
            </div>
          </div>
        )}
      </Card>
    </div>
  );
};

export default LogsOverview;
