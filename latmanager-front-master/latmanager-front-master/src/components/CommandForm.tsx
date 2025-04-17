import React, { useState } from 'react';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { CommandFormData, CommandFormProps, CommandResponse } from '@/types/Command';

export default function CommandForm({ open, onOpenChange }: CommandFormProps) {
    /*const recurrenceOptions = [
        { value: "Mensuel", label: "Une fois par mois" },
        { value: "Hebdomadaire", label: "Une fois par semaine" },
        { value: "Quotidien", label: "Quotidien" },
        { value: "Toutes les X heures", label: "Toutes les X heures" },
        { value: "Toutes les X minutes", label: "Toutes les X minutes" }
    ];*/

    const [formData, setFormData] = useState<CommandFormData>({
        name: '',
        scriptName: '',
        recurrence: 'Quotidien',
        interval: null,
        attemptMax: 5,
        startTime: null,
        endTime: null,
        active: true,
        statusSendEmail: true,
    });

    const queryClient = useQueryClient();

    const { mutate: createCommand, isPending } = useMutation({
        mutationFn: async (data: CommandFormData): Promise<CommandResponse> => {
            const response = await fetch(`${import.meta.env.VITE_API_URL}/api/commands`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(data),
            });
            if (!response.ok) {
                const errorData = await response.json();
                throw new Error(errorData.error || 'Erreur lors de la création de la commande');
            }
            return response.json();
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['commands'] });
            onOpenChange(false);
            setFormData({
                name: '',
                scriptName: '',
                recurrence: 'Quotidien',
                interval: null,
                attemptMax: 5,
                startTime: null,
                endTime: null,
                active: true,
                statusSendEmail: true,
            });
        },
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        createCommand(formData);
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-[425px]">
                <DialogHeader>
                    <DialogTitle>Ajouter une commande</DialogTitle>
                </DialogHeader>
                <form onSubmit={handleSubmit} className="space-y-4">
                    <div className="grid w-full gap-2">
                        <Label htmlFor="name">Application</Label>
                        <Input
                            id="name"
                            value={formData.name}
                            onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                            placeholder="Nom de l'application"
                            required
                        />
                    </div>
                    <div className="grid w-full gap-2">
                        <Label htmlFor="scriptName">Script</Label>
                        <Input
                            id="scriptName"
                            value={formData.scriptName}
                            onChange={(e) => setFormData({ ...formData, scriptName: e.target.value })}
                            placeholder="Nom du script"
                            required
                        />
                    </div>
                    <div className="flex items-center gap-2">
                        <input
                            type="checkbox"
                            id="active"
                            checked={formData.active}
                            onChange={(e) => setFormData({ ...formData, active: e.target.checked })}
                            className="h-4 w-4 rounded border-gray-300"
                        />
                        <Label htmlFor="active">Actif</Label>
                    </div>
                    <div className="flex items-center gap-2">
                        <input
                            type="checkbox"
                            id="statusSendEmail"
                            checked={formData.statusSendEmail}
                            onChange={(e) => setFormData({ ...formData, statusSendEmail: e.target.checked })}
                            className="h-4 w-4 rounded border-gray-300"
                        />
                        <Label htmlFor="statusSendEmail">Activer les notifications par email</Label>
                    </div>
                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
                            Annuler
                        </Button>
                        <Button type="submit" disabled={isPending}>
                            {isPending ? 'Création...' : 'Créer'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
