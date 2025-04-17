import { ExportFormat } from '@/components/ExportButton';

interface UseExportOptions {
  commandId: number;
  type?: 'all' | 'api' | 'history';
}

interface ExportOptions {
  selectedDates: string[];
  exportType: 'resume' | 'requests' | 'responses';
}

export function useExport({ commandId, type = 'all' }: UseExportOptions) {
  const handleExport = async (format: ExportFormat, options: ExportOptions) => {
    try {
      const params = new URLSearchParams();
      params.append('format', format);
      params.append('type', type);
      params.append('exportType', options.exportType);

      if (options.selectedDates && options.selectedDates.length > 0) {
        options.selectedDates.forEach(date => {
          params.append('dates[]', date);
        });
      }

      const url = `/api/commands/${commandId}/logs/export?${params.toString()}`;
      const response = await fetch(url, {
        method: 'GET',
        headers: {
          'Accept': format === 'json' ? 'application/json' : 'text/csv'
        }
      });

      if (!response.ok) {
        if (response.headers.get('content-type')?.includes('application/json')) {
          const error = await response.json();
          throw new Error(error.error || 'Erreur lors de l\'export');
        }
        throw new Error(`Erreur ${response.status} lors de l'export`);
      }

      const contentDisposition = response.headers.get('content-disposition');
      let filename = 'export_logs.csv';
      
      if (contentDisposition) {
        const matches = /filename="([^"]+)"/.exec(contentDisposition);
        if (matches?.[1]) {
          filename = matches[1];
        }
      }

      const blob = await response.blob();
      const urlBlob = window.URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = urlBlob;
      a.download = filename;
      document.body.appendChild(a);
      a.click();
      window.URL.revokeObjectURL(urlBlob);
      document.body.removeChild(a);
    } catch (error) {
      console.error('Export error:', error);
      throw error;
    }
  };

  return { handleExport };
} 