export interface Board {
    id: number;
    name: string;
    description?: string;
    columns: Column[];
    project: {
        id: number;
        name: string;
    };
}

export interface Column {
    id: number;
    name: string;
    position: number;
    cards: Card[];
}

export interface Card {
    id: number;
    title: string;
    description?: string;
    position: number;
    dueDate?: string;
    labels: Label[];
    checklists: Checklist[];
}

export interface Label {
    id: number;
    name: string;
    color: string;
}

export interface Comment {
    id: number;
    content: string;
    cardId: number;
    createdAt: string;
    updatedAt?: string;
}

export interface ChecklistItem {
    id: number;
    content: string;
    completed: boolean;
}

export interface Checklist {
    id: number;
    title: string;
    items: ChecklistItem[];
}

// Types pour les props des composants
export interface ColumnProps {
    column: Column;
    index: number;
    boardId: number;
}

export interface CardProps {
    card: Card;
    index: number;
    columnId: number;
    boardId: number;
}

// Types pour le drag and drop
export interface DragResult {
    draggableId: string;
    type: string;
    source: {
        droppableId: string;
        index: number;
    };
    destination?: {
        droppableId: string;
        index: number;
    };
}

export interface CardDragResult {
    draggableId: string;
    type: 'CARD';
    source: {
        droppableId: string;
        index: number;
    };
    destination: {
        droppableId: string;
        index: number;
    };
    reason: string;
    mode: string;
} 