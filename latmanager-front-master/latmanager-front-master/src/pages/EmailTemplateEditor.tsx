import { useState, useEffect, useRef } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Loader2, Save, ArrowLeft, Eye, FileText } from 'lucide-react';
import { useToast } from '@/components/ui/use-toast';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { api, getTwigTemplate, getAvailableTwigTemplates } from '@/lib/api';
import { Editor } from '@tinymce/tinymce-react';

// Définir l'interface des props
interface EmailTemplateEditorProps {
  initialTab?: string;
}

// Interface pour les données du template
interface TemplateData {
  name: string;
  content: string;
  updatedAt: string;
}

// Type pour l'éditeur TinyMCE
interface TinyMCEEditor {
  ui: {
    registry: {
      addMenuButton: (
        identifier: string, 
        config: {
          text: string;
          fetch: (callback: (items: any[]) => void) => void;
        }
      ) => void;
    };
  };
  insertContent: (content: string) => void;
}

// Interface pour les réponses API
interface ApiResponse<T> {
  data: T;
}

// Interface pour un template Twig
interface TwigTemplate {
  id: string;
  name: string;
  description: string;
}

// Ajouter la prop au composant
export function EmailTemplateEditor({ initialTab = 'edit' }: EmailTemplateEditorProps) {
  const { name = 'new' } = useParams<{ name?: string }>();
  const isNew = name === 'new';
  const navigate = useNavigate();
  const { toast } = useToast();
  const queryClient = useQueryClient();
  
  const [templateName, setTemplateName] = useState('');
  const [content, setContent] = useState('');
  const [previewHtml, setPreviewHtml] = useState('');
  const [activeTab, setActiveTab] = useState(initialTab);
  const [showTwigImport, setShowTwigImport] = useState(false);
  const [twigTemplates, setTwigTemplates] = useState<TwigTemplate[]>([]);
  const [loadingTemplates, setLoadingTemplates] = useState(false);
  
  const editorRef = useRef<any>(null);

  const { data, isLoading } = useQuery<TemplateData>({
    queryKey: ['email-template', name],
    queryFn: async () => {
      if (isNew || !name || name === 'undefined') {
        return { name: '', content: '', updatedAt: '' };
      }
      
      try {
        const response = await api.get<ApiResponse<TemplateData>>(`/api/email-templates/${name}`);
        return response.data;
      } catch (error) {
        console.error('Erreur lors du chargement du template:', error);
        throw error;
      }
    },
    enabled: !isNew && !!name && name !== 'undefined'
  });

  // Utiliser useEffect pour mettre à jour les états après le chargement des données
  useEffect(() => {
    if (data && !isNew) {
      setTemplateName(data.name);
      setContent(data.content);
    }
  }, [data, isNew]);

  // Gérer les erreurs avec useEffect
  useEffect(() => {
    const handleError = () => {
      toast({
        title: 'Erreur',
        description: 'Impossible de charger le template',
        variant: 'destructive',
      });
      navigate('/email-templates');
    };

    // Si la requête a échoué, afficher une erreur
    if (!isLoading && !data && !isNew) {
      handleError();
    }
  }, [isLoading, data, isNew, toast, navigate]);

  interface SaveTemplateResponse {
    name: string;
    updatedAt: string;
  }

  interface PreviewResponse {
    content: string;
  }

  const saveTemplateMutation = useMutation({
    mutationFn: async () => {
      try {
        const response = await api.post<ApiResponse<SaveTemplateResponse>>(`/api/email-templates/${templateName}`, {
          content
        });
        return response.data;
      } catch (error: any) {
        console.error('Erreur lors de la sauvegarde:', error);
        // Extraire le message d'erreur de la réponse
        const errorMessage = error.response?.data?.error || 'Impossible d\'enregistrer le template';
        throw new Error(errorMessage);
      }
    },
    onSuccess: () => {
      toast({
        title: 'Succès',
        description: 'Template enregistré avec succès',
        variant: 'success',
      });
      
      queryClient.invalidateQueries({ queryKey: ['email-templates'] });
      queryClient.invalidateQueries({ queryKey: ['email-template', templateName] });
      
      if (isNew) {
        navigate(`/email-templates/${templateName}/edit`);
      }
    },
    onError: (error: any) => {
      toast({
        title: 'Erreur',
        description: error.message || 'Impossible d\'enregistrer le template',
        variant: 'destructive',
      });
    }
  });

  const previewMutation = useMutation({
    mutationFn: async () => {
      // Pour les nouveaux templates, envoyer directement le contenu actuel sans faire d'appel API
      if (isNew) {
        return { content: content || '<div>Aucun contenu à prévisualiser</div>' };
      }
      
      // Utiliser le nom actuel du template pour la prévisualisation
      const previewName = name;
      
      try {
        const response = await api.post<ApiResponse<PreviewResponse>>(`/api/email-templates/${previewName}/preview`, {
          // Données d'exemple pour la prévisualisation
          title: 'Titre d\'exemple',
          message: 'Message d\'exemple',
          items: [
            { name: 'Item 1', value: 100 },
            { name: 'Item 2', value: 200 },
          ]
        });
        return response.data;
      } catch (error) {
        console.error('Erreur lors de la prévisualisation:', error);
        throw error;
      }
    },
    onSuccess: (response: { content: string }) => {
      setPreviewHtml(response.content);
      setActiveTab('preview');
    },
    onError: () => {
      toast({
        title: 'Erreur',
        description: 'Impossible de générer la prévisualisation',
        variant: 'destructive',
      });
    }
  });

  // Fonction pour convertir le template Twig en template utilisable dans l'éditeur
  const importTwigTemplate = (twigContent: string): string => {
    // Convertir les tags Twig en commentaires ou adaptations pour l'éditeur
    let content = twigContent;
    
    // Remplacer les blocs if/for par des commentaires
    content = content.replace(/{%\s*if\s+([^}]*)\s*%}/g, '<!-- IF $1 -->');
    content = content.replace(/{%\s*else\s*%}/g, '<!-- ELSE -->');
    content = content.replace(/{%\s*endif\s*%}/g, '<!-- ENDIF -->');
    content = content.replace(/{%\s*for\s+([^}]*)\s*%}/g, '<!-- FOREACH $1 -->');
    content = content.replace(/{%\s*endfor\s*%}/g, '<!-- ENDFOREACH -->');
    
    // Remplacer les variables Twig par des placeholders
    content = content.replace(/{{([^}]*)}}/g, '{{$1}}');
    
    return content;
  };
  
  // Fonction pour charger un template Twig
  const loadTwigTemplate = async (templateId: string) => {
    try {
      setShowTwigImport(false);
      
      // Afficher toast de chargement
      toast({
        title: 'Chargement',
        description: 'Importation du template en cours...',
        variant: 'warning',
      });
      
      // Charger le template du backend
      const twigContent = await getTwigTemplate(templateId);
      
      // Convertir le template Twig en HTML pour l'éditeur
      const editorContent = importTwigTemplate(twigContent);
      
      // Mettre à jour le contenu de l'éditeur
      setContent(editorContent);
      
      // Afficher toast de succès
      toast({
        title: 'Succès',
        description: 'Template importé avec succès',
        variant: 'success',
      });
    } catch (error) {
      console.error('Erreur lors du chargement du template Twig:', error);
      toast({
        title: 'Erreur',
        description: 'Impossible de charger le template',
        variant: 'destructive',
      });
    }
  };
  
  // Charger la liste des templates Twig disponibles
  const loadTwigTemplatesList = async () => {
    try {
      setLoadingTemplates(true);
      const templates = await getAvailableTwigTemplates();
      setTwigTemplates(templates);
    } catch (error) {
      console.error('Erreur lors du chargement des templates Twig:', error);
      toast({
        title: 'Erreur',
        description: 'Impossible de charger la liste des templates',
        variant: 'destructive',
      });
    } finally {
      setLoadingTemplates(false);
    }
  };
  
  // Déclencher le chargement des templates quand on ouvre le modal
  useEffect(() => {
    if (showTwigImport) {
      loadTwigTemplatesList();
    }
  }, [showTwigImport]);

  const handleSave = () => {
    if (!templateName.trim()) {
      toast({
        title: 'Erreur',
        description: 'Le nom du template est requis',
        variant: 'destructive',
      });
      return;
    }

    saveTemplateMutation.mutate();
  };

  const handlePreview = () => {
    // Pour les nouveaux templates, on peut prévisualiser directement sans appel API
    if (isNew) {
      if (!content.trim()) {
        setPreviewHtml('<div class="text-center p-4">Aucun contenu à prévisualiser</div>');
      } else {
        setPreviewHtml(content);
      }
      setActiveTab('preview');
      return;
    }
    
    // Pour les templates existants, on fait l'appel API
    previewMutation.mutate();
  };

  if (isLoading) {
    return (
      <div className="flex justify-center items-center h-96">
        <Loader2 className="h-8 w-8 animate-spin" />
      </div>
    );
  }

  return (
    <div className="container py-10">
      <Button variant="ghost" onClick={() => navigate('/email-templates')} className="mb-6">
        <ArrowLeft className="mr-2 h-4 w-4" /> Retour
      </Button>
      
      <div className="flex justify-between items-center mb-6">
        <h1 className="text-3xl font-bold">
          {isNew ? 'Nouveau Template' : `Édition: ${name}`}
        </h1>
        <div className="flex gap-2">
          <Button 
            variant="outline" 
            onClick={() => setShowTwigImport(true)}
          >
            <FileText className="mr-2 h-4 w-4" />
            Importer Twig
          </Button>
          <Button 
            variant="outline" 
            onClick={handlePreview} 
            disabled={previewMutation.isPending && !isNew}
          >
            {previewMutation.isPending && !isNew ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : <Eye className="mr-2 h-4 w-4" />}
            Prévisualiser
          </Button>
          <Button 
            onClick={handleSave} 
            disabled={saveTemplateMutation.isPending}
          >
            {saveTemplateMutation.isPending ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : <Save className="mr-2 h-4 w-4" />}
            Enregistrer
          </Button>
        </div>
      </div>

      {isNew && (
        <div className="mb-6">
          <label htmlFor="templateName" className="block text-sm font-medium mb-2">
            Nom du template
          </label>
          <Input
            id="templateName"
            placeholder="Nom du template"
            value={templateName}
            onChange={(e) => setTemplateName(e.target.value)}
            className="max-w-md"
          />
        </div>
      )}

      <Card className="mb-6">
        <CardHeader>
          <CardTitle>Éditeur de Template</CardTitle>
        </CardHeader>
        <CardContent>
          <Tabs value={activeTab} onValueChange={setActiveTab}>
            <TabsList className="mb-4">
              <TabsTrigger value="edit">Édition</TabsTrigger>
              <TabsTrigger value="preview">Prévisualisation</TabsTrigger>
              <TabsTrigger value="help">Aide</TabsTrigger>
            </TabsList>
            
            <TabsContent value="edit" className="min-h-[500px]">
              <Editor
                apiKey="hle22e9m3kx2a8f6r1m1h73kkhubnmnh91onuczlgy0909b1"
                value={content}
                onEditorChange={(newContent: string) => setContent(newContent)}
                init={{
                  height: 500,
                  menubar: true,
                  plugins: [
                    'advlist', 'autolink', 'lists', 'link', 'image', 'charmap', 'preview',
                    'anchor', 'searchreplace', 'visualblocks', 'code', 'fullscreen',
                    'insertdatetime', 'media', 'table', 'code', 'help', 'wordcount'
                  ],
                  toolbar: 'undo redo | formatselect | ' +
                    'bold italic backcolor forecolor | alignleft aligncenter ' +
                    'alignright alignjustify | bullist numlist outdent indent | ' +
                    'image table link | shadcn_components | removeformat | code | help',
                  content_style: `
                    body { font-family: ui-sans-serif, system-ui, sans-serif; font-size: 14px; margin: 20px; }
                    .placeholder { background-color: #ffffcc; padding: 2px 5px; border-radius: 3px; }
                  `,
                  setup: function (editor: TinyMCEEditor) {
                    setupShadcnComponents(editor);
                  }
                }}
              />
            </TabsContent>
            
            <TabsContent value="preview">
              <div className="border rounded-md p-4 min-h-[500px] bg-white">
                {previewHtml ? (
                  <div dangerouslySetInnerHTML={{ __html: previewHtml }} />
                ) : (
                  <div className="text-center p-8 text-muted-foreground">
                    Cliquez sur "Prévisualiser" pour voir le rendu du template
                  </div>
                )}
              </div>
            </TabsContent>
            
            <TabsContent value="help">
              <div className="prose max-w-none">
                <h3>Utilisation des variables</h3>
                <p>Vous pouvez utiliser les variables suivantes dans votre template:</p>
                <ul>
                  <li><code>{'{{title}}'}</code> - Titre du message</li>
                  <li><code>{'{{message}}'}</code> - Corps du message</li>
                </ul>
                
                <h3>Structures de contrôle</h3>
                <p>Vous pouvez utiliser des boucles et des conditions:</p>
                
                <h4>Boucles</h4>
                <pre>{`<!-- FOREACH items -->
  <div>{{item.name}}: {{item.value}}</div>
<!-- ENDFOREACH -->`}</pre>
                
                <h4>Conditions</h4>
                <pre>{`<!-- IF hasError -->
  <div class="error">Une erreur est survenue</div>
<!-- ENDIF -->`}</pre>
              </div>
            </TabsContent>
          </Tabs>
        </CardContent>
      </Card>

      {/* Modal pour l'importation de templates Twig */}
      {showTwigImport && (
        <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
          <Card className="w-[700px] max-h-[90vh] overflow-auto">
            <CardHeader>
              <CardTitle>Importer depuis Twig</CardTitle>
            </CardHeader>
            <CardContent>
              <p className="text-sm text-muted-foreground mb-4">
                Templates Twig disponibles :
              </p>

              {loadingTemplates ? (
                <div className="flex justify-center py-8">
                  <Loader2 className="h-8 w-8 animate-spin text-muted-foreground" />
                </div>
              ) : (
                <div className="space-y-3">
                  {twigTemplates.map((template) => (
                    <div key={template.id} className="bg-gray-50 p-4 rounded-md">
                      <div className="flex justify-between items-start">
                        <div>
                          <h3 className="font-medium">{template.name}</h3>
                          <p className="text-sm text-muted-foreground">{template.description}</p>
                        </div>
                        <Button 
                          variant="ghost" 
                          size="sm" 
                          onClick={() => loadTwigTemplate(template.id)}
                        >
                          <span className="mr-1">Importer</span>
                        </Button>
                      </div>
                    </div>
                  ))}
                  
                  {twigTemplates.length === 0 && (
                    <div className="text-center py-8 text-muted-foreground">
                      Aucun template Twig disponible
                    </div>
                  )}
                </div>
              )}

              <div className="flex justify-end mt-4">
                <Button variant="outline" onClick={() => setShowTwigImport(false)}>
                  Annuler
                </Button>
              </div>
            </CardContent>
          </Card>
        </div>
      )}
    </div>
  );
}

// Définition du type MenuItem pour éviter les 'any'
interface MenuItem {
  type: string;
  text: string;
  onAction?: () => void;
  getSubmenuItems?: () => MenuItem[];
}

// Fonction à ajouter à votre configuration TinyMCE
const setupShadcnComponents = (editor: TinyMCEEditor) => {
  // Ajouter un menu de composants
  editor.ui.registry.addMenuButton('shadcn_components', {
    text: 'Composants UI',
    fetch: (callback: (items: MenuItem[]) => void) => {
      const items: MenuItem[] = [
        {
          type: 'nestedmenuitem',
          text: 'Boutons',
          getSubmenuItems: () => [
            {
              type: 'menuitem',
              text: 'Bouton Primaire',
              onAction: () => {
                editor.insertContent(`
                  <a href="{{buttonLink}}" class="inline-flex items-center justify-center rounded-md text-sm font-medium ring-offset-background transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50 bg-primary text-primary-foreground hover:bg-primary/90 h-10 px-4 py-2 no-underline" style="background-color: #0f172a; color: white; border-radius: 0.375rem; padding: 0.5rem 1rem; text-decoration: none; font-weight: 500; font-family: ui-sans-serif, system-ui, sans-serif;">
                    {{buttonText}}
                  </a>
                `);
              }
            },
            {
              type: 'menuitem',
              text: 'Bouton Secondaire',
              onAction: () => {
                editor.insertContent(`
                  <a href="{{buttonLink}}" class="inline-flex items-center justify-center rounded-md text-sm font-medium ring-offset-background transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50 border border-input bg-background hover:bg-accent hover:text-accent-foreground h-10 px-4 py-2 no-underline" style="background-color: white; color: #0f172a; border: 1px solid #e2e8f0; border-radius: 0.375rem; padding: 0.5rem 1rem; text-decoration: none; font-weight: 500; font-family: ui-sans-serif, system-ui, sans-serif;">
                    {{buttonText}}
                  </a>
                `);
              }
            }
          ]
        },
        {
          type: 'nestedmenuitem',
          text: 'Cartes',
          getSubmenuItems: () => [
            {
              type: 'menuitem',
              text: 'Carte Simple',
              onAction: () => {
                editor.insertContent(`
                  <div class="rounded-lg border bg-card text-card-foreground shadow-sm" style="border: 1px solid #e2e8f0; border-radius: 0.5rem; background-color: white; padding: 1.5rem; font-family: ui-sans-serif, system-ui, sans-serif; margin-bottom: 1rem;">
                    <div style="margin-bottom: 1rem;">
                      <h3 style="font-size: 1.25rem; font-weight: 600; color: #0f172a; margin-top: 0; margin-bottom: 0.5rem;">{{cardTitle}}</h3>
                      <p style="color: #64748b; font-size: 0.875rem; margin-top: 0;">{{cardDescription}}</p>
                    </div>
                    <div style="margin-top: 1.5rem;">
                      {{cardContent}}
                    </div>
                  </div>
                `);
              }
            },
            {
              type: 'menuitem',
              text: 'Carte avec Entête & Pied',
              onAction: () => {
                editor.insertContent(`
                  <div class="rounded-lg border bg-card text-card-foreground shadow-sm" style="border: 1px solid #e2e8f0; border-radius: 0.5rem; background-color: white; font-family: ui-sans-serif, system-ui, sans-serif; margin-bottom: 1rem;">
                    <div style="padding: 1.5rem 1.5rem 0.75rem 1.5rem; border-bottom: 1px solid #e2e8f0;">
                      <h3 style="font-size: 1.25rem; font-weight: 600; color: #0f172a; margin-top: 0; margin-bottom: 0.5rem;">{{cardTitle}}</h3>
                      <p style="color: #64748b; font-size: 0.875rem; margin-top: 0;">{{cardDescription}}</p>
                    </div>
                    <div style="padding: 1.5rem;">
                      {{cardContent}}
                    </div>
                    <div style="padding: 0.75rem 1.5rem 1.5rem 1.5rem; border-top: 1px solid #e2e8f0;">
                      {{cardFooter}}
                    </div>
                  </div>
                `);
              }
            }
          ]
        },
        {
          type: 'nestedmenuitem',
          text: 'Alertes',
          getSubmenuItems: () => [
            {
              type: 'menuitem',
              text: 'Alerte Succès',
              onAction: () => {
                editor.insertContent(`
                  <div class="rounded-md border px-4 py-3 text-sm" style="background-color: #f0fdf4; border: 1px solid #bbf7d0; color: #15803d; border-radius: 0.375rem; padding: 0.75rem 1rem; font-family: ui-sans-serif, system-ui, sans-serif; margin-bottom: 1rem;">
                    <div style="display: flex; align-items: center;">
                      <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 0.5rem;"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                      <span>{{alertText}}</span>
                    </div>
                  </div>
                `);
              }
            },
            {
              type: 'menuitem',
              text: 'Alerte Erreur',
              onAction: () => {
                editor.insertContent(`
                  <div class="rounded-md border px-4 py-3 text-sm" style="background-color: #fef2f2; border: 1px solid #fecaca; color: #b91c1c; border-radius: 0.375rem; padding: 0.75rem 1rem; font-family: ui-sans-serif, system-ui, sans-serif; margin-bottom: 1rem;">
                    <div style="display: flex; align-items: center;">
                      <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 0.5rem;"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                      <span>{{alertText}}</span>
                    </div>
                  </div>
                `);
              }
            }
          ]
        },
        {
          type: 'nestedmenuitem',
          text: 'Badges',
          getSubmenuItems: () => [
            {
              type: 'menuitem',
              text: 'Badge Par Défaut',
              onAction: () => {
                editor.insertContent(`
                  <span style="display: inline-flex; align-items: center; border-radius: 9999px; border: 1px solid #e2e8f0; background-color: #f8fafc; color: #475569; font-size: 0.75rem; font-weight: 500; padding: 0.125rem 0.5rem; font-family: ui-sans-serif, system-ui, sans-serif;">
                    {{badgeText}}
                  </span>
                `);
              }
            },
            {
              type: 'menuitem',
              text: 'Badge Succès',
              onAction: () => {
                editor.insertContent(`
                  <span style="display: inline-flex; align-items: center; border-radius: 9999px; background-color: #f0fdf4; color: #15803d; font-size: 0.75rem; font-weight: 500; padding: 0.125rem 0.5rem; font-family: ui-sans-serif, system-ui, sans-serif;">
                    {{badgeText}}
                  </span>
                `);
              }
            }
          ]
        },
        {
          type: 'nestedmenuitem',
          text: 'Tableaux',
          getSubmenuItems: () => [
            {
              type: 'menuitem',
              text: 'Tableau Simple',
              onAction: () => {
                editor.insertContent(`
                  <table style="width: 100%; border-collapse: collapse; font-family: ui-sans-serif, system-ui, sans-serif;">
                    <thead>
                      <tr style="border-bottom: 1px solid #e2e8f0;">
                        <th style="text-align: left; padding: 0.75rem; font-weight: 500; color: #64748b; font-size: 0.875rem;">Nom</th>
                        <th style="text-align: left; padding: 0.75rem; font-weight: 500; color: #64748b; font-size: 0.875rem;">Statut</th>
                        <th style="text-align: right; padding: 0.75rem; font-weight: 500; color: #64748b; font-size: 0.875rem;">Actions</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr style="border-bottom: 1px solid #e2e8f0;">
                        <td style="padding: 0.75rem; font-size: 0.875rem; color: #0f172a;">{{row1col1}}</td>
                        <td style="padding: 0.75rem; font-size: 0.875rem; color: #0f172a;">{{row1col2}}</td>
                        <td style="padding: 0.75rem; font-size: 0.875rem; color: #0f172a; text-align: right;">{{row1col3}}</td>
                      </tr>
                      <tr style="border-bottom: 1px solid #e2e8f0;">
                        <td style="padding: 0.75rem; font-size: 0.875rem; color: #0f172a;">{{row2col1}}</td>
                        <td style="padding: 0.75rem; font-size: 0.875rem; color: #0f172a;">{{row2col2}}</td>
                        <td style="padding: 0.75rem; font-size: 0.875rem; color: #0f172a; text-align: right;">{{row2col3}}</td>
                      </tr>
                    </tbody>
                  </table>
                `);
              }
            }
          ]
        },
        {
          type: 'nestedmenuitem',
          text: 'Structures',
          getSubmenuItems: () => [
            {
              type: 'menuitem',
              text: 'En-tête Email',
              onAction: () => {
                editor.insertContent(`
                  <div style="text-align: center; padding: 1.5rem; background-color: #f8fafc; margin-bottom: 1.5rem;">
                    <img src="{{logoUrl}}" alt="Logo" style="height: 40px; margin-bottom: 1rem;">
                    <h1 style="margin: 0; font-size: 1.5rem; color: #0f172a; font-weight: 600; font-family: ui-sans-serif, system-ui, sans-serif;">{{emailTitle}}</h1>
                  </div>
                `);
              }
            },
            {
              type: 'menuitem',
              text: 'Pied de Page Email',
              onAction: () => {
                editor.insertContent(`
                  <div style="text-align: center; padding: 1.5rem; background-color: #f8fafc; margin-top: 1.5rem; color: #64748b; font-size: 0.875rem; font-family: ui-sans-serif, system-ui, sans-serif;">
                    <p>© {{currentYear}} Votre Entreprise. Tous droits réservés.</p>
                    <p style="margin-bottom: 0;">
                      <a href="{{privacyUrl}}" style="color: #3b82f6; text-decoration: none;">Politique de confidentialité</a> • 
                      <a href="{{termsUrl}}" style="color: #3b82f6; text-decoration: none;">Conditions d'utilisation</a>
                    </p>
                  </div>
                `);
              }
            }
          ]
        }
      ];
      callback(items);
    }
  });
};
