import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount } from '@vue/test-utils';
import Sidebar from '../Sidebar.vue';
import { i18n } from '@/locales';

import { usePage } from '@inertiajs/vue3';

// Mock ziggy route helper
(global as any).route = vi.fn((name: string) => `/${name}`);

const defaultAdminAuth = {
  user: { name: 'Admin User', role: 'admin' },
  roles: ['admin'],
  permissions: [
    'dashboard.admin.view',
    'inventory.manage',
    'inventory.view',
    'karyawan.view',
    'inventory.status_approval.request',
    'master.view',
    'requests.inbox.view',
    'requests.fulfill',
    'requests.archive.view',
    'audit.view',
  ],
};

const defaultPage = {
  url: '/smart/dashboard',
  props: {
    auth: defaultAdminAuth,
  },
};

// Mock @inertiajs/vue3
vi.mock('@inertiajs/vue3', () => ({
  Link: {
    name: 'Link',
    props: ['href', 'method', 'as'],
    template: '<a :href="href"><slot /></a>',
  },
  usePage: vi.fn(() => defaultPage),
}));

const mountSidebar = (props: any) => {
  return mount(Sidebar, {
    props,
    global: {
      plugins: [i18n],
      mocks: {
        route: (name: string) => `/${name}`,
      },
    },
  });
};

