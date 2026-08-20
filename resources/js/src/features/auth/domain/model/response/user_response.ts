import { UserRole } from '../enum/user_role';

export interface User {
    id: number | string;
    name: string;
    email: string;
    role?: UserRole;
}
