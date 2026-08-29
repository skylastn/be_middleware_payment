import { User } from '@/features/auth/domain/model/response/user_response';
import { authService } from '@/features/auth/application/auth_service';
import { RouteInfo, Theme } from '@/features/dashboard/domain/model/resource_model';
import { navigate } from '@/shared/utils/format_utils';

export interface UseShellLogicProps {
    user: User | null;
    route: RouteInfo;
    theme: Theme;
    onToggleTheme: () => void;
    onLogout: () => void;
}

export function useShellLogic({ onLogout }: UseShellLogicProps) {
    const handleLogout = async () => {
        await authService.logout();
        onLogout();
        navigate('/login');
    };

    return {
        handleLogout,
    };
}
