import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Plus } from 'lucide-react';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useNavigate } from 'react-router-dom';

interface Project {
    id: number;
    name: string;
}

interface FormData {
    name: string;
    description: string;
    projectId: number;
}

const defaultFormData: FormData = {
    name: '',
    description: '',
    projectId: 0
};

export function CreateBoard() {
    const [isOpen, setIsOpen] = useState(false);
    const [formData, setFormData] = useState<FormData>(defaultFormData);
    const queryClient = useQueryClient();
    const navigate = useNavigate();

    const { data: projects } = useQuery<Project[]>({
        queryKey: ['projects'],
        queryFn: async () => {
            const response = await fetch(`${import.meta.env.VITE_API_URL}/api/projects`);
            if (!response.ok) {
                throw new Error('Failed to fetch projects');
            }
            return response.json();
        }
    });

    const { mutate: createBoard } = useMutation({
        mutationFn: async (data: FormData) => {
            const response = await fetch(`${import.meta.env.VITE_API_URL}/api/projects/${data.projectId}/boards`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    name: data.name,
                    description: data.description
                })
            });
            if (!response.ok) {
                throw new Error('Failed to create board');
            }
            return response.json();
        },
        onSuccess: (data) => {
            queryClient.invalidateQueries({ queryKey: ['boards'] });
            setIsOpen(false);
            setFormData(defaultFormData);
            navigate(`/board/${data.id}`);
        }
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!formData.name.trim() || !formData.projectId) return;
        createBoard(formData);
    };

    const handleClose = () => {
        setIsOpen(false);
        setFormData(defaultFormData);
    };

    return (
        <Dialog open={isOpen} onOpenChange={setIsOpen}>
            <DialogTrigger asChild>
                <Button>
                    <Plus className="w-4 h-4 mr-2" />
                    Créer un tableau
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Créer un nouveau tableau</DialogTitle>
                </DialogHeader>
                <form onSubmit={handleSubmit} className="space-y-4">
                    <div>
                        <label htmlFor="project" className="block text-sm font-medium mb-1">
                            Projet
                        </label>
                        <Select
                            value={formData.projectId.toString()}
                            onValueChange={(value) => 
                                setFormData(prev => ({ ...prev, projectId: parseInt(value) }))
                            }
                        >
                            <SelectTrigger>
                                <SelectValue placeholder="Sélectionner un projet" />
                            </SelectTrigger>
                            <SelectContent>
                                {projects?.map(project => (
                                    <SelectItem key={project.id} value={project.id.toString()}>
                                        {project.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    <div>
                        <label htmlFor="name" className="block text-sm font-medium mb-1">
                            Nom
                        </label>
                        <Input
                            id="name"
                            value={formData.name}
                            onChange={(e) => 
                                setFormData(prev => ({ ...prev, name: e.target.value }))
                            }
                            required
                        />
                    </div>
                    <div>
                        <label htmlFor="description" className="block text-sm font-medium mb-1">
                            Description (optionnelle)
                        </label>
                        <Textarea
                            id="description"
                            value={formData.description}
                            onChange={(e) => 
                                setFormData(prev => ({ ...prev, description: e.target.value }))
                            }
                        />
                    </div>
                    <div className="flex justify-end gap-2">
                        <Button 
                            type="button" 
                            variant="outline" 
                            onClick={handleClose}
                        >
                            Annuler
                        </Button>
                        <Button type="submit" disabled={!formData.projectId}>
                            Créer
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
} 