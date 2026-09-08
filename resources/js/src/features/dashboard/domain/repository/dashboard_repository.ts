import { DashboardData } from '../model/response/dashboard_response';
import { DashboardFilterParams } from '../../infrastructure/data_source/remote/dashboard_remote_data_source';

export interface DashboardRepository {
    getDashboardData(params?: DashboardFilterParams): Promise<DashboardData>;
}
