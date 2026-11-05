import { describe, it, expect, beforeEach, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import { i18n, setI18nLanguage } from '@/locales';
import { reactive } from 'vue';

vi.mock('../ModelDetailTab.vue', () => ({
  default: {
    name: 'ModelDetailTab',
    template: '<div data-testid="model-detail-tab">Model Detail</div>',
  },
}));

vi.mock('../LotListTab.vue', () => ({
  default: {
    name: 'LotListTab',
    template: '<div data-testid="lot-list-tab">Lot List</div>',
  },
}));

vi.mock('../components/InventoryAuditCard.vue', () => ({
  default: {
    name: 'InventoryAuditCard',
    props: ['logs', 'hideBarangColumn'],
    template: '<div data-testid="inventory-audit-card">Audit Logs ({{ logs?.length || 0 }})</div>',
  },
}));

const mockPageProps = reactive({
  flash: {},
  auth: { user: { id: 1, permissions: ['inventory.manage'] } },
});

vi.mock('@inertiajs/vue3', async (importOriginal) => {
  const actual: any = await importOriginal();
  return {
    ...actual,
    router: {
      post: vi.fn(),
      delete: vi.fn(),
    },
    usePage: () => ({
      url: '/smart/stock-management/consumables/ATKHVS40001',
      props: mockPageProps,
    }),
  };
});

import ConsumableListTab from '../ConsumableListTab.vue';

describe('ConsumableListTab.vue', () => {
  beforeEach(() => {
    setI18nLanguage('id');
    vi.clearAllMocks();
  });

  const sampleBarang = {
    id: 1,
    code: 'ATKHVS40001',
    number: 'ATKHVS40001',
    name: 'Kertas HVS A4',
    is_consumable: true,
  };

  const sampleLogs = [
    {
      id: 101,
      barang_number: 'ATKHVS40001',
      barang_name: 'Kertas HVS A4',
      lot_number: 'LOT-001',
      action_type: 'Penambahan Stok',
      quantity_change: 50,
      actor: 'John Doe',
      note: 'Initial import',
      waktu: '01-01-2026 10:00:00',
    },
  ];

  it('renders "Audit Manajemen Stok" tab and switches to InventoryAuditCard when selected', async () => {
    const wrapper = mount(ConsumableListTab, {
      props: {
        barangs: [sampleBarang],
        selectedBarangCode: 'ATKHVS40001',
        inventoryLogs: sampleLogs,
      },
      global: {
        plugins: [i18n],
      },
    });

    // The detailTabs should contain 'Audit Manajemen Stok'
    expect(wrapper.text()).toContain('Audit Manajemen Stok');

    // Click on 'Audit Manajemen Stok' tab button
    const auditTabBtn = wrapper.findAll('button').find((b) => b.text().includes('Audit Manajemen Stok'));
    expect(auditTabBtn).toBeDefined();
    await auditTabBtn!.trigger('click');

    // Should display InventoryAuditCard
    const auditCard = wrapper.find('[data-testid="inventory-audit-card"]');
    expect(auditCard.exists()).toBe(true);
    expect(auditCard.text()).toContain('Audit Logs (1)');
  });
});
