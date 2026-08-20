import { useEffect, useState } from 'react';
import { DashboardData } from '../../domain/model/response/dashboard_response';
import { dashboardService } from '../../application/dashboard_service';

export function useDashboardLogic() {
    const [data, setData] = useState<DashboardData | null>(null);
    const [error, setError] = useState<string>('');
    const [loading, setLoading] = useState<boolean>(true);

    const loadData = () => {
        setLoading(true);
        setError('');
        dashboardService.getDashboardData()
            .then(setData)
            .catch((exception: Error) => setError(exception.message))
            .finally(() => setLoading(false));
    };

    useEffect(() => {
        loadData();
    }, []);

    return {
        data,
        error,
        loading,
        reload: loadData,
    };
}
