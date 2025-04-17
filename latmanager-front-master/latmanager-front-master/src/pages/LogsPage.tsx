import OdfLogs from '@/components/home/OdfLogs';

export default function LogsPage() {
    return (
        <div className="py-8">
            <OdfLogs 
                title="Logs ODF"
                showHeader={true}
                showPagination={true}
            />
        </div>
    );
} 