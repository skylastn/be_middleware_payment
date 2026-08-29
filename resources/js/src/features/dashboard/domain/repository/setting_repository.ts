import { SettingFilterRequest, SettingItem, SettingListResponse } from '../model/setting/setting_model';

export interface SettingRepository {
    getSettings(params?: SettingFilterRequest): Promise<SettingListResponse>;
    getSettingById(id: string | number): Promise<SettingItem>;
    createSetting(data: Record<string, any>): Promise<SettingItem>;
    updateSetting(id: string | number, data: Record<string, any>): Promise<SettingItem>;
    deleteSetting(id: string | number): Promise<any>;
}
