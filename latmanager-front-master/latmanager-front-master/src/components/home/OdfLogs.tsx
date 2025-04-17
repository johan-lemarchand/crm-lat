import React, { useState, useEffect } from 'react';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { ChevronDown, ChevronUp, Loader2, RefreshCw, FileX, XCircle, ArrowRight, CheckCircle, AlertCircle, List } from 'lucide-react';
import { OdfLog, OdfLogsResponse, OdfStep } from './types';
import { formatExecutionTime, getStatusClass, getStepStatusClass, getStepNumberClass, getStepName, calculateSessionExecutionTime } from './utils';
import { api } from '@/lib/api';
import StepsModal from './StepsModal';
import { Pagination } from '@/components/ui/pagination';

interface OdfLogsProps {
  apiVersion?: string;
  limit?: number;
  title?: string;
  showHeader?: boolean;
  showPagination?: boolean;
  lastExecution?: {
    date?: string;
    status?: string;
  };
}

const ITEMS_PER_PAGE = 10;

const OdfLogs: React.FC<OdfLogsProps> = ({ 
  apiVersion, 
  limit = 10, 
  title = "API TRIMBLE - Logs d'exécution",
  showHeader = true,
  showPagination = false,
  lastExecution: propLastExecution
}) => {
  // États pour l'UI
  const [odfLogsExpanded, setOdfLogsExpanded] = useState(true);
  const [expandedOdfs, setExpandedOdfs] = useState<number[]>([]);
  const [expandedSessions, setExpandedSessions] = useState<number[]>([]);
  const [stepsModalOpen, setStepsModalOpen] = useState(false);
  const [selectedSteps, setSelectedSteps] = useState<OdfStep[]>([]);
  const [currentPage, setCurrentPage] = useState(1);
  const [lastExecution, setLastExecution] = useState<{
    date?: string;
    status?: string;
  } | undefined>(propLastExecution);
  
  const queryClient = useQueryClient();

  // Requête pour récupérer les logs ODF
  const { 
    data: odfLogsData, 
    isLoading: odfLogsLoading, 
    error: odfLogsError 
  } = useQuery<OdfLogsResponse>({
    queryKey: ['odf-logs', showPagination],
    queryFn: () => api.get(showPagination ? '/api/odf/logs/all' : '/api/odf/logs')
  });

  const odfLogs = odfLogsData?.logs || [];
  const totalPages = Math.ceil(odfLogs.length / ITEMS_PER_PAGE);
  const paginatedLogs = showPagination 
    ? odfLogs.slice((currentPage - 1) * ITEMS_PER_PAGE, currentPage * ITEMS_PER_PAGE)
    : odfLogs.slice(0, limit);

  // Réinitialiser la page courante quand les données changent
  useEffect(() => {
    setCurrentPage(1);
  }, [odfLogsData]);

  // Effet pour déterminer la dernière session basée sur les données ODF
  useEffect(() => {
    if (odfLogs.length > 0) {
      // Trier les ODF par date de création (du plus récent au plus ancien)
      const sortedLogs = [...odfLogs].sort((a, b) => 
        new Date(b.createdAt).getTime() - new Date(a.createdAt).getTime()
      );
      
      // Prendre le premier ODF (le plus récent)
      const mostRecentOdf = sortedLogs[0];
      
      // S'il y a des sessions, utiliser la date de la plus récente
      if (mostRecentOdf.sessions.length > 0) {
        // Trier les sessions par date (du plus récent au plus ancien)
        const sortedSessions = [...mostRecentOdf.sessions].sort((a, b) => 
          new Date(b.createdAt).getTime() - new Date(a.createdAt).getTime()
        );
        
        // Prendre la première session (la plus récente)
        const mostRecentSession = sortedSessions[0];
        
        setLastExecution({
          date: new Date(mostRecentSession.createdAt).toLocaleString('fr-FR'),
          status: mostRecentOdf.status || 'unknown'
        });
      } else {
        // Sinon utiliser la date de l'ODF lui-même
        setLastExecution({
          date: new Date(mostRecentOdf.createdAt).toLocaleString('fr-FR'),
          status: mostRecentOdf.status || 'unknown'
        });
      }
    }
  }, [odfLogs]);

  // Fonction pour obtenir l'icône de statut appropriée
  const getStatusIcon = (status: string | undefined) => {
    if (!status) return null;
    
    switch (status.toLowerCase()) {
      case 'success':
        return <CheckCircle className="h-4 w-4 text-green-500" />;
      case 'error':
        return <XCircle className="h-4 w-4 text-red-500" />;
      case 'warning':
      case 'running':
        return <AlertCircle className="h-4 w-4 text-yellow-500" />;
      default:
        return null;
    }
  };

  // Fonction pour basculer l'état d'expansion d'un ODF
  const toggleOdfExpansion = (odfId: number) => {
    setExpandedOdfs(prev => 
      prev.includes(odfId) 
        ? prev.filter(id => id !== odfId) 
        : [...prev, odfId]
    );
  };

  // Fonction pour basculer l'état d'expansion d'une session
  const toggleSessionExpansion = (sessionId: number) => {
    setExpandedSessions(prev => 
      prev.includes(sessionId) 
        ? prev.filter(id => id !== sessionId) 
        : [...prev, sessionId]
    );
  };

  return (
    <Card className="border-0 shadow-none mt-8">
      {showHeader && (
        <CardHeader className="pb-0">
          <div className="flex justify-between items-center">
            <div 
              className="flex items-center gap-4 cursor-pointer" 
              onClick={() => setOdfLogsExpanded(!odfLogsExpanded)}
            >
              <CardTitle className="text-2xl font-semibold">
                {title}
                {apiVersion && (
                  <Badge variant="outline" className="w-fit ml-2">
                    v{apiVersion}
                  </Badge>
                )}
                {odfLogsData?.totalSize && (
                  <span className="ml-2 text-sm font-normal text-muted-foreground">
                    (Taille totale: {odfLogsData.totalSize.formatted})
                  </span>
                )}
              </CardTitle>
              {odfLogsExpanded ? (
                <ChevronUp className="h-5 w-5 text-muted-foreground" />
              ) : (
                <ChevronDown className="h-5 w-5 text-muted-foreground" />
              )}
            </div>
            {odfLogsExpanded && (
              <div className="flex items-center gap-2">
                <Button 
                  variant="outline" 
                  size="sm" 
                  onClick={() => queryClient.invalidateQueries({ queryKey: ['odf-logs'] })}
                  className="flex items-center gap-2"
                >
                  <RefreshCw className="h-4 w-4" />
                  Actualiser
                </Button>
                <Button 
                  variant="outline" 
                  size="sm" 
                  onClick={() => window.location.href = showPagination ? '/' : '/logs/odf'}
                  className="flex items-center gap-2"
                >
                  <List className="h-4 w-4" />
                  {showPagination ? "Retour à l'accueil" : "Voir tous les logs ODF"}
                </Button>
              </div>
            )}
          </div>
        </CardHeader>
      )}
      
      {/* Afficher la dernière exécution au-dessus du contenu */}
      {lastExecution?.date && showHeader && odfLogsExpanded && (
        <div className="px-6 pt-2 pb-0 text-sm text-muted-foreground flex items-center gap-2">
          Dernière exécution : {lastExecution.date}
          {getStatusIcon(lastExecution.status)}
        </div>
      )}
      
      {odfLogsExpanded && (
        <CardContent>
          {odfLogsLoading ? (
            <div className="flex justify-center items-center p-8">
              <Loader2 className="h-8 w-8 animate-spin text-primary" />
            </div>
          ) : odfLogsError ? (
            <div className="flex flex-col items-center justify-center gap-4 p-8">
              <XCircle className="h-12 w-12 text-red-500" />
              <div className="text-xl font-medium text-center">Erreur de chargement</div>
              <div className="text-sm text-muted-foreground text-center max-w-md">
                {(odfLogsError as Error).message}
              </div>
              <Button 
                onClick={() => queryClient.invalidateQueries({ queryKey: ['odf-logs'] })}
                className="mt-4"
              >
                Réessayer
              </Button>
            </div>
          ) : odfLogs.length === 0 ? (
            <div className="flex flex-col items-center justify-center gap-4 p-8">
              <FileX className="h-12 w-12 text-muted-foreground" />
              <div className="text-xl font-medium">Aucun log ODF</div>
              <div className="text-sm text-muted-foreground text-center max-w-md">
                Aucun log d'exécution ODF n'a été trouvé.
              </div>
            </div>
          ) : (
            <>
              <div className="rounded-md border">
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead className="text-center">ODF</TableHead>
                      <TableHead className="text-center">Statut</TableHead>
                      <TableHead className="text-center">Temps d'exécution</TableHead>
                      <TableHead className="text-center">Temps de pause</TableHead>
                      <TableHead className="text-center">Date de création</TableHead>
                      <TableHead className="text-center">Nombre de sessions</TableHead>
                      <TableHead className="text-center">Taille</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {paginatedLogs.map((log: OdfLog) => (
                      <React.Fragment key={log.id}>
                        <TableRow 
                          className={log.executionsCount > 0 ? "cursor-pointer hover:bg-gray-50" : ""}
                          onClick={() => log.executionsCount > 0 && toggleOdfExpansion(log.id)}
                        >
                          <TableCell className="font-medium text-center">
                            <div className="flex items-center justify-center gap-2">
                              {log.name}
                              {log.executionsCount > 0 && (
                                expandedOdfs.includes(log.id) 
                                  ? <ChevronUp className="h-4 w-4 text-muted-foreground" /> 
                                  : <ChevronDown className="h-4 w-4 text-muted-foreground" />
                              )}
                            </div>
                          </TableCell>
                          <TableCell className="text-center">
                            <span className={`px-2 py-1 rounded-full text-xs font-medium ${getStatusClass(log.status)}`}>
                              {log.status}
                            </span>
                          </TableCell>
                          <TableCell className="text-center">
                            {formatExecutionTime(log.executionTime, false)}
                          </TableCell>
                          <TableCell className="text-center">
                            {formatExecutionTime(log.executionTimePause, false)}
                          </TableCell>
                          <TableCell className="text-center">{new Date(log.createdAt).toLocaleString('fr-FR')}</TableCell>
                          <TableCell className="text-center">
                            <Badge variant="outline">
                              {log.sessionsCount === 1 
                                ? "1 session" 
                                : `${log.sessionsCount} sessions`}
                            </Badge>
                          </TableCell>
                          <TableCell className="text-center">
                            {log.size ? (
                              <span className="text-xs font-bold">{log.size.formatted}</span>
                            ) : (
                              <span className="text-xs text-muted-foreground">N/A</span>
                            )}
                          </TableCell>
                        </TableRow>
                        
                        {/* Afficher les sessions si l'ODF est développé */}
                        {expandedOdfs.includes(log.id) && log.sessions.length > 0 && (
                          <TableRow className="bg-gray-50">
                            <TableCell colSpan={7} className="p-0">
                              <div className="p-4 border-l-4 border-green-400 bg-green-50 rounded-md shadow-sm ml-4 mr-4 mb-2">
                                <h4 className="text-sm font-medium mb-2 text-green-700 flex items-center">
                                  <ArrowRight className="h-4 w-4 mr-1" />
                                  Sessions associées ({log.sessionsCount === 1 
                                    ? "1 session" 
                                    : `${log.sessionsCount} sessions`})
                                </h4>
                                <Table className="border border-green-200 rounded-md overflow-hidden">
                                  <TableHeader className="bg-green-100">
                                    <TableRow>
                                      <TableHead className="text-center">ID Session</TableHead>
                                      <TableHead className="text-center">Dernière étape connue</TableHead>
                                      <TableHead className="text-center">Nombre d'exécutions</TableHead>
                                      <TableHead className="text-center">Temps d'exécution</TableHead>
                                      <TableHead className="text-center">Date de création</TableHead>
                                      <TableHead className="text-center">Identifiant Wavesoft</TableHead>
                                    </TableRow>
                                  </TableHeader>
                                  <TableBody>
                                    {log.sessions.map((session) => (
                                      <React.Fragment key={session.id}>
                                        <TableRow 
                                          className={session.executionsCount > 0 ? "cursor-pointer hover:bg-gray-100" : ""}
                                          onClick={() => session.executionsCount > 0 && toggleSessionExpansion(session.id)}
                                        >
                                          <TableCell className="font-mono text-xs text-center">
                                            <div className="flex items-center gap-2">
                                              {session.sessionId}
                                              {session.executionsCount > 0 && (
                                                expandedSessions.includes(session.id) 
                                                  ? <ChevronUp className="h-3 w-3 text-muted-foreground" /> 
                                                  : <ChevronDown className="h-3 w-3 text-muted-foreground" />
                                              )}
                                            </div>
                                          </TableCell>
                                          <TableCell className="text-center">
                                            <span className={`px-2 py-1 rounded-full text-xs font-medium ${
                                              session.stepsStatus !== undefined && session.stepsStatus !== null
                                                ? getStepNumberClass(session.status)
                                                : (session.status && !isNaN(parseInt(session.status))
                                                  ? getStepNumberClass(session.status)
                                                  : getStepStatusClass(session.status))
                                            }`}>
                                              {session.stepsStatus !== undefined && session.stepsStatus !== null
                                                ? `Étape ${session.stepsStatus}`
                                                : (session.status && !isNaN(parseInt(session.status))
                                                  ? `Étape ${session.status}`
                                                  : (session.status || "-"))
                                              }
                                            </span>
                                          </TableCell>
                                          <TableCell className="text-center">
                                            <Badge variant="outline">
                                              {session.executionsCount === 1 
                                                ? "1 exécution" 
                                                : `${session.executionsCount} exécutions`}
                                            </Badge>
                                          </TableCell>
                                          <TableCell className="text-center">{formatExecutionTime(calculateSessionExecutionTime(session), true)}</TableCell>
                                          <TableCell className="text-center">
                                            {new Date(session.createdAt).toLocaleString('fr-FR')}
                                          </TableCell>
                                          <TableCell className="text-center">
                                            {session.userName ? (
                                              <span className="text-xs font-medium">{session.userName}</span>
                                            ) : (
                                              <span className="text-xs text-muted-foreground">N/A</span>
                                            )}
                                          </TableCell>
                                        </TableRow>
                                        
                                        {/* Afficher les exécutions si la session est développée */}
                                        {expandedSessions.includes(session.id) && session.executions.length > 0 && (
                                          <TableRow className="bg-gray-50">
                                            <TableCell colSpan={7} className="p-0">
                                              <div className="p-4 border-l-4 border-blue-400 bg-blue-50 rounded-md shadow-sm ml-4 mr-4 mb-2">
                                                <h4 className="text-xs font-medium mb-2 text-blue-700 flex items-center">
                                                  <ArrowRight className="h-3 w-3 mr-1" />
                                                  Détails des exécutions ({session.executionsCount === 1 
                                                    ? "1 exécution" 
                                                    : `${session.executionsCount} exécutions`})
                                                </h4>
                                                <Table className="border border-blue-200 rounded-md overflow-hidden">
                                                  <TableHeader className="bg-blue-100">
                                                    <TableRow>
                                                      <TableHead className="text-center">ID</TableHead>
                                                      <TableHead className="text-center">Étape</TableHead>
                                                      <TableHead className="text-center">Temps d'exécution</TableHead>
                                                      <TableHead className="text-center">Date de création</TableHead>
                                                      <TableHead className="text-center">Étapes</TableHead>
                                                    </TableRow>
                                                  </TableHeader>
                                                  <TableBody>
                                                    {session.executions.map((execution) => (
                                                      <TableRow key={execution.id}>
                                                        <TableCell className="font-medium text-xs text-center">
                                                          {execution.id}
                                                        </TableCell>
                                                        <TableCell className="text-center">
                                                          <span className={`px-2 py-1 rounded-full text-xs font-medium ${
                                                            execution.status !== undefined && execution.status !== null
                                                              ? getStepNumberClass(execution.status)
                                                              : (execution.status 
                                                                ? getStepNumberClass(execution.status) 
                                                                : 'bg-gray-100 text-gray-800')
                                                          }`}>
                                                            {execution.stepsStatus !== undefined && execution.stepsStatus !== null
                                                              ? `${execution.stepsStatus} - ${getStepName(execution.stepsStatus)}`
                                                              : (execution.stepStatus 
                                                                ? `${execution.stepStatus} - ${getStepName(execution.stepStatus)}`
                                                                : "Étape inconnue")
                                                            }
                                                          </span>
                                                        </TableCell>
                                                        <TableCell className="text-xs text-center">
                                                          {formatExecutionTime(execution.executionTime, true)}
                                                        </TableCell>
                                                        <TableCell className="text-center">
                                                          {new Date(execution.createdAt).toLocaleString('fr-FR')}
                                                        </TableCell>
                                                        <TableCell className="text-center">
                                                          {execution.steps && execution.steps.length > 0 ? (
                                                            <Button 
                                                              variant="link" 
                                                              className="text-xs text-blue-500 p-0 h-auto"
                                                              onClick={(e) => {
                                                                e.stopPropagation();
                                                                // Assurez-vous que les étapes sont correctement formatées
                                                                const formattedSteps = execution.steps || [];
                                                                setSelectedSteps(formattedSteps);
                                                                setStepsModalOpen(true);
                                                              }}
                                                            >
                                                              Voir les étapes
                                                            </Button>
                                                          ) : execution.step ? (
                                                            <Button 
                                                              variant="link" 
                                                              className="text-xs text-blue-500 p-0 h-auto"
                                                              onClick={(e) => {
                                                                e.stopPropagation();
                                                                // Gérer le cas où step est une chaîne ou un tableau
                                                                try {
                                                                  const stepData = typeof execution.step === 'string' 
                                                                    ? JSON.parse(execution.step || '[]') 
                                                                    : (Array.isArray(execution.step) ? execution.step : []);
                                                                  
                                                                  // S'assurer que les données ont le bon format
                                                                  const formattedSteps: OdfStep[] = Array.isArray(stepData) 
                                                                    ? stepData.map(step => ({
                                                                        id: step.id || `step-${Math.random().toString(36).substring(2, 9)}`,
                                                                        name: step.name || 'Étape sans nom',
                                                                        status: step.status || 'unknown',
                                                                        time: step.time || null,
                                                                        details: step.details || {}
                                                                      }))
                                                                    : [{ 
                                                                        id: "parsed", 
                                                                        name: "Données parsées", 
                                                                        status: "info",
                                                                        time: null,
                                                                        details: { rawData: stepData }
                                                                      }];
                                                                  
                                                                  setSelectedSteps(formattedSteps);
                                                                  setStepsModalOpen(true);
                                                                } catch (error) {
                                                                  console.error("Erreur lors du parsing des étapes:", error);
                                                                  // Afficher les données brutes en cas d'erreur
                                                                  setSelectedSteps([{ 
                                                                    id: "error", 
                                                                    name: "Erreur de parsing", 
                                                                    status: "error",
                                                                    time: null,
                                                                    details: { 
                                                                      error: String(error),
                                                                      rawData: execution.step 
                                                                    }
                                                                  }]);
                                                                  setStepsModalOpen(true);
                                                                }
                                                              }}
                                                            >
                                                              Voir les étapes (raw)
                                                            </Button>
                                                          ) : (
                                                            <span className="text-xs text-muted-foreground">Aucune étape</span>
                                                          )}
                                                        </TableCell>
                                                      </TableRow>
                                                    ))}
                                                  </TableBody>
                                                </Table>
                                              </div>
                                            </TableCell>
                                          </TableRow>
                                        )}
                                      </React.Fragment>
                                    ))}
                                  </TableBody>
                                </Table>
                              </div>
                            </TableCell>
                          </TableRow>
                        )}
                      </React.Fragment>
                    ))}
                  </TableBody>
                </Table>
              </div>
              {showPagination && totalPages > 1 && (
                <div className="mt-4">
                  <Pagination
                    currentPage={currentPage}
                    totalPages={totalPages}
                    onPageChange={setCurrentPage}
                  />
                </div>
              )}
            </>
          )}
        </CardContent>
      )}

      {/* Modal pour afficher le contenu JSON des étapes */}
      <StepsModal 
        open={stepsModalOpen}
        onOpenChange={setStepsModalOpen}
        steps={selectedSteps}
      />
    </Card>
  );
};

export default OdfLogs; 