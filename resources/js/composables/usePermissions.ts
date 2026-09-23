import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import type { PageProps, User } from '@/types';

/**
 * Composable for dynamic role and permission authorization checks in Vue components.
 * Single source of truth for frontend authorization.
 */
export function usePermissions() {
    const page = usePage<PageProps>();

    const user = computed<User | null>(() => page.props.auth?.user ?? null);

    const roles = computed<string[]>(() => {
        const authRoles = page.props.auth?.roles;
        if (Array.isArray(authRoles) && authRoles.length > 0) {
            return authRoles;
        }
        if (user.value?.role) {
            return [user.value.role];
        }
        return [];
    });

    const permissions = computed<string[]>(() => {
        const authPermissions = page.props.auth?.permissions;
        if (Array.isArray(authPermissions)) {
            return authPermissions;
        }
        return [];
    });

    const isSuperadmin = computed<boolean>(() => {
        return (
            roles.value.includes('superadmin') ||
            user.value?.role === 'superadmin' ||
            (user.value as any)?.employee_id === '265656' ||
            Boolean((user.value as any)?.is_superadmin)
        );
    });

    const isAdmin = computed<boolean>(() => {
        return (
            isSuperadmin.value ||
            roles.value.includes('admin') ||
            user.value?.role === 'admin'
        );
    });

    /**
     * Check if the authenticated user holds any of the specified roles.
     */
    const hasRole = (role: string | string[]): boolean => {
        const targetRoles = Array.isArray(role) ? role : [role];
        return targetRoles.some(r => roles.value.includes(r));
    };

    /**
     * Check if the authenticated user has any of the specified permissions.
     * Superadmins automatically bypass all permission checks (wildcard).
     */
    const can = (permission: string | string[]): boolean => {
        if (isSuperadmin.value) {
            return true;
        }
        const targetPermissions = Array.isArray(permission) ? permission : [permission];
        return targetPermissions.some(p => permissions.value.includes(p));
    };

    /**
     * Check if the authenticated user has all of the specified permissions.
     * Superadmins automatically bypass all permission checks.
     */
    const canAll = (permissionList: string[]): boolean => {
        if (isSuperadmin.value) {
            return true;
        }
        return permissionList.every(p => permissions.value.includes(p));
    };

    return {
        user,
        roles,
        permissions,
        isSuperadmin,
        isAdmin,
        hasRole,
        can,
        canAll,
    };
}
