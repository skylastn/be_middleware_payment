import { SettingRepository } from '../../domain/repository/setting_repository';
import { SettingFilterRequest } from '../../domain/model/request/setting/setting_filter_request';
import { SettingItem, SettingListResponse } from '../../domain/model/response/setting/setting_response';
import { SettingRemoteDataSource } from '../data_source/remote/setting_remote_data_source';

export class SettingRepositoryImpl implements SettingRepository {
    constructor(
        private readonly remote: SettingRemoteDataSource = new SettingRemoteDataSource()
    ) {}

    async getSettings(params?: SettingFilterRequest): Promise<SettingListResponse> {
        return this.remote.getSettings(params);
    }

    async getSettingById(id: string | number): Promise<SettingItem> {
        return this.remote.getSettingById(id);
    }

    async createSetting(data: Record<string, any>): Promise<SettingItem> {
        return this.remote.createSetting(data);
    }

    async updateSetting(id: string | number, data: Record<string, any>): Promise<SettingItem> {
        return this.remote.updateSetting(id, data);
    }

    async deleteSetting(id: string | number): Promise<any> {
        return this.remote.deleteSetting(id);
    }
}
