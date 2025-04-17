import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Loader2, Plus, Edit, Trash, Eye } from 'lucide-react';
import { useToast } from '@/components/ui/use-toast';
import { useNavigate } from 'react-router-dom';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { api } from '@/lib/api';
import React from 'react';

interface Template {
  name: string;
  updatedAt: string;
}

interface ApiResponse<T> {
  data: T;
}

export function EmailTemplatesPage() {
  const { toast } = useToast();
  const navigate = useNavigate();
  const queryClient = useQueryClient();

  const { data: templates = [], isLoading, error } = useQuery<Template[]>({
    queryKey: ['email-templates'],
    queryFn: async () => {
      try {
        const response = await api.get<ApiResponse<Template[]>>('/api/email-templates');
        // S'assurer que response.data est toujours un tableau
        return Array.isArray(response.data) ? response.data : [];
      } catch (error) {
        console.error('Erreur lors du chargement des templates:', error);
        // Retourner un tableau vide en cas d'erreur au lieu de undefined
        return [];
      }
    },
    // Désactiver les retries automatiques pour éviter trop de requêtes en cas d'échec
    retry: 1
  });

  // Afficher une notification en cas d'erreur
  React.useEffect(() => {
    if (error) {
      toast({
        title: 'Erreur',
        description: 'Impossible de charger les templates',
        variant: 'destructive',
      });
    }
  }, [error, toast]);

  const deleteTemplateMutation = useMutation({
    mutationFn: async (name: string) => {
      try {
        await api.delete(`/api/email-templates/${name}`);
        return true;
      } catch (error) {
        console.error('Erreur lors de la suppression:', error);
        throw error;
      }
    },
    onSuccess: () => {
      toast({
        title: 'Succès',
        description: 'Template supprimé avec succès',
      });
      queryClient.invalidateQueries({ queryKey: ['email-templates'] });
    },
    onError: () => {
      toast({
        title: 'Erreur',
        description: 'Impossible de supprimer le template',
        variant: 'destructive',
      });
    }
  });

  const handleDelete = (name: string) => {
    deleteTemplateMutation.mutate(name);
  };

  return (
    <div className="container py-10">
      <div className="flex justify-between items-center mb-6">
        <h1 className="text-3xl font-bold">Templates d'Emails</h1>
        <div className="flex gap-2">
          <Button onClick={() => navigate('/email-templates/new')}>
            <Plus className="mr-2 h-4 w-4" /> Nouveau Template
          </Button>
        </div>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Templates disponibles</CardTitle>
          <CardDescription>Gérez vos templates d'emails pour les notifications et rapports</CardDescription>
        </CardHeader>
        <CardContent>
          {isLoading ? (
            <div className="flex justify-center p-8">
              <Loader2 className="h-8 w-8 animate-spin" />
            </div>
          ) : templates.length === 0 ? (
            <div className="text-center p-8 text-muted-foreground">
              Aucun template disponible. Créez votre premier template en cliquant sur "Nouveau Template".
            </div>
          ) : (
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Nom</TableHead>
                  <TableHead>Dernière mise à jour</TableHead>
                  <TableHead className="text-right">Actions</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {templates.map((template: Template) => (
                  <TableRow key={template.name}>
                    <TableCell className="font-medium">{template.name}</TableCell>
                    <TableCell>{new Date(template.updatedAt).toLocaleString()}</TableCell>
                    <TableCell className="text-right">
                      <div className="flex justify-end gap-2">
                        <Button variant="outline" size="icon" onClick={() => navigate(`/email-templates/${template.name}/preview`)}>
                          <Eye className="h-4 w-4" />
                        </Button>
                        <Button variant="outline" size="icon" onClick={() => navigate(`/email-templates/${template.name}/edit`)}>
                          <Edit className="h-4 w-4" />
                        </Button>
                        <Button variant="outline" size="icon" onClick={() => handleDelete(template.name)}>
                          <Trash className="h-4 w-4" />
                        </Button>
                      </div>
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          )}
        </CardContent>
      </Card>
    </div>
  );
}
