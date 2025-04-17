import { useParams } from 'react-router-dom';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import type { Board as BoardType } from '@/types/kanban';
import { CreateColumn } from './CreateColumn';
import {
    DndContext,
    DragOverlay,
    closestCorners,
    pointerWithin,
    rectIntersection,
    KeyboardSensor,
    PointerSensor,
    useSensor,
    useSensors,
    DragEndEvent,
    DragStartEvent,
    useDroppable,
    DragOverEvent,
    MeasuringStrategy,
    CollisionDetection,
    closestCenter,
} from '@dnd-kit/core';
import { useState } from 'react';
import { SortableColumn } from './SortableColumn';
import { Button } from '@/components/ui/button';
import { CalendarIcon, FilterIcon, RefreshCw } from 'lucide-react';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuCheckboxItem,
    DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu"

interface DroppableColumnProps {
    column: any;
    boardId: number;
}

// Composant pour rendre une colonne droppable
function DroppableColumn({ column, boardId }: DroppableColumnProps) {
    const { setNodeRef, isOver, active } = useDroppable({
        id: column.id.toString(),
        data: {
            type: 'column',
            column,
        },
    });

    // Détermine si la carte active est en train d'être déplacée sur cette colonne
    const isActiveCard = active?.data.current?.type === 'card';
    const shouldHighlight = isOver && isActiveCard;

    return (
        <div 
            ref={setNodeRef}
            className={`transition-all duration-200 ${
                shouldHighlight 
                ? 'bg-blue-50 ring-2 ring-blue-400 rounded-lg shadow-md transform scale-[1.01]' 
                : ''
            }`}
            data-column-id={column.id}
            style={{
                minHeight: '200px', // Ajouter une hauteur minimale pour améliorer la zone de dépôt
                position: 'relative' // S'assurer que la position est relative pour la détection
            }}
        >
            <SortableColumn column={column} boardId={boardId} isOver={shouldHighlight} />
        </div>
    );
}

// Fonction personnalisée de détection de collision qui combine plusieurs stratégies
const customCollisionDetection: CollisionDetection = (args) => {
    const { active, droppableContainers } = args;
    
    // Si la carte active a le même ID qu'une colonne, on doit faire attention
    if (active && active.data.current?.type === 'card') {
        const cardId = active.id;
        
        // Vérifier si une colonne a le même ID que la carte
        const conflictingColumns = droppableContainers.filter(
            container => container.id === cardId && container.data.current?.type === 'column'
        );
        
        if (conflictingColumns.length > 0) {
            // Augmenter la priorité des collisions avec les colonnes
            const prioritizedContainers = [...droppableContainers].sort((a, b) => {
                if (a.id === cardId && a.data.current?.type === 'column') return -1;
                if (b.id === cardId && b.data.current?.type === 'column') return 1;
                return 0;
            });
            
            // Utiliser une combinaison de stratégies avec les containers prioritaires
            const rectIntersections = rectIntersection({
                ...args,
                droppableContainers: prioritizedContainers
            });
            
            if (rectIntersections.length > 0) {
                return rectIntersections;
            }
        }
    }
    
    // D'abord essayer avec rectIntersection qui est plus précis pour les intersections directes
    const intersections = rectIntersection(args);
    
    // Si des intersections sont trouvées, les utiliser
    if (intersections.length > 0) {
        return intersections;
    }
    
    // Essayer avec pointerWithin qui est plus précis pour les petits éléments
    const pointerIntersections = pointerWithin(args);
    if (pointerIntersections.length > 0) {
        return pointerIntersections;
    }
    
    // En dernier recours, utiliser closestCorners qui est plus permissif
    return closestCorners(args);
};

