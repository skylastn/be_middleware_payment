import { DashboardRepository } from '../domain/repository/dashboard_repository';
import { DashboardRepositoryImpl } from '../infrastructure/persistence/dashboard_repository_impl';
import { DashboardData } from '../domain/model/response/dashboard_response';

export class DashboardService {
    private repo: DashboardRepository;

    constructor(repo?: DashboardRepository) {
        this.repo = repo ?? new DashboardRepositoryImpl();
    }

    async getDashboardData(): Promise<DashboardData> {
        return this.repo.getDashboardData();
    }
}

export const dashboardService = new DashboardService();
