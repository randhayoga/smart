export interface User {
    id: number;
    name: string;
    email: string;
    email_verified_at?: string;
    avatar?: string;
    role?: string;
    org_name?: string;
}

export type PageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    auth: {
        user: User;
        roles?: string[];
        permissions?: string[];
        pendingRequestCount?: number;
        pendingAssetStatusCount?: number;
        pendingAdminApprovedCount?: number;
        activeRequestsCount?: number;
        notifications?: any[];
        unreadNotificationCount?: number;
        mercure?: {
            hubUrl: string;
            topic: string;
            token: string;
        } | null;
    };
};

declare module 'vue' {
    interface ComponentCustomProperties {
        $can: (permission: string | string[]) => boolean;
        $hasRole: (role: string | string[]) => boolean;
    }
}
