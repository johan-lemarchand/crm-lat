import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Progress } from '@/components/ui/progress';
import type { Board } from '@/types/kanban';

interface BoardStatsProps {
    board: Board;
}

export function BoardStats({ board }: BoardStatsProps) {
    if (!board?.columns) return null;

    const stats = board.columns.reduce((acc, column) => {
        if (!column.cards) return acc;

        const columnStats = {
            total: column.cards.length || 0,
            withDueDate: column.cards.filter(card => card.dueDate)?.length || 0,
            withChecklists: column.cards.filter(card => card.checklists?.length > 0)?.length || 0,
            withLabels: column.cards.filter(card => card.labels?.length > 0)?.length || 0,
        };

        return {
            total: acc.total + columnStats.total,
            withDueDate: acc.withDueDate + columnStats.withDueDate,
            withChecklists: acc.withChecklists + columnStats.withChecklists,
            withLabels: acc.withLabels + columnStats.withLabels,
        };
    }, {
        total: 0,
        withDueDate: 0,
        withChecklists: 0,
        withLabels: 0,
    });

    const totalChecklists = board.columns.reduce((total, column) => {
        if (!column.cards) return total;
        
        return total + column.cards.reduce((cardTotal, card) => {
            if (!card.checklists) return cardTotal;
            return cardTotal + card.checklists.length;
        }, 0);
    }, 0);

    const totalChecklistItems = board.columns.reduce((total, column) => {
        if (!column.cards) return total;
        
        return total + column.cards.reduce((cardTotal, card) => {
            if (!card.checklists) return cardTotal;
            
            return cardTotal + card.checklists.reduce((listTotal, list) => {
                if (!list.items) return listTotal;
                return listTotal + list.items.length;
            }, 0);
        }, 0);
    }, 0);

    const completedChecklistItems = board.columns.reduce((total, column) => {
        if (!column.cards) return total;
        
        return total + column.cards.reduce((cardTotal, card) => {
            if (!card.checklists) return cardTotal;
            
            return cardTotal + card.checklists.reduce((listTotal, list) => {
                if (!list.items) return listTotal;
                return listTotal + list.items.filter(item => item.completed).length;
            }, 0);
        }, 0);
    }, 0);

    const completionRate = totalChecklistItems > 0
        ? (completedChecklistItems / totalChecklistItems) * 100
        : 0;

    return (
        <div className="grid gap-4 md:grid-cols-3">
            <Card>
                <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                    <CardTitle className="text-sm font-medium">Total des cartes</CardTitle>
                </CardHeader>
                <CardContent>
                    <div className="text-2xl font-bold">{stats.total}</div>
                    <p className="text-xs text-muted-foreground">
                        {stats.withDueDate} avec date d'échéance
                    </p>
                </CardContent>
            </Card>

            <Card>
                <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                    <CardTitle className="text-sm font-medium">Checklists</CardTitle>
                </CardHeader>
                <CardContent>
                    <div className="text-2xl font-bold">{totalChecklists}</div>
                    <p className="text-xs text-muted-foreground">
                        {totalChecklistItems} tâches au total
                    </p>
                </CardContent>
            </Card>

            <Card>
                <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                    <CardTitle className="text-sm font-medium">Taux de complétion</CardTitle>
                </CardHeader>
                <CardContent>
                    <div className="text-2xl font-bold">
                        {completionRate.toFixed(1)}%
                    </div>
                    <Progress value={completionRate} className="h-2" />
                </CardContent>
            </Card>
        </div>
    );
} 