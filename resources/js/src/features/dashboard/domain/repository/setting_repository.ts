import { SettingItem, SettingListResponse } from '../model/response/setting/setting_response';
import { SettingFilterRequest } from '../model/request/setting/setting_filter_request';

export interface SettingRepository {
    getSettings(params?: SettingFilterRequest): Promise<SettingListResponse>;
    getSettingById(id: string | number): Promise<SettingItem>;
    createSetting(data: Record<string, any>): Promise<SettingItem>;
    updateSetting(id: string | number, data: Record<string, any>): Promise<SettingItem>;
    deleteSetting(id: string | number): Promise<any>;
}
