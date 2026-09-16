import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount } from '@vue/test-utils';
import Sidebar from '../Sidebar.vue';
import { i18n } from '@/locales';

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
});
