import { useSortable } from "@dnd-kit/sortable";
import { CSS } from "@dnd-kit/utilities";
import { CreateCard } from "./CreateCard";
import type { Column } from "@/types/kanban";
import { SortableContext, verticalListSortingStrategy } from "@dnd-kit/sortable";
import { useState } from 'react';
import { CardModal } from './CardModal';
import { Button } from "@/components/ui/button";
import { Pencil, Trash2 } from "lucide-react";
import { format } from 'date-fns';
import { CalendarIcon, MessageSquare, Paperclip } from "lucide-react";
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogFooter,
} from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { useQueryClient } from "@tanstack/react-query";

interface SortableColumnProps {
    column: Column;
    boardId: number;
    isOver?: boolean;
}

// Composant pour une carte déplaçable
function SortableCard({ card, columnId, boardId }: { card: any; columnId: number; boardId: number }) {
    const [isModalOpen, setIsModalOpen] = useState(false);
    
    const {
        attributes,
        listeners,
        setNodeRef,
        transform,
        transition,
        isDragging,
        isOver,
        over
    } = useSortable({
        id: card.id.toString(),
        data: {
            type: "card",
            columnId: columnId,
            card: card
        },
        transition: {
            duration: 150,
            easing: 'cubic-bezier(0.25, 1, 0.5, 1)',
        }
    });

    // Déterminer si la carte est survolée par une autre carte
    const isBeingOverlaid = isOver && over?.data.current?.type === 'card';

    const style = {
        transform: CSS.Transform.toString(transform),
        transition,
        opacity: isDragging ? 0.3 : undefined,
        cursor: 'grab',
        touchAction: 'none',
        zIndex: isDragging ? 1000 : (isBeingOverlaid ? 10 : 'auto'),
    };

    // Fonction pour formater la date d'échéance
    const formatDueDate = (dateString: string) => {
        const date = new Date(dateString);
        const today = new Date();
        const isOverdue = date < today;
        
        return {
            formattedDate: format(date, 'dd MMM'),
            isOverdue
        };
    };

    // Vérifier si la carte a des labels
    const hasLabels = card.labels && card.labels.length > 0;
    // Vérifier si la carte a une date d'échéance
    const hasDueDate = card.dueDate;
    // Vérifier si la carte a une liste de tâches
    const hasChecklist = card.checklists && card.checklists.length > 0;
    // Calculer le progrès des tâches si une liste existe
    const checklistProgress = hasChecklist 
        ? Math.round((card.checklists.filter((item: any) => item.completed).length / card.checklists.length) * 100)
        : 0;

    return (
        <>
            <div
                ref={setNodeRef}
                style={style}
                className={`group bg-white p-3 rounded-md shadow-sm hover:shadow-md transition-all 
                    ${isDragging ? 'ring-2 ring-blue-400 scale-[0.98] bg-blue-50/30' : ''} 
                    ${isBeingOverlaid ? 'ring-2 ring-green-400 translate-y-1' : ''}
                    border border-gray-200 hover:border-blue-300`}
                {...attributes}
                {...listeners}
                data-type="card"
                data-card-id={card.id}
                data-parent-column-id={columnId}
            >
                {isBeingOverlaid && (
                    <div className="absolute inset-x-0 -top-3 h-3 bg-green-400 rounded-t-md transition-all duration-150" />
                )}
                
                {/* Labels au début de la carte si présents */}
                {hasLabels && (
                    <div className="flex flex-wrap gap-1 mb-2">
                        {card.labels.map((label: any) => (
                            <div 
                                key={label.id} 
                                className="h-2 w-12 rounded-full" 
                                style={{ backgroundColor: label.color }}
                                title={label.name}
                            />
                        ))}
                    </div>
                )}
                
                <div className="flex items-start justify-between">
                    <div className="flex-1">
                        <div className="font-medium text-gray-800">{card.title}</div>
                        {card.description && (
                            <div className="text-sm text-gray-600 mt-1 line-clamp-2">
                                {card.description}
                            </div>
                        )}
                    </div>
                    <div 
                        className="ml-2 opacity-0 group-hover:opacity-100 transition-opacity"
                        onClick={(e) => {
                            e.preventDefault();
                            e.stopPropagation();
                            setIsModalOpen(true);
                        }}
                    >
                        <Button 
                            variant="ghost" 
                            size="sm"
                            className="h-7 w-7 p-0 rounded-full hover:bg-gray-100"
                        >
                            <Pencil className="h-3.5 w-3.5" />
                        </Button>
                    </div>
                </div>
                
                {/* Barre de progression si liste de tâches présente */}
                {hasChecklist && (
                    <div className="mt-2">
                        <div className="w-full bg-gray-200 rounded-full h-1.5">
                            <div 
                                className="bg-blue-500 h-1.5 rounded-full" 
                                style={{ width: `${checklistProgress}%` }}
                            />
                        </div>
                        <div className="text-xs text-gray-500 mt-0.5 text-right">
                            {checklistProgress}%
                        </div>
                    </div>
                )}
                
                {/* Footer avec métadonnées */}
                <div className="mt-2 flex items-center justify-between text-xs text-gray-500">
                    {/* Date d'échéance */}
                    {hasDueDate && card.dueDate && (
                        <div className={`flex items-center gap-1 ${formatDueDate(card.dueDate)?.isOverdue ? 'text-red-500' : 'text-gray-500'}`}>
                            <CalendarIcon className="h-3 w-3" />
                            <span>{formatDueDate(card.dueDate)?.formattedDate}</span>
                        </div>
                    )}
                    
                    {/* Commentaires ou pièces jointes si ajoutés dans le futur */}
                    <div className="flex items-center gap-2">
                        {card.comments && card.comments.length > 0 && (
                            <div className="flex items-center gap-1">
                                <MessageSquare className="h-3 w-3" />
                                <span>{card.comments.length}</span>
                            </div>
                        )}
                        {card.attachments && card.attachments.length > 0 && (
                            <div className="flex items-center gap-1">
                                <Paperclip className="h-3 w-3" />
                                <span>{card.attachments.length}</span>
                            </div>
                        )}
                    </div>
                </div>
            </div>

            <CardModal
                card={card}
                isOpen={isModalOpen}
                onClose={() => setIsModalOpen(false)}
                columnId={columnId}
                boardId={boardId}
            />
        </>
    );
}

