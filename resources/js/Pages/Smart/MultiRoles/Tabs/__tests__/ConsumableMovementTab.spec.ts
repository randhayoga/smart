import { describe, it, expect, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import { i18n } from '@/locales';
import ConsumableMovementTab from '../ConsumableMovementTab.vue';

const mockGet = vi.fn();

vi.mock('../components/ConsumableMovementChart.vue', () => ({
  default: {
    name: 'ConsumableMovementChart',
    props: ['trends', 'isLoading'],
    template: '<div data-testid="movement-chart">Chart ({{ trends?.length || 0 }})</div>',
  },
}));

vi.mock('@/Pages/Smart/Admin/StockManagement/Tabs/components/InventoryAuditCard.vue', () => ({
  default: {
    name: 'InventoryAuditCard',
    props: ['logs', 'title', 'hideFilters', 'showHideStockInFilter'],
    template: '<div data-testid="audit-card" :data-hide-filters="hideFilters" :data-show-hide-stock-in="showHideStockInFilter">{{ title }} ({{ logs?.length || 0 }})</div>',
  },
}));

vi.mock('@inertiajs/vue3', async (importOriginal) => {
  const actual: any = await importOriginal();
  return {
    ...actual,
    router: {
      get: (...args: any[]) => mockGet(...args),
      reload: vi.fn(),
    },
    usePage: () => ({
      url: '/smart/dashboard?tab=stock_movement',
      props: {},
    }),
  };
});

describe('ConsumableMovementTab.vue', () => {
  const filters = {
    categories: [{ id: 1, code: 'ATK', name: 'Alat Tulis Kantor' }],
    types: [{ id: 10, code: 'HVS', name: 'Kertas HVS', category_id: 1 }],
    models: [{ id: 100, number: 'MDL-HVS-01', name: 'Kertas A4 Sidu', type_id: 10 }],
    departments: [{ id: 5, name: 'Finance' }],
    projects: [{ id: 25, name: 'Expansion Jakarta', no_project: 'PRJ-01' }],
    availableYears: [2026],
  };

  const movementData = {
    summary: {
      total_in: 200,
      total_out: 80,
      total_in_events: 15,
      total_out_events: 8,
      net_movement: 120,
      total_events: 23,
    },
    trends: [
      { period: 1, label: 'Jan', total_in: 100, total_out: 40 },
      { period: 2, label: 'Feb', total_in: 100, total_out: 40 },
    ],
    logs: [
      {
        id: 1,
        barang_number: 'MDL-HVS-01',
        barang_name: 'Kertas A4 Sidu',
        lot_number: 'LOT-01',
        action_type: 'stock_in',
        quantity_change: 200,
        actor: 'Admin',
        note: 'Penerimaan awal',
        waktu: '01-03-2026 10:00:00',
        created_at_raw: null,
      },
    ],
  };

  it('renders all sections and passes hide-filters to audit card', () => {
    const wrapper = mount(ConsumableMovementTab, {
      props: {
        filters,
        movementData,
      },
      global: {
        plugins: [i18n],
      },
    });

    const text = wrapper.text();
    expect(text).toContain('200');
    expect(text).toContain('80');
    expect(text).toContain('+120');
    const auditCard = wrapper.find('[data-testid="audit-card"]');
    expect(auditCard.exists()).toBe(true);
    expect(auditCard.attributes('data-hide-filters')).toBe('true');
    expect(auditCard.attributes('data-show-hide-stock-in')).toBe('true');
  });

  it('triggers Inertia partial reload with all nulls when filter reset is invoked', async () => {
    mockGet.mockClear();

    const wrapper = mount(ConsumableMovementTab, {
      props: {
        filters,
        movementData,
      },
      global: {
        plugins: [i18n],
      },
    });

    const filterBar = wrapper.findComponent({ name: 'ConsumableMovementFilterBar' });
    filterBar.vm.$emit('reset');
    await new Promise((r) => setTimeout(r, 25));

    expect(mockGet).toHaveBeenCalledWith(
      '/smart/dashboard',
      expect.objectContaining({
        tab: 'stock_movement',
        decade: String(Math.floor(new Date().getFullYear() / 10) * 10),
        year: undefined,
        month: undefined,
      }),
      expect.objectContaining({ only: ['stockMovementData'], preserveState: true })
    );
  });

  it('sends year and decade when year filter is set', async () => {
    mockGet.mockClear();

    const wrapper = mount(ConsumableMovementTab, {
      props: {
        filters,
        movementData,
      },
      global: {
        plugins: [i18n],
      },
    });

    const filterBar = wrapper.findComponent({ name: 'ConsumableMovementFilterBar' });
    filterBar.vm.$emit('update:selectedYear', '2026');
    await new Promise((r) => setTimeout(r, 25));

    expect(mockGet).toHaveBeenCalledWith(
      '/smart/dashboard',
      expect.objectContaining({
        tab: 'stock_movement',
        decade: String(Math.floor(new Date().getFullYear() / 10) * 10),
        year: '2026',
      }),
      expect.objectContaining({ only: ['stockMovementData'], preserveState: true })
    );
  });
});
