import { describe, it, expect, vi, beforeEach } from 'vitest';
import { usePermissions } from '../usePermissions';
import { usePage } from '@inertiajs/vue3';

vi.mock('@inertiajs/vue3', () => ({
    usePage: vi.fn(),
}));

describe('usePermissions composable', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('returns empty roles and permissions for guest/unauthenticated user', () => {
        vi.mocked(usePage).mockReturnValue({
            props: {
                auth: {
                    user: null,
                    roles: [],
                    permissions: [],
                },
            },
        } as any);

        const { roles, permissions, isSuperadmin, isAdmin, can, canAll, hasRole } = usePermissions();

        expect(roles.value).toEqual([]);
        expect(permissions.value).toEqual([]);
        expect(isSuperadmin.value).toBe(false);
        expect(isAdmin.value).toBe(false);
        expect(can('inventory.view')).toBe(false);
        expect(canAll(['inventory.view', 'inventory.manage'])).toBe(false);
        expect(hasRole('admin')).toBe(false);
    });

    it('correctly evaluates permissions for regular user', () => {
        vi.mocked(usePage).mockReturnValue({
            props: {
                auth: {
                    user: { id: 10, name: 'Standard User', role: 'user' },
                    roles: ['user'],
                    permissions: ['dashboard.user.view', 'requests.create', 'requests.view_own'],
                },
            },
        } as any);

        const { can, canAll, hasRole, isSuperadmin, isAdmin } = usePermissions();

        expect(isSuperadmin.value).toBe(false);
        expect(isAdmin.value).toBe(false);
        expect(hasRole('user')).toBe(true);
        expect(hasRole('admin')).toBe(false);
        expect(hasRole(['manager', 'user'])).toBe(true);

        expect(can('requests.create')).toBe(true);
        expect(can(['inventory.manage', 'requests.create'])).toBe(true);
        expect(can('inventory.manage')).toBe(false);

        expect(canAll(['dashboard.user.view', 'requests.create'])).toBe(true);
        expect(canAll(['requests.create', 'master.manage'])).toBe(false);
    });

    it('grants full wildcard access to superadmin without requiring explicit permissions', () => {
        vi.mocked(usePage).mockReturnValue({
            props: {
                auth: {
                    user: { id: 1, name: 'Superadmin User', role: 'superadmin', employee_id: '265656' },
                    roles: ['superadmin'],
                    permissions: ['access.manage'],
                },
            },
        } as any);

        const { isSuperadmin, isAdmin, can, canAll, hasRole } = usePermissions();

        expect(isSuperadmin.value).toBe(true);
        expect(isAdmin.value).toBe(true);
        expect(hasRole('superadmin')).toBe(true);

        // Wildcard check: even permissions not listed in permissions array evaluate to true
        expect(can('access.manage')).toBe(true);
        expect(can('inventory.delete_everything')).toBe(true);
        expect(can(['unknown.perm1', 'unknown.perm2'])).toBe(true);
        expect(canAll(['inventory.view', 'nonexistent.perm'])).toBe(true);
    });

    it('evaluates admin role correctly', () => {
        vi.mocked(usePage).mockReturnValue({
            props: {
                auth: {
                    user: { id: 2, name: 'Admin User', role: 'admin', employee_id: '255578' },
                    roles: ['admin'],
                    permissions: ['inventory.view', 'inventory.manage', 'master.manage'],
                },
            },
        } as any);

        const { isSuperadmin, isAdmin, can, hasRole } = usePermissions();

        expect(isSuperadmin.value).toBe(false);
        expect(isAdmin.value).toBe(true);
        expect(hasRole('admin')).toBe(true);
        expect(hasRole('superadmin')).toBe(false);

        expect(can('inventory.manage')).toBe(true);
        expect(can('access.manage')).toBe(false);
    });
});