export function SortableColumn({ column, boardId, isOver = false }: SortableColumnProps) {
    const cards = Array.isArray(column.cards) 
        ? [...column.cards].sort((a, b) => a.position - b.position)
        : [];
    const cardIds = cards.map(card => card.id.toString());
    const [isEditModalOpen, setIsEditModalOpen] = useState(false);
    const [columnName, setColumnName] = useState(column.name);
    const queryClient = useQueryClient();

    // Calculer quelques statistiques pour la barre de progression
    const totalCards = cards.length;
    const cardsWithChecklists = cards.filter(card => card.checklists && card.checklists.length > 0).length;
    
    // Calculer le nombre de cartes avec date d'échéance dépassée
    const overdueTasks = cards.filter(card => {
        if (!card.dueDate) return false;
        const dueDate = new Date(card.dueDate);
        const today = new Date();
        return dueDate < today;
    }).length;

    // Gérer le changement de nom de colonne
    const handleColumnNameChange = async (e: React.FormEvent) => {
        e.preventDefault();
        
        try {
            // Créer d'abord une copie locale mise à jour pour un feedback instantané
            const currentBoard = queryClient.getQueryData<any>(['board', boardId.toString()]);
            
            if (currentBoard) {
                const optimisticUpdatedBoard = {
                    ...currentBoard,
                    columns: currentBoard.columns.map((col: any) => 
                        col.id === column.id ? { ...col, name: columnName } : col
                    )
                };
                
                // Mettre à jour immédiatement le cache avec notre version optimiste
                queryClient.setQueryData(['board', boardId.toString()], optimisticUpdatedBoard);
            }
            
            // Fermer la modal immédiatement
            setIsEditModalOpen(false);
            
            // Ensuite effectuer la requête au serveur
            const response = await fetch(`${import.meta.env.VITE_API_URL}/api/boards/${boardId}/columns/${column.id}`, {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ name: columnName })
            });
            
            if (response.ok) {
                // Si la requête réussit, mettre à jour avec les données du serveur
                const updatedBoardData = await response.json();
                
                // On vérifie que les données contiennent bien les colonnes avant de mettre à jour
                if (updatedBoardData && updatedBoardData.columns) {
                    queryClient.setQueryData(['board', boardId.toString()], updatedBoardData);
                }
            } else {
                // En cas d'erreur, restaurer les données originales
                console.error('Erreur lors de la mise à jour du nom de la colonne');
                
                if (currentBoard) {
                    queryClient.setQueryData(['board', boardId.toString()], currentBoard);
                } else {
                    queryClient.invalidateQueries({ queryKey: ['board', boardId.toString()] });
                }
                
                // Notification d'erreur à l'utilisateur
                alert("Erreur lors de la mise à jour du nom de la colonne. Veuillez réessayer.");
            }
        } catch (error) {
            console.error('Error updating column name:', error);
            
            // En cas d'erreur, forcer un rechargement des données
            queryClient.invalidateQueries({ queryKey: ['board', boardId.toString()] });
            
            // Notification d'erreur à l'utilisateur
            alert("Une erreur est survenue. Les modifications pourront apparaître après actualisation.");
        }
    };
    
    // Fonction pour supprimer une colonne
    const handleDeleteColumn = async () => {
        if (!confirm("Êtes-vous sûr de vouloir supprimer cette colonne et toutes ses cartes ?")) {
            return;
        }
        
        try {
            // Appliquer d'abord une suppression optimiste
            const currentBoard = queryClient.getQueryData<any>(['board', boardId.toString()]);
            
            if (currentBoard) {
                // Créer une version du tableau sans la colonne à supprimer
                const optimisticUpdatedBoard = {
                    ...currentBoard,
                    columns: currentBoard.columns.filter((col: any) => col.id !== column.id)
                };
                
                // Mettre à jour immédiatement le cache
                queryClient.setQueryData(['board', boardId.toString()], optimisticUpdatedBoard);
            }
            
            // Ensuite effectuer la requête au serveur
            const response = await fetch(`${import.meta.env.VITE_API_URL}/api/boards/${boardId}/columns/${column.id}`, {
                method: 'DELETE',
                headers: { 'Content-Type': 'application/json' }
            });
            
            if (!response.ok) {
                throw new Error(`Erreur HTTP: ${response.status}`);
            }
            
            // Vérifier si la réponse contient du contenu JSON
            const contentType = response.headers.get("content-type");
            if (contentType && contentType.includes("application/json") && response.bodyUsed === false) {
                try {
                    const text = await response.text();
                    if (text && text.trim() !== "") {
                        const updatedBoardData = JSON.parse(text);
                        // Mettre à jour les données en cache
                        if (updatedBoardData && updatedBoardData.columns) {
                            queryClient.setQueryData(['board', boardId.toString()], updatedBoardData);
                        }
                    }
                } catch (parseError) {
                    console.warn("La réponse n'est pas un JSON valide, mais la colonne a bien été supprimée");
                }
            }
        } catch (error) {
            console.error('Error deleting column:', error);
            
            // Restaurer les données originales en cas d'erreur
            const currentBoard = queryClient.getQueryData<any>(['board', boardId.toString()]);
            if (currentBoard) {
                queryClient.invalidateQueries({ queryKey: ['board', boardId.toString()] });
            }
            
            alert("Erreur lors de la suppression de la colonne. Veuillez rafraîchir la page.");
        }
    };

    return (
        <div 
            className={`flex-shrink-0 w-72 bg-gray-50 rounded-lg border border-gray-200 shadow-sm overflow-hidden flex flex-col transition-all duration-200
                ${isOver ? 'ring-inset ring-2 ring-blue-300' : ''}
            `}
            data-type="column"
            data-column-id={column.id}
        >
            {/* En-tête de colonne */}
            <div className={`p-3 border-b border-gray-200 ${isOver ? 'bg-blue-50' : 'bg-gradient-to-r from-gray-50 to-gray-100'}`}>
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-2">
                        <span className="font-medium text-gray-700">{column.name}</span>
                        <span className="px-2 py-0.5 bg-gray-200 text-gray-600 rounded-full text-xs font-medium">
                            {cards.length}
                        </span>
                    </div>
                    <div className="flex items-center gap-1">
                        <Button 
                            variant="ghost" 
                            size="sm"
                            className="h-7 w-7 p-0 rounded-full hover:bg-gray-200"
                            onClick={() => setIsEditModalOpen(true)}
                        >
                            <Pencil className="h-3.5 w-3.5" />
                        </Button>
                        <Button 
                            variant="ghost" 
                            size="sm"
                            className="h-7 w-7 p-0 rounded-full hover:bg-red-100 text-red-500"
                            onClick={handleDeleteColumn}
                        >
                            <Trash2 className="h-3.5 w-3.5" />
                        </Button>
                    </div>
                </div>
                
                {/* Statistiques et visualisation */}
                {totalCards > 0 && (
                    <div className="mt-2">
                        <div className="w-full bg-gray-200 rounded-full h-1">
                            <div className="flex h-1">
                                {/* Cartes en retard (rouge) */}
                                {overdueTasks > 0 && (
                                    <div 
                                        className="bg-red-500 h-1 rounded-l-full" 
                                        style={{ width: `${(overdueTasks / totalCards) * 100}%` }}
                                        title={`${overdueTasks} tâche(s) en retard`}
                                    />
                                )}
                                {/* Cartes avec checklists (bleu) */}
                                {cardsWithChecklists > 0 && (
                                    <div 
                                        className={`bg-blue-500 h-1 ${overdueTasks === 0 ? 'rounded-l-full' : ''}`}
                                        style={{ width: `${(cardsWithChecklists / totalCards) * 100}%` }}
                                        title={`${cardsWithChecklists} tâche(s) avec liste`}
                                    />
                                )}
                            </div>
                        </div>
                    </div>
                )}
            </div>

            {/* Zone des cartes avec indication visuelle sur l'état du drop */}
            <div className={`p-2 flex-1 overflow-y-auto max-h-[calc(100vh-220px)] 
                ${isOver ? 'bg-blue-50/50' : ''} 
                transition-colors duration-200`}
            >
                <div className={`space-y-2 min-h-[50px] relative 
                    ${isOver ? 'before:absolute before:inset-0 before:border-2 before:border-dashed before:border-blue-300 before:rounded-md before:pointer-events-none before:z-10' : ''}
                    ${cards.length === 0 && isOver ? 'after:absolute after:inset-0 after:bg-blue-100/30 after:rounded-md after:pointer-events-none' : ''}
                `}>
                    <SortableContext 
                        items={cardIds}
                        strategy={verticalListSortingStrategy}
                    >
                        {cards.map((card) => (
                            <SortableCard 
                                key={card.id} 
                                card={card} 
                                columnId={column.id}
                                boardId={boardId}
                            />
                        ))}
                    </SortableContext>
                </div>

                {/* Zone d'ajout de carte avec état différent lors du survol */}
                <div className={`mt-3 ${isOver ? 'opacity-50' : 'opacity-100'} transition-opacity duration-200`}>
                    <CreateCard columnId={column.id} boardId={boardId} />
                </div>
            </div>

            {/* Modal d'édition du nom de colonne */}
            <Dialog open={isEditModalOpen} onOpenChange={setIsEditModalOpen}>
                <DialogContent className="sm:max-w-[425px]">
                    <DialogHeader>
                        <DialogTitle>Modifier la colonne</DialogTitle>
                    </DialogHeader>
                    <form onSubmit={handleColumnNameChange}>
                        <div className="grid gap-4 py-4">
                            <div className="grid gap-2">
                                <Label htmlFor="column-name">Nom de la colonne</Label>
                                <Input
                                    id="column-name"
                                    value={columnName}
                                    onChange={(e) => setColumnName(e.target.value)}
                                    required
                                    autoFocus
                                />
                            </div>
                        </div>
                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => {
                                setColumnName(column.name);
                                setIsEditModalOpen(false);
                            }}>
                                Annuler
                            </Button>
                            <Button type="submit">Enregistrer</Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </div>
    );
} 