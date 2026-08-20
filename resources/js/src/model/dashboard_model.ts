export interface DashboardData {
    updatedAt: string;
    summary: {
        orders: number;
        paidOrders: number;
        pendingOrders: number;
        failedOrders: number;
        projects: number;
        paymentGateways: number;
        paymentRepositories: number;
        paymentMethods: number;
    };
    statusMix: {
        success: number;
        pending: number;
        failedExpired: number;
        successDeg: number;
        pendingDeg: number;
    };
    modeCounts: Array<{
        mode: string;
        total: number;
    }>;
    recentOrders: Array<{
        reference: string;
        type: string;
        paymentMethod: string;
        status: string;
        statusClass: string;
        mode: string;
        createdAt?: string;
    }>;
    repositories: Array<{
        id: string;
        gateway: string;
        mode: string;
    }>;
}