describe('Sidebar.vue', () => {
  beforeEach(() => {
    vi.mocked(usePage).mockReturnValue(defaultPage as any);
  });

  it('renders expanded desktop sidebar when collapsed is false and isMobile is false', () => {
    const wrapper = mountSidebar({
      open: true,
      isMobile: false,
      collapsed: false,
    });

    const aside = wrapper.find('aside');
    expect(aside.exists()).toBe(true);
    expect(aside.classes()).toContain('lg:w-64');
    expect(aside.classes()).not.toContain('lg:w-[4.5rem]');
    expect(wrapper.text()).toContain('MENU UTAMA');
    expect(wrapper.text()).toContain('Dashboard');
  });

  it('renders compact mini-rail desktop sidebar when collapsed is true and isMobile is false', () => {
    const wrapper = mountSidebar({
      open: true,
      isMobile: false,
      collapsed: true,
    });

    const aside = wrapper.find('aside');
    expect(aside.exists()).toBe(true);
    expect(aside.classes()).toContain('lg:w-[4.5rem]');
    expect(aside.classes()).not.toContain('lg:w-64');
  });

  it('emits toggle-collapse when collapse toggle button in sidebar is clicked', async () => {
    const wrapper = mountSidebar({
      open: true,
      isMobile: false,
      collapsed: false,
    });

    const toggleBtn = wrapper.find('[data-testid="sidebar-collapse-toggle"]');
    expect(toggleBtn.exists()).toBe(true);
    await toggleBtn.trigger('click');
    expect(wrapper.emitted('toggle-collapse')).toBeTruthy();
  });

  it('renders mobile sheet when isMobile is true and open is true', () => {
    const wrapper = mountSidebar({
      open: true,
      isMobile: true,
      collapsed: false,
    });

    // Desktop aside should not be rendered
    expect(wrapper.find('aside').exists()).toBe(false);
  });

  it('renders inventory_audit in sidebar for Admin', () => {
    const wrapper = mountSidebar({
      open: true,
      isMobile: false,
      collapsed: false,
    });

    expect(wrapper.text()).toContain('Audit Manajemen Stok');
    expect(wrapper.text()).toContain('Pergerakan Aset');
  });

  it('renders inventory_audit, audit_trail, and stock items in sidebar for IFS Manager, but no admin-exclusive items', () => {
    vi.mocked(usePage).mockReturnValue({
      url: '/smart/dashboard',
      props: {
        auth: {
          user: { name: 'IFS Manager', role: 'ifs_manager' },
          roles: ['ifs_manager'],
          permissions: [
            'dashboard.admin.view',
            'inventory.view',
            'karyawan.view',
            'inventory.status_approval.decide',
            'audit.view',
          ],
        },
      },
    } as any);

    const wrapper = mountSidebar({
      open: true,
      isMobile: false,
      collapsed: false,
    });

    // Allowed items for IFS Manager
    expect(wrapper.text()).toContain('Dashboard');
    expect(wrapper.text()).toContain('Daftar Stok (Habis Pakai)');
    expect(wrapper.text()).toContain('Daftar Aset');
    expect(wrapper.text()).toContain('Daftar Karyawan');
    expect(wrapper.text()).toContain('Perlu Approval');
    expect(wrapper.text()).toContain('Audit Manajemen Stok');
    expect(wrapper.text()).toContain('Pergerakan Aset');

    // Forbidden / Hidden items for IFS Manager
    expect(wrapper.text()).not.toContain('Manajemen Barang');
    expect(wrapper.text()).not.toContain('Master Data');
    expect(wrapper.text()).not.toContain('Pindai Barcode');
    expect(wrapper.text()).not.toContain('Daftar Pending Nonaktif');
    expect(wrapper.text()).not.toContain('Permintaan Aktif');
    expect(wrapper.text()).not.toContain('Arsip');
  });

  it('does NOT render audit section in sidebar for regular User', () => {
    vi.mocked(usePage).mockReturnValue({
      url: '/smart/user/dashboard',
      props: {
        auth: {
          user: { name: 'Regular User', role: 'user' },
          roles: ['user'],
          permissions: [
            'dashboard.user.view',
            'requests.create',
            'requests.view_own',
          ],
        },
      },
    } as any);

    const wrapper = mountSidebar({
      open: true,
      isMobile: false,
      collapsed: false,
    });

    expect(wrapper.text()).not.toContain('Audit Manajemen Stok');
    expect(wrapper.text()).not.toContain('Pergerakan Aset');
  });

  it('renders Access Management section for Superadmin with access.manage permission', () => {
    vi.mocked(usePage).mockReturnValue({
      url: '/smart/dashboard',
      props: {
        auth: {
          user: { name: 'Superadmin User', role: 'superadmin', employee_id: '265656' },
          roles: ['superadmin'],
          permissions: ['access.manage', 'dashboard.admin.view'],
        },
      },
    } as any);

    const wrapper = mountSidebar({
      open: true,
      isMobile: false,
      collapsed: false,
    });

    expect(wrapper.text()).toContain('SUPERADMIN');
    expect(wrapper.text()).toMatch(/Manajemen Akses|Access Management/);
  });

  describe('active link detection', () => {
    it('activates only Daftar Pending Aktivasi and NOT Manajemen Barang when viewing /smart/inventory/pending-aktivasi', () => {
      vi.mocked(usePage).mockReturnValue({
        url: '/smart/inventory/pending-aktivasi',
        props: {
          auth: defaultAdminAuth,
        },
      } as any);

      const wrapper = mountSidebar({
        open: true,
        isMobile: false,
        collapsed: false,
      });

      const pendingActivationLink = wrapper.find('a[href="/smart/inventory/pending-aktivasi"]');
      const inventoryLink = wrapper.find('a[href="/smart/inventory"]');

      expect(pendingActivationLink.exists()).toBe(true);
      expect(inventoryLink.exists()).toBe(true);

      // Pending activation should be active
      expect(pendingActivationLink.classes()).toContain('bg-gradient-primary');
      expect(pendingActivationLink.classes()).toContain('text-white');

      // Item Management (/smart/inventory) should NOT be active
      expect(inventoryLink.classes()).not.toContain('bg-gradient-primary');
      expect(inventoryLink.classes()).toContain('text-foreground');
    });

    it('activates only Manajemen Barang when viewing /smart/inventory', () => {
      vi.mocked(usePage).mockReturnValue({
        url: '/smart/inventory',
        props: {
          auth: defaultAdminAuth,
        },
      } as any);

      const wrapper = mountSidebar({
        open: true,
        isMobile: false,
        collapsed: false,
      });

      const inventoryLink = wrapper.find('a[href="/smart/inventory"]');
      const pendingActivationLink = wrapper.find('a[href="/smart/inventory/pending-aktivasi"]');

      expect(inventoryLink.classes()).toContain('bg-gradient-primary');
      expect(pendingActivationLink.classes()).not.toContain('bg-gradient-primary');
    });

    it('activates Manajemen Barang when viewing item detail /smart/inventory/LAPTOP-01', () => {
      vi.mocked(usePage).mockReturnValue({
        url: '/smart/inventory/LAPTOP-01',
        props: {
          auth: defaultAdminAuth,
        },
      } as any);

      const wrapper = mountSidebar({
        open: true,
        isMobile: false,
        collapsed: false,
      });

      const inventoryLink = wrapper.find('a[href="/smart/inventory"]');
      const pendingActivationLink = wrapper.find('a[href="/smart/inventory/pending-aktivasi"]');

      expect(inventoryLink.classes()).toContain('bg-gradient-primary');
      expect(pendingActivationLink.classes()).not.toContain('bg-gradient-primary');
    });

    it('activates only Daftar Pending Nonaktif and NOT Manajemen Barang when viewing /smart/inventory/pending-nonaktif', () => {
      vi.mocked(usePage).mockReturnValue({
        url: '/smart/inventory/pending-nonaktif',
        props: {
          auth: defaultAdminAuth,
        },
      } as any);

      const wrapper = mountSidebar({
        open: true,
        isMobile: false,
        collapsed: false,
      });

      const pendingInactiveLink = wrapper.find('a[href="/smart/inventory/pending-nonaktif"]');
      const inventoryLink = wrapper.find('a[href="/smart/inventory"]');

      expect(pendingInactiveLink.classes()).toContain('bg-gradient-primary');
      expect(inventoryLink.classes()).not.toContain('bg-gradient-primary');
    });
  });
});

