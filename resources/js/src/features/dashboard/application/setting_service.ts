import { SettingRepository } from '../domain/repository/setting_repository';
import { SettingRepositoryImpl } from '../infrastructure/persistence/setting_repository_impl';
import { SettingFilterRequest, SettingItem, SettingListResponse } from '../domain/model/setting/setting_model';

export class SettingService {
    private repo: SettingRepository;

    constructor(repo?: SettingRepository) {
        this.repo = repo ?? new SettingRepositoryImpl();
    }

    async getSettings(params?: SettingFilterRequest): Promise<SettingListResponse> {
        return this.repo.getSettings(params);
    }

    async getSettingById(id: string | number): Promise<SettingItem> {
        return this.repo.getSettingById(id);
    }

    async createSetting(data: Record<string, any>): Promise<SettingItem> {
        return this.repo.createSetting(data);
    }

    async updateSetting(id: string | number, data: Record<string, any>): Promise<SettingItem> {
        return this.repo.updateSetting(id, data);
    }

    async deleteSetting(id: string | number): Promise<any> {
        return this.repo.deleteSetting(id);
    }
}

export const settingService = new SettingService();
