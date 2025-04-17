import { useState } from "react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { useQueryClient } from "@tanstack/react-query";
import { PlusIcon } from "lucide-react";

interface CreateCardProps {
    columnId: number;
    boardId: number;
}

export function CreateCard({ columnId, boardId }: CreateCardProps) {
    const [isAdding, setIsAdding] = useState(false);
    const [title, setTitle] = useState("");
    const queryClient = useQueryClient();

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        if (!title.trim()) return;

        try {
            const response = await fetch(`${import.meta.env.VITE_API_URL}/api/columns/${columnId}/cards`, {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                },
                body: JSON.stringify({
                    title: title.trim(),
                }),
            });

            if (!response.ok) {
                throw new Error("Failed to create card");
            }

            // Réinitialiser le formulaire
            setTitle("");
            setIsAdding(false);
            
            // Rafraîchir les données du board
            await queryClient.invalidateQueries({ queryKey: ["board", boardId.toString()] });
        } catch (error) {
            console.error("Error creating card:", error);
        }
    };

    if (!isAdding) {
        return (
            <Button 
                variant="ghost" 
                className="w-full flex items-center justify-start gap-2 text-gray-500 hover:text-gray-800 hover:bg-gray-200 rounded-md transition-colors h-auto py-2 px-3"
                onClick={() => setIsAdding(true)}
            >
                <PlusIcon className="h-4 w-4" />
                Ajouter une carte
            </Button>
        );
    }

    return (
        <div className="bg-white rounded-md shadow-sm border border-gray-200 p-2">
            <form onSubmit={handleSubmit} className="space-y-2">
                <Input
                    type="text"
                    placeholder="Titre de la carte"
                    value={title}
                    onChange={(e) => setTitle(e.target.value)}
                    autoFocus
                    className="border-gray-300 focus:border-blue-400 focus:ring-blue-400"
                />
                <div className="flex gap-2">
                    <Button 
                        type="submit" 
                        size="sm"
                        className="bg-blue-600 hover:bg-blue-700"
                    >
                        Ajouter
                    </Button>
                    <Button 
                        type="button" 
                        variant="outline" 
                        size="sm"
                        className="border-gray-300 text-gray-700"
                        onClick={() => {
                            setIsAdding(false);
                            setTitle("");
                        }}
                    >
                        Annuler
                    </Button>
                </div>
            </form>
        </div>
    );
} 