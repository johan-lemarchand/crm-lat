import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Loader2, FileText, ArrowRight } from 'lucide-react';
import { useToast } from '@/components/ui/use-toast';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { api } from '@/lib/api';
import { useNavigate } from 'react-router-dom';
import { renderCommandExecutionEmail } from '@/emails/CommandExecutionEmail';
import { renderCurrencySyncEmail } from '@/emails/CurrencySyncEmail';

export function EmailTemplateFromReact() {
  const { toast } = useToast();
  const [isOpen, setIsOpen] = useState(false);
  const navigate = useNavigate();
  const queryClient = useQueryClient();

  // Templates disponibles
  const availableTemplates = [
    {
      id: 'command_execution',
      name: 'Exécution de Commande',
      description: 'Email envoyé lors de l\'exécution d\'une commande',
      renderFn: async () => {
        return await renderCommandExecutionEmail({
          commandName: 'exemple-commande',
          scriptName: 'script-exemple',
          status: 'success',
          executionDate: new Date().toLocaleString()
        });
      },
      // Fonction qui convertit l'HTML rendu en template avec placeholders
      convertToTemplate: (html: string) => {
        return html
          .replace(/>exemple-commande</g, '>{{commandName}}<')
          .replace(/>script-exemple</g, '>{{scriptName}}<')
          .replace(/class="[^"]*text-green-500[^"]*"/g, 'class="{{statusColorClass}}"')
          .replace(/>Succès</g, '>{{statusText}}<')
          .replace(new RegExp(escapeRegExp(new Date().toLocaleString()), 'g'), '{{executionDate}}');
      }
    },
    {
      id: 'currency_sync',
      name: 'Synchronisation des Devises',
      description: 'Rapport de synchronisation des devises',
      renderFn: async () => {
        return await renderCurrencySyncEmail({
          status: 'success',
          executionDate: new Date().toLocaleString(),
          total_devises: 5,
          mises_a_jour: 3,
          erreurs: 0,
          currencies: {
            'USD': {
              last_update: new Date(Date.now() - 86400000).toISOString(), // Hier
              old_rate: 1.08,
              new_rate: 1.09234,
              summary: {
                articles_updated: 12
              }
            },
            'GBP': {
              last_update: new Date(Date.now() - 86400000).toISOString(),
              old_rate: 0.85,
              new_rate: 0.84321,
              summary: {
                articles_updated: 8
              }
            }
          }
        });
      },
      convertToTemplate: (html: string) => {
        const now = new Date();
        const yesterday = new Date(now.getTime() - 86400000);
        
        return html
          .replace(/class="[^"]*text-green-500[^"]*"/g, 'class="{{statusColorClass}}"')
          .replace(/>Succès</g, '>{{statusText}}<')
          .replace(new RegExp(escapeRegExp(now.toLocaleString()), 'g'), '{{executionDate}}')
          .replace(/>5</g, '>{{total_devises}}<')
          .replace(/>3</g, '>{{mises_a_jour}}<')
          .replace(/>0</g, '>{{erreurs}}<')
          .replace(/>USD</g, '>{{currency1}}<')
          .replace(/>GBP</g, '>{{currency2}}<')
          .replace(new RegExp(escapeRegExp(yesterday.toLocaleString()), 'g'), '{{lastUpdateDate}}')
          .replace(/>1\.08</g, '>{{old_rate1}}<')
          .replace(/>1\.09234</g, '>{{new_rate1}}<')
          .replace(/>0\.85</g, '>{{old_rate2}}<')
          .replace(/>0\.84321</g, '>{{new_rate2}}<')
          .replace(/>12</g, '>{{articles_updated1}}<')
          .replace(/>8</g, '>{{articles_updated2}}<');
      }
    }
  ];

  // Mutation pour sauvegarder le template importé
  const saveTemplateMutation = useMutation({
    mutationFn: async ({ name, content }: { name: string, content: string }) => {
      return api.post(`/api/email-templates/${name}`, { content });
    },
    onSuccess: (_, variables) => {
      toast({
        title: 'Template importé',
        description: `Le template "${variables.name}" a été importé avec succès.`
      });
      queryClient.invalidateQueries({ queryKey: ['email-templates'] });
      setIsOpen(false);
      // Rediriger vers l'édition du template
      navigate(`/email-templates/${variables.name}/edit`);
    },
    onError: () => {
      toast({
        title: 'Erreur',
        description: 'L\'importation du template a échoué',
        variant: 'destructive',
      });
    }
  });

  // Fonction pour échapper les caractères spéciaux dans une regex
  function escapeRegExp(string: string) {
    return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
  }

  // Fonction pour importer un template
  const importTemplate = async (template: typeof availableTemplates[0]) => {
    try {
      // Rendre l'HTML du composant React
      const html = await template.renderFn();
      // Convertir l'HTML en template avec placeholders
      const templateContent = template.convertToTemplate(html);
      // Sauvegarder le template
      saveTemplateMutation.mutate({
        name: template.id,
        content: templateContent
      });
    } catch (error) {
      console.error('Erreur lors de la génération du template:', error);
      toast({
        title: 'Erreur',
        description: 'Une erreur est survenue lors de la génération du template',
        variant: 'destructive',
      });
    }
  };

  return (
    <>
      <Button variant="outline" onClick={() => setIsOpen(true)}>
        <FileText className="mr-2 h-4 w-4" /> Importer depuis React
      </Button>

      {isOpen && (
        <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
          <Card className="w-[600px] max-h-[80vh] overflow-auto">
            <CardHeader>
              <CardTitle>Importer depuis les composants React</CardTitle>
            </CardHeader>
            <CardContent>
              <p className="text-sm text-muted-foreground mb-4">
                Sélectionnez un template à importer depuis les composants React existants :
              </p>

              <div className="space-y-3">
                {availableTemplates.map((template) => (
                  <div key={template.id} className="bg-gray-50 p-4 rounded-md">
                    <div className="flex justify-between items-start">
                      <div>
                        <h3 className="font-medium">{template.name}</h3>
                        <p className="text-sm text-muted-foreground">{template.description}</p>
                      </div>
                      <Button 
                        variant="ghost" 
                        size="sm" 
                        onClick={() => importTemplate(template)}
                        disabled={saveTemplateMutation.isPending}
                      >
                        {saveTemplateMutation.isPending ? (
                          <Loader2 className="h-4 w-4 animate-spin" />
                        ) : (
                          <>
                            <span className="mr-1">Importer</span>
                            <ArrowRight className="h-3 w-3" />
                          </>
                        )}
                      </Button>
                    </div>
                  </div>
                ))}
              </div>

              <div className="flex justify-end mt-6">
                <Button variant="outline" onClick={() => setIsOpen(false)}>
                  Fermer
                </Button>
              </div>
            </CardContent>
          </Card>
        </div>
      )}
    </>
  );
} 