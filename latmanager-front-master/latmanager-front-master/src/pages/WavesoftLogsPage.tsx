import { Helmet } from 'react-helmet-async';
import WavesoftLogs from '@/components/home/WavesoftLogs';

export default function WavesoftLogsPage() {
  return (
    <>
      <Helmet>
        <title>Logs Wavesoft - Projet Manager</title>
      </Helmet>
      
      <div className="container mx-auto py-6">
        <h1 className="text-3xl font-bold mb-6">Logs Wavesoft</h1>
        <WavesoftLogs
          showPagination={true}
          title="Historique complet des logs Wavesoft"
        />
      </div>
    </>
  );
} 