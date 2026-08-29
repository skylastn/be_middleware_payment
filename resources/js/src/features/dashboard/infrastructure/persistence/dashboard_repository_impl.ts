import { DashboardRepository } from '../../domain/repository/dashboard_repository';
import { DashboardData } from '../../domain/model/response/dashboard_response';
import { DashboardRemoteDataSource, DashboardFilterParams } from '../data_source/remote/dashboard_remote_data_source';

export class DashboardRepositoryImpl implements DashboardRepository {
    constructor(
        private readonly remote: DashboardRemoteDataSource = new DashboardRemoteDataSource()
    ) {}

    async getDashboardData(params?: DashboardFilterParams): Promise<DashboardData> {
        return this.remote.getDashboardData(params);
    }
}
