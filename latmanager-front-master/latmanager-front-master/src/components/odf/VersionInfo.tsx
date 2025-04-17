import { api } from '@/lib/api';
import { useQuery } from '@tanstack/react-query';
import { Rocket, Globe2 } from 'lucide-react';

interface Versions {
    manager_version: string;
    Api_Trimble: string;
    Trimble_api: string;
}

export function VersionInfo() {
    const { data: versions } = useQuery<Versions>({
        queryKey: ['versions'],
        queryFn: () => api.get('/api/versions'),
    });

    if (!versions) return null;

    return (
        <div className="flex items-center gap-6 text-sm">
            <div className="flex items-center gap-2 text-blue-600">
                <Rocket className="h-4 w-4" />
                <span className="font-medium">API Version :</span>
                <span>{versions.Api_Trimble}</span>
            </div>
            <div className="flex items-center gap-2 text-emerald-600">
                <Globe2 className="h-4 w-4" />
                <span className="font-medium">Trimble Version API :</span>
                <span>{versions.Trimble_api}</span>
            </div>
        </div>
    );
}
