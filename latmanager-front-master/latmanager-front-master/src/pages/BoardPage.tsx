import { useParams } from 'react-router-dom';
import { Board } from '@/components/kanban/Board';

export default function BoardPage() {
  const { boardId } = useParams();

  if (!boardId) return null;

  return <Board boardId={parseInt(boardId)} />;
} 