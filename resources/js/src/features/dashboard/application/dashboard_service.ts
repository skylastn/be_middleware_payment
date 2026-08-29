import { DashboardRepository } from '../domain/repository/dashboard_repository';
import { DashboardRepositoryImpl } from '../infrastructure/persistence/dashboard_repository_impl';
import { DashboardData } from '../domain/model/response/dashboard_response';
import { DashboardFilterParams } from '../infrastructure/data_source/remote/dashboard_remote_data_source';

export class DashboardService {
    private repo: DashboardRepository;

    constructor(repo?: DashboardRepository) {
        this.repo = repo ?? new DashboardRepositoryImpl();
    }

    async getDashboardData(params?: DashboardFilterParams): Promise<DashboardData> {
        return this.repo.getDashboardData(params);
    }
}

export const dashboardService = new DashboardService();
