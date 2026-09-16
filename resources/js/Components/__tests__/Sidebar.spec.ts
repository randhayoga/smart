import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount } from '@vue/test-utils';
import Sidebar from '../Sidebar.vue';
import { i18n } from '@/locales';

import { usePage } from '@inertiajs/vue3';

// Mock ziggy route helper
(global as any).route = vi.fn((name: string) => `/${name}`);

// Mock @inertiajs/vue3
vi.mock('@inertiajs/vue3', () => ({
  Link: {
    name: 'Link',
    props: ['href', 'method', 'as'],
    template: '<a :href="href"><slot /></a>',
  },
  usePage: vi.fn(() => ({
    url: '/smart/dashboard',
    props: {
      auth: {
        user: { name: 'Admin User', role: 'admin' },
        isAdmin: true,
      },
    },
  })),
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
    vi.clearAllMocks();
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

  it('renders inventory_audit in sidebar for IFS Manager but NOT jejak audit', () => {
    vi.mocked(usePage).mockReturnValueOnce({
      url: '/smart/dashboard',
      props: {
        auth: {
          user: { name: 'IFS Manager', role: 'ifs_manager' },
          isAdmin: true,
        },
      },
    } as any);

    const wrapper = mountSidebar({
      open: true,
      isMobile: false,
      collapsed: false,
    });

    expect(wrapper.text()).toContain('Audit Manajemen Stok');
    expect(wrapper.text()).not.toContain('Pergerakan Aset');
  });

  it('does NOT render audit section in sidebar for regular User', () => {
    vi.mocked(usePage).mockReturnValueOnce({
      url: '/smart/user/dashboard',
      props: {
        auth: {
          user: { name: 'Regular User', role: 'user' },
          isAdmin: false,
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
});
