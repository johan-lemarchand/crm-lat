import { Card, CardContent, CardHeader } from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Badge } from '@/components/ui/badge';
import { JsonFormatter } from '../JsonFormatter';
import { ApiLog } from '@/types/logs';

interface ApiLogViewerProps {
  apiLogs: ApiLog[];
}

export function ApiLogViewer({ apiLogs }: ApiLogViewerProps) {
  return (
    <div className="space-y-4 p-4">
      {apiLogs
        .sort((a, b) => new Date(b.createdAt).getTime() - new Date(a.createdAt).getTime())
        .map((apiLog) => (
          <Card key={apiLog.id} className="border border-gray-200">
            <CardHeader className="py-3">
              <div className="flex justify-between items-center">
                <div className="flex items-center gap-3">
                  <Badge variant={apiLog.statusCode === 200 ? 'success' : 'destructive'}>
                    {apiLog.method}
                  </Badge>
                  <span className="font-mono text-sm">{apiLog.endpoint}</span>
                </div>
                <div className="flex items-center gap-4">
                  <Badge variant="outline">
                    {apiLog.duration.toFixed(2)}s
                  </Badge>
                  <Badge variant={apiLog.statusCode === 200 ? 'success' : 'destructive'}>
                    {apiLog.statusCode}
                  </Badge>
                </div>
              </div>
            </CardHeader>
            <CardContent>
              <Tabs defaultValue="request" className="w-full">
                <TabsList className="w-full">
                  <TabsTrigger value="request" className="flex-1">Requête</TabsTrigger>
                  <TabsTrigger value="response" className="flex-1">Réponse</TabsTrigger>
                </TabsList>
                <TabsContent value="request">
                  <JsonFormatter data={apiLog.requestXml} />
                </TabsContent>
                <TabsContent value="response">
                  <JsonFormatter data={apiLog.responseXml} />
                </TabsContent>
              </Tabs>
            </CardContent>
          </Card>
        ))}
    </div>
  );
} 