import { useEffect, useState } from 'react';
import { DashboardData } from '../../domain/model/response/dashboard_response';
import { dashboardService } from '../../application/dashboard_service';

function getCurrentMonthRange() {
    const now = new Date();
    const year = now.getFullYear();
    const month = String(now.getMonth() + 1).padStart(2, '0');
    const firstDay = `${year}-${month}-01`;
    const lastDayNum = new Date(year, now.getMonth() + 1, 0).getDate();
    const lastDay = `${year}-${month}-${String(lastDayNum).padStart(2, '0')}`;
    return { firstDay, lastDay };
}

export function useDashboardLogic() {
    const { firstDay, lastDay } = getCurrentMonthRange();
    const [data, setData] = useState<DashboardData | null>(null);
    const [error, setError] = useState<string>('');
    const [loading, setLoading] = useState<boolean>(true);

    const [startDate, setStartDate] = useState<string>(firstDay);
    const [endDate, setEndDate] = useState<string>(lastDay);
    const [selectedRepository, setSelectedRepository] = useState<string>('all');

    const loadData = () => {
        setLoading(true);
        setError('');
        dashboardService.getDashboardData({
            startDate: startDate || undefined,
            endDate: endDate || undefined,
            paymentRepositoryId: selectedRepository !== 'all' ? selectedRepository : undefined,
        })
            .then(setData)
            .catch((exception: Error) => setError(exception.message))
            .finally(() => setLoading(false));
    };

    useEffect(() => {
        loadData();
    }, [startDate, endDate, selectedRepository]);

    const handleClearFilters = () => {
        const { firstDay: defaultStart, lastDay: defaultEnd } = getCurrentMonthRange();
        setStartDate(defaultStart);
        setEndDate(defaultEnd);
        setSelectedRepository('all');
    };

    return {
        data,
        error,
        loading,
        startDate,
        setStartDate,
        endDate,
        setEndDate,
        selectedRepository,
        setSelectedRepository,
        handleClearFilters,
        reload: loadData,
    };
}