export function Board() {
    const { boardId } = useParams();
    const queryClient = useQueryClient();
    const [activeCard, setActiveCard] = useState<any>(null);
    const [hoveredColumn, setHoveredColumn] = useState<string | null>(null);
    const [activeDrag, setActiveDrag] = useState<any>(null);
    // État pour suivre les erreurs de déplacement
    const [moveError, setMoveError] = useState<string | null>(null);
    // État pour le filtrage
    const [filter, setFilter] = useState<{
        showWithDueDate: boolean;
        showWithChecklists: boolean;
        showOverdue: boolean;
    }>({
        showWithDueDate: false,
        showWithChecklists: false,
        showOverdue: false,
    });
    // État pour indiquer que l'actualisation est en cours
    const [isRefreshing, setIsRefreshing] = useState(false);

    const sensors = useSensors(
        useSensor(PointerSensor, {
            activationConstraint: {
                distance: 8,
            },
        }),
        useSensor(KeyboardSensor)
    );

    const { data: board, isLoading, refetch } = useQuery<BoardType>({
        queryKey: ['board', boardId],
        queryFn: async () => {
            const response = await fetch(`${import.meta.env.VITE_API_URL}/api/boards/${boardId}`);
            if (!response.ok) throw new Error('Network response was not ok');
            return response.json();
        }
    });

    // Fonction pour actualiser les données du tableau
    const handleRefresh = async () => {
        setIsRefreshing(true);
        try {
            await refetch();
        } finally {
            setIsRefreshing(false);
        }
    };

    // Fonction pour filtrer les cartes selon les critères sélectionnés
    const filterCards = (cards: any[]) => {
        if (!cards) return [];
        
        // Si aucun filtre n'est actif, retourner toutes les cartes
        if (!filter.showWithDueDate && !filter.showWithChecklists && !filter.showOverdue) {
            return cards;
        }
        
        return cards.filter(card => {
            // Filtre pour les cartes avec date d'échéance
            if (filter.showWithDueDate && !card.dueDate) {
                return false;
            }
            
            // Filtre pour les cartes avec listes
            if (filter.showWithChecklists && (!card.checklists || card.checklists.length === 0)) {
                return false;
            }
            
            // Filtre pour les cartes en retard
            if (filter.showOverdue) {
                if (!card.dueDate) return false;
                
                const dueDate = new Date(card.dueDate);
                const today = new Date();
                return dueDate < today;
            }
            
            return true;
        });
    };

    if (isLoading || !board) return <div>Chargement...</div>;

    const handleDragStart = (event: DragStartEvent) => {
        const { active } = event;
        if (active.data.current?.type === 'card') {
            setActiveCard(active.data.current.card);
            setActiveDrag(active);
            // Réinitialiser la colonne survolée
            setHoveredColumn(null);
        }
    };

    const handleDragOver = (event: DragOverEvent) => {
        const { active, over } = event;
        
        if (!over || !active.data.current) return;

        const activeType = active.data.current.type;
        const overData = over.data.current;
        const overType = overData?.type;

        // Mémoriser la colonne survolée pour des animations plus fluides
        if (activeType === 'card' && overType === 'column') {
            setHoveredColumn(over.id.toString());
        } else if (activeType === 'card' && overType === 'card' && overData) {
            // Si on survole une carte, on récupère la colonne parente
            const overColumnId = overData.columnId;
            if (overColumnId) {
                setHoveredColumn(overColumnId.toString());
            }
        }
    };

    // Fonction utilitaire pour déplacer une carte entre colonnes
    const moveCardBetweenColumns = async (
        cardId: string | number, 
        sourceColumnId: number, 
        targetColumnId: string | number, 
        position: number = 0, 
        oldPosition: number = 0
    ) => {
        try {
            setMoveError(null);
            
            // Conversion explicite pour s'assurer que l'ID est bien traité comme une chaîne puis un nombre
            const targetColId = parseInt(targetColumnId.toString());
            
            const response = await fetch(`${import.meta.env.VITE_API_URL}/api/columns/${targetColId}/cards/${cardId}`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    columnId: targetColId,
                    position,
                    oldPosition,
                    fromColumnId: sourceColumnId
                })
            });

            if (!response.ok) {
                const errorMessage = `Erreur HTTP ${response.status}: ${response.statusText}`;
                setMoveError(errorMessage);
                throw new Error(errorMessage);
            }

            // Mettre à jour le tableau avec les nouvelles données
            const updatedBoard = await response.json();
            queryClient.setQueryData(['board', boardId], updatedBoard);
            
            return true;
        } catch (error) {
            console.error('Erreur de déplacement:', error);
            setMoveError(error instanceof Error ? error.message : 'Erreur inconnue');
            return false;
        }
    };

    const handleDragEnd = async (event: DragEndEvent) => {
        const { active, over } = event;
        
        // Réinitialiser la colonne survolée et l'état de drag
        setHoveredColumn(null);
        
        if (!over) {
            setActiveCard(null);
            setActiveDrag(null);
            return;
        }

        if (!active.data.current) {
            setActiveCard(null);
            setActiveDrag(null);
            return;
        }

        const activeType = active.data.current.type;
        const overData = over.data.current;
        const overType = overData?.type;
        
        // CORRECTION IMPORTANTE: Vérifier si le drop cible une carte (ID) qui a le même ID qu'une colonne
        // Ce cas se produit lorsqu'une carte d'ID 1 est présente et qu'on veut la déplacer vers la colonne d'ID 1
        let isForcingColumnDrop = false;
        let forcedColumnId = null;
        
        // Si on dépose sur une carte qui a le même ID qu'une colonne
        if (overType === 'card' && board.columns && board.columns.some(col => col.id.toString() === over.id.toString())) {
            // Identifier la colonne par sa position DOM plutôt que par son ID
            const columnElement = document.querySelector(`[data-column-id="${over.id}"]`);
            if (columnElement) {
                isForcingColumnDrop = true;
                forcedColumnId = over.id.toString();
            }
        }
        
        // Cas où on doit forcer un drop sur une colonne
        if (isForcingColumnDrop && forcedColumnId) {
            const cardId = active.id;
            const fromColumnId = active.data.current.columnId;
            const cardPosition = active.data.current.card.position;
            
            // Position 0 pour placer la carte en haut de la colonne
            await moveCardBetweenColumns(
                cardId,
                fromColumnId,
                forcedColumnId,
                0,
                cardPosition
            );
        }
        // Cas particulier: lorsqu'on dépose une carte sur une autre carte
        else if (activeType === 'card' && overType === 'card') {
            const cardId = active.id;
            const fromColumnId = active.data.current.columnId;
            const cardPosition = active.data.current.card.position;
            
            // Obtenir la colonne cible à partir de la carte cible
            const targetColumnId = overData?.columnId;
            
            if (!targetColumnId) {
                setActiveCard(null);
                setActiveDrag(null);
                return;
            }
            
            // Obtenir la carte cible
            const overCard = overData.card;
            
            if (fromColumnId === targetColumnId) {
                // Réarrangement dans la même colonne
                const isMovingUp = cardPosition > overCard.position;
                const newPosition = isMovingUp ? overCard.position : overCard.position + 1;
                
                await moveCardBetweenColumns(
                    cardId,
                    fromColumnId,
                    targetColumnId,
                    newPosition,
                    cardPosition
                );
            } else {
                // Déplacement vers une autre colonne et positionnement relatif à la carte cible
                const newPosition = overCard.position + 1;
                
                await moveCardBetweenColumns(
                    cardId,
                    fromColumnId,
                    targetColumnId,
                    newPosition,
                    cardPosition
                );
            }
        }
        // Cas où on dépose directement sur une colonne
        else if (activeType === 'card' && overType === 'column') {
            const cardId = active.id;
            const fromColumnId = active.data.current.columnId;
            const cardPosition = active.data.current.card.position;
            const targetColumnId = over.id;
            
            // Position 0 pour placer la carte en haut de la colonne
            await moveCardBetweenColumns(
                cardId,
                fromColumnId,
                targetColumnId,
                0,
                cardPosition
            );
        }

        setActiveCard(null);
        setActiveDrag(null);
    };

    const columns = Array.isArray(board.columns) 
        ? [...board.columns].sort((a, b) => a.position - b.position).map(column => ({
            ...column,
            cards: filterCards(column.cards)
        }))
        : [];

    // Calculer le nombre total de cartes après filtrage
    const totalFilteredCards = columns.reduce((acc, col) => acc + (col.cards ? col.cards.length : 0), 0);

    return (
        <div className="p-4 bg-gradient-to-b from-gray-50 to-gray-100 min-h-screen">
            {/* Afficher les erreurs éventuelles */}
            {moveError && (
                <div className="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4 rounded-lg shadow">
                    <div className="flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" className="h-6 w-6 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        <span>Erreur: {moveError}</span>
                    </div>
                    <button 
                        className="bg-red-200 hover:bg-red-300 text-red-800 text-xs px-2 py-1 rounded mt-2"
                        onClick={() => setMoveError(null)}
                    >
                        Fermer
                    </button>
                </div>
            )}
            
            <div className="flex items-center justify-between mb-6">
                <div>
                    <h1 className="text-2xl font-bold text-gray-800 flex items-center">
                        {board.name}
                        <span className="ml-3 px-3 py-1 bg-blue-100 text-blue-800 text-xs rounded-full">
                            {totalFilteredCards} tâches {Object.values(filter).some(v => v) && "(filtrées)"}
                        </span>
                    </h1>
                    {board.description && (
                        <p className="text-gray-600 mt-1">{board.description}</p>
                    )}
                </div>
                
                <div className="flex gap-2">
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button size="sm" variant="outline" className="flex items-center gap-1">
                                <FilterIcon className="h-4 w-4" />
                                Filtrer
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent className="w-56">
                            <DropdownMenuCheckboxItem
                                checked={filter.showWithDueDate}
                                onCheckedChange={(checked) => 
                                    setFilter(prev => ({ ...prev, showWithDueDate: checked }))
                                }
                            >
                                Avec date d'échéance
                            </DropdownMenuCheckboxItem>
                            <DropdownMenuCheckboxItem
                                checked={filter.showWithChecklists}
                                onCheckedChange={(checked) => 
                                    setFilter(prev => ({ ...prev, showWithChecklists: checked }))
                                }
                            >
                                Avec listes de tâches
                            </DropdownMenuCheckboxItem>
                            <DropdownMenuCheckboxItem
                                checked={filter.showOverdue}
                                onCheckedChange={(checked) => 
                                    setFilter(prev => ({ ...prev, showOverdue: checked }))
                                }
                            >
                                En retard
                            </DropdownMenuCheckboxItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                    
                    <Button 
                        size="sm" 
                        variant="outline" 
                        className="flex items-center gap-1"
                        onClick={handleRefresh}
                        disabled={isRefreshing}
                    >
                        <RefreshCw className={`h-4 w-4 ${isRefreshing ? 'animate-spin' : ''}`} />
                        {isRefreshing ? 'Actualisation...' : 'Actualiser'}
                    </Button>
                </div>
            </div>
            
            <DndContext
                sensors={sensors}
                collisionDetection={customCollisionDetection}
                onDragStart={handleDragStart}
                onDragOver={handleDragOver}
                onDragEnd={handleDragEnd}
                measuring={{
                    droppable: {
                        strategy: MeasuringStrategy.Always
                    }
                }}
            >
                <div className="flex gap-4 overflow-x-auto pb-6 pt-8 snap-x">
                    {columns && columns.length > 0 ? columns.map((column, index) => (
                        <div 
                            key={column.id} 
                            className="snap-start"
                            id={`column-container-${column.id}`}
                            data-position={index}
                            data-column-id={column.id}
                        >
                            <DroppableColumn
                                column={column}
                                boardId={board.id}
                            />
                        </div>
                    )) : (
                        <div className="p-4 bg-gray-100 rounded-md text-gray-500">
                            Aucune colonne disponible. Créez une nouvelle colonne pour commencer.
                        </div>
                    )}
                    
                    <div className="flex-shrink-0 snap-start">
                        <CreateColumn boardId={board.id} />
                    </div>
                </div>

                <DragOverlay dropAnimation={{
                    duration: 150,
                    easing: 'cubic-bezier(0.18, 0.67, 0.6, 1.22)',
                }}>
                    {activeCard && (
                        <div className="bg-white p-3 rounded-md shadow-xl opacity-95 border-2 border-blue-400 max-w-xs transform scale-105 z-50">
                            {activeCard.labels && activeCard.labels.length > 0 && (
                                <div className="flex flex-wrap gap-1 mb-2">
                                    {activeCard.labels.map((label: any) => (
                                        <div 
                                            key={label.id} 
                                            className="h-2 w-12 rounded-full" 
                                            style={{ backgroundColor: label.color }}
                                        />
                                    ))}
                                </div>
                            )}
                            <div className="font-medium">{activeCard.title}</div>
                            {activeCard.description && (
                                <div className="text-sm text-gray-600 mt-1 line-clamp-2">
                                    {activeCard.description}
                                </div>
                            )}
                            {activeCard.dueDate && (
                                <div className="mt-2 text-xs inline-flex items-center gap-1 bg-blue-50 text-blue-600 px-2 py-1 rounded">
                                    <CalendarIcon className="h-3 w-3" />
                                    <span>{new Date(activeCard.dueDate).toLocaleDateString('fr-FR', { day: '2-digit', month: 'short' })}</span>
                                </div>
                            )}
                        </div>
                    )}
                </DragOverlay>
            </DndContext>
        </div>
    );
} 