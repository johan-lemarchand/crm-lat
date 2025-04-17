import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Link } from 'react-router-dom';
import { Button } from '@/components/ui/button';
import { Plus } from 'lucide-react';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { useState } from 'react';
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogFooter,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

interface Project {
  id: number;
  name: string;
  description?: string;
}

interface CreateProjectData {
  name: string;
  description?: string;
}

export default function ProjectsPage() {
  const [isOpen, setIsOpen] = useState(false);
  const [formData, setFormData] = useState<CreateProjectData>({
    name: '',
    description: '',
  });

  const queryClient = useQueryClient();

  const { data: projects, isLoading, isError, error } = useQuery<Project[]>({
    queryKey: ['projects'],
    queryFn: async () => {
      const response = await fetch(`${import.meta.env.VITE_API_URL}/api/projects`);
      if (!response.ok) {
        throw new Error(`Erreur ${response.status}: ${response.statusText}`);
      }
      return response.json();
    },
  });

  const { mutate: createProject, isPending } = useMutation({
    mutationFn: async (data: CreateProjectData) => {
      const response = await fetch(`${import.meta.env.VITE_API_URL}/api/projects`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify(data),
      });
      
      if (!response.ok) {
        throw new Error('Erreur lors de la création du projet');
      }
      
      return response.json();
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['projects'] });
      setIsOpen(false);
      setFormData({ name: '', description: '' });
    },
  });

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    createProject(formData);
  };

  if (isLoading) return <div>Chargement...</div>;

  if (isError) {
    return (
      <Alert variant="destructive" className="my-4">
        <AlertDescription>
          Erreur lors du chargement des projets: {error.message}
        </AlertDescription>
      </Alert>
    );
  }

  return (
    <div className="py-6">
      <div className="flex justify-between items-center mb-6">
        <h1 className="text-2xl font-bold">Projets</h1>
        <Button onClick={() => setIsOpen(true)}>
          <Plus className="w-4 h-4 mr-2" />
          Nouveau projet
        </Button>
      </div>

      <Dialog open={isOpen} onOpenChange={setIsOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Créer un nouveau projet</DialogTitle>
          </DialogHeader>
          <form onSubmit={handleSubmit}>
            <div className="space-y-4">
              <div>
                <Label htmlFor="name">Nom</Label>
                <Input
                  id="name"
                  value={formData.name}
                  onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                  required
                />
              </div>
              <div>
                <Label htmlFor="description">Description (optionnelle)</Label>
                <Input
                  id="description"
                  value={formData.description}
                  onChange={(e) => setFormData({ ...formData, description: e.target.value })}
                />
              </div>
            </div>
            <DialogFooter className="mt-4">
              <Button type="button" variant="outline" onClick={() => setIsOpen(false)}>
                Annuler
              </Button>
              <Button type="submit" disabled={isPending}>
                {isPending ? 'Création...' : 'Créer'}
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>

      {projects?.length === 0 ? (
        <div className="text-center text-gray-500 mt-8">
          Aucun projet disponible. Créez-en un nouveau !
        </div>
      ) : (
        <div className="grid gap-4 md:grid-cols-3">
          {projects?.map(project => (
            <div
              key={project.id}
              className="block p-4 rounded-lg border border-gray-200 hover:border-blue-500 transition-colors"
            >
              <h2 className="font-semibold mb-2">{project.name}</h2>
              {project.description && (
                <p className="text-sm text-gray-600">{project.description}</p>
              )}
            </div>
          ))}
        </div>
      )}
    </div>
  );
} 