import { useQuery } from '@tanstack/react-query';
import { Link } from 'react-router-dom';
import { Button } from '@/components/ui/button';
import { Plus } from 'lucide-react';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogFooter,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import type { Board } from '@/types/kanban';
import { CreateBoard } from '@/components/kanban/CreateBoard';

interface Project {
  id: number;
  name: string;
}

interface CreateBoardData {
  name: string;
  description?: string;
  projectId: number;
}

export default function BoardsPage() {
  const [isOpen, setIsOpen] = useState(false);
  const [formData, setFormData] = useState<CreateBoardData>({
    name: '',
    description: '',
    projectId: 0,
  });
  
  const navigate = useNavigate();
  const queryClient = useQueryClient();

  const { data: boards, isLoading, error } = useQuery<Board[]>({
    queryKey: ['boards'],
    queryFn: async () => {
      const response = await fetch(`${import.meta.env.VITE_API_URL}/api/boards`);
      if (!response.ok) {
        throw new Error('Failed to fetch boards');
      }
      return response.json();
    }
  });

  const { data: projects } = useQuery<Project[]>({
    queryKey: ['projects'],
    queryFn: async () => {
      const response = await fetch(`${import.meta.env.VITE_API_URL}/api/projects`);
      if (!response.ok) {
        throw new Error('Erreur lors du chargement des projets');
      }
      return response.json();
    },
  });

  const { mutate: createBoard, isPending } = useMutation({
    mutationFn: async (data: CreateBoardData) => {
      const response = await fetch(`${import.meta.env.VITE_API_URL}/api/boards`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify(data),
      });
      
      if (!response.ok) {
        throw new Error('Erreur lors de la création du tableau');
      }
      
      return response.json();
    },
    onSuccess: (data) => {
      queryClient.invalidateQueries({ queryKey: ['boards'] });
      setIsOpen(false);
      navigate(`/board/${data.id}`);
    },
  });

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    createBoard(formData);
  };

  if (isLoading) return <div>Chargement...</div>;
  if (error) return <div>Erreur lors du chargement des tableaux</div>;
  if (!boards) return null;

  return (
    <div className="py-6">
      <div className="flex justify-between items-center mb-6">
        <h1 className="text-2xl font-bold">Tableaux</h1>
        <div className="flex items-center space-x-2">
          <Button variant="outline" onClick={() => navigate('/projects')}>
            <Plus className="w-4 h-4 mr-2" />
            Nouveau projet
          </Button>
          <CreateBoard projectId={formData.projectId} />
        </div>
      </div>

      <Dialog open={isOpen} onOpenChange={setIsOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Créer un nouveau tableau</DialogTitle>
          </DialogHeader>
          <form onSubmit={handleSubmit}>
            <div className="space-y-4">
              <div>
                <Label htmlFor="project">Projet</Label>
                <Select
                  value={formData.projectId.toString()}
                  onValueChange={(value) => setFormData({ ...formData, projectId: parseInt(value) })}
                >
                  <SelectTrigger>
                    <SelectValue placeholder="Sélectionner un projet" />
                  </SelectTrigger>
                  <SelectContent>
                    {projects?.map((project) => (
                      <SelectItem key={project.id} value={project.id.toString()}>
                        {project.name}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
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
              <Button type="submit" disabled={isPending || !formData.projectId}>
                {isPending ? 'Création...' : 'Créer'}
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>

      {boards?.length === 0 ? (
        <div className="text-center text-gray-500 mt-8">
          Aucun tableau disponible. Créez-en un nouveau !
        </div>
      ) : (
        <div className="grid gap-4 md:grid-cols-3">
          {boards?.map(board => (
            <Link
              key={board.id}
              to={`/board/${board.id}`}
              className="block p-4 border rounded-lg hover:shadow-md transition-shadow"
            >
              <h2 className="font-medium">{board.name}</h2>
              {board.description && (
                <p className="text-sm text-gray-600 mt-1">{board.description}</p>
              )}
              <p className="text-sm text-gray-500 mt-2">
                Projet: {board.project.name}
              </p>
            </Link>
          ))}
        </div>
      )}
    </div>
  );
} 