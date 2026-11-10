import { describe, it, expect, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import { i18n } from '@/locales';
import Dashboard from '../Dashboard.vue';

vi.mock('../Tabs/ClassicDashboardTab.vue', () => ({
  default: {
    name: 'ClassicDashboardTab',
    template: '<div data-testid="classic-tab">Classic Tab Content</div>',
  },
}));

vi.mock('../Tabs/ConsumableMovementTab.vue', () => ({
  default: {
    name: 'ConsumableMovementTab',
    template: '<div data-testid="movement-tab">Movement Tab Content</div>',
  },
}));

vi.mock('@inertiajs/vue3', async (importOriginal) => {
  const actual: any = await importOriginal();
  return {
    ...actual,
    usePage: () => ({
      url: '/smart/dashboard',
      props: {},
    }),
  };
});

describe('Dashboard.vue', () => {
  const user = { name: 'Admin User', email: 'admin@test.com' };

  it('renders greeting and classic tab by default', () => {
    const wrapper = mount(Dashboard, {
      props: {
        user,
      },
      global: {
        plugins: [i18n],
        stubs: {
          AppLayout: { template: '<div><slot /></div>' },
        },
      },
    });

    expect(wrapper.text()).toContain('Admin User');
    expect(wrapper.find('[data-testid="classic-tab"]').exists()).toBe(true);
  });

  it('switches to stock movement tab when tab button is clicked', async () => {
    const wrapper = mount(Dashboard, {
      props: {
        user,
      },
      global: {
        plugins: [i18n],
        stubs: {
          AppLayout: { template: '<div><slot /></div>' },
        },
      },
    });

    // Find tabs button for stock movement
    const buttons = wrapper.findAll('button');
    const movementButton = buttons.find((btn) => btn.text().includes('Stock Movement') || btn.text().includes('Pergerakan Stok'));
    expect(movementButton).toBeDefined();

    if (movementButton) {
      await movementButton.trigger('click');
      expect(wrapper.find('[data-testid="movement-tab"]').exists()).toBe(true);
      expect(wrapper.find('[data-testid="classic-tab"]').exists()).toBe(false);
    }
  });

  it('renders WIP tab when selected', async () => {
    const wrapper = mount(Dashboard, {
      props: {
        user,
      },
      global: {
        plugins: [i18n],
        stubs: {
          AppLayout: { template: '<div><slot /></div>' },
        },
      },
    });

    const buttons = wrapper.findAll('button');
    const wipButton = buttons.find((btn) => btn.text().includes('WIP'));
    expect(wipButton).toBeDefined();

    if (wipButton) {
      await wipButton.trigger('click');
      expect(wrapper.text()).toContain('Under development');
    }
  });
});
