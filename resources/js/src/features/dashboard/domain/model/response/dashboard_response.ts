export interface DashboardSummary {
    orders: number;
    paidOrders: number;
    pendingOrders: number;
    failedOrders: number;
    projects: number;
    paymentGateways: number;
    paymentRepositories: number;
    paymentMethods: number;
}

export interface DashboardStatusMix {
    success: number;
    pending: number;
    failedExpired: number;
    successDeg: number;
    pendingDeg: number;
}

export interface DashboardModeCount {
    mode: string;
    total: number;
}

export interface DashboardRecentOrder {
    reference: string;
    type: string;
    paymentMethod: string;
    status: string;
    statusClass: string;
    mode: string;
    createdAt?: string;
}

export interface DashboardProject {
    name: string;
    type: string;
    slug: string;
    callback: string;
}

export interface DashboardRepositoryItem {
    id: string;
    gateway: string;
    mode: string;
}

export interface DashboardData {
    summary: DashboardSummary;
    statusCounts: Record<string, number>;
    statusMix: DashboardStatusMix;
    modeCounts: DashboardModeCount[];
    recentOrders: DashboardRecentOrder[];
    projects: DashboardProject[];
    repositories: DashboardRepositoryItem[];
    updatedAt: string;
}
