import { describe, it, expect, beforeEach } from 'vitest';
import { mount } from '@vue/test-utils';
import ModelDetail from '../ModelDetail.vue';
import { i18n, setI18nLanguage } from '@/locales';

vi.mock('../Tabs/ModelDetailTab.vue', () => ({ default: { template: '<div></div>' } }));
vi.mock('../Tabs/LotListTab.vue', () => ({ default: { template: '<div></div>' } }));
vi.mock('../Tabs/NonConsumableListTab.vue', () => ({ default: { template: '<div></div>' } }));
vi.mock('../Tabs/components/InventoryAuditCard.vue', () => ({ default: { name: 'InventoryAuditCard', template: '<div data-testid="inventory-audit-card"></div>' } }));
vi.mock('../Modals/EditModelModal.vue', () => ({ default: { template: '<div></div>' } }));
vi.mock('@/Components/DeleteConfirmationModal.vue', () => ({ default: { template: '<div></div>' } }));
vi.mock('@/Components/DeleteErrorModal.vue', () => ({ default: { template: '<div></div>' } }));

vi.mock('@inertiajs/vue3', async (importOriginal) => {
  const actual: any = await importOriginal();
  return {
    ...actual,
    usePage: () => ({
      props: { flash: {}, auth: { user: { id: 1 } } },
    }),
    router: {
      delete: vi.fn(),
    },
  };
});

describe('ModelDetail.vue - Audit Manajemen Stok Tab', () => {
  beforeEach(() => {
    setI18nLanguage('id');
  });

  const sampleBarang = {
    id: 100,
    code: 'COMP-NB-001',
    category: 'Komputer',
    subcategory: 'Laptop',
    brand: 'Dell',
    name: 'Latitude 5420',
    specification: 'Intel Core i5',
    lastUpdate: '15-01-2026 09:30',
    amount: 10,
    image_url: null,
    uom: 'Unit',
    subcategory_id: 1,
    category_id: 1,
    category_code: 'COMP',
    brand_id: 1,
    uom_id: 1,
    is_consumable: true,
  };

  const sampleLogs = [
    {
      id: 1,
      barang_number: 'COMP-NB-001',
      barang_name: 'Latitude 5420',
      lot_number: 'LOT-001',
      action_type: 'stock_in',
      quantity_change: 10,
      actor: 'Admin',
      note: 'Stok masuk awal',
      waktu: '15-01-2026 10:00:00',
      created_at_raw: '2026-01-15T10:00:00Z',
    },
  ];

  it('includes "Audit Manajemen Stok" tab and renders InventoryAuditCard when active', async () => {
    const wrapper = mount(ModelDetail, {
      props: {
        itemId: 'COMP-NB-001',
        barang: sampleBarang,
        lots: [],
        brands: [],
        uoms: [],
        organizers: [],
        vendors: [],
        locations: [],
        projects: [],
        inventoryLogs: sampleLogs,
      },
      global: {
        plugins: [i18n],
        stubs: {
          AppLayout: { template: '<div><slot /></div>' },
          Breadcrumb: { template: '<div><slot /></div>' },
          BreadcrumbList: { template: '<div><slot /></div>' },
          BreadcrumbItem: { template: '<div><slot /></div>' },
          BreadcrumbLink: { template: '<div><slot /></div>' },
          BreadcrumbSeparator: { template: '<div><slot /></div>' },
          Button: { template: '<button><slot /></button>' },
          Tabs: {
            props: ['modelValue', 'tabs'],
            template: `
              <div class="tabs-stub">
                <button v-for="t in tabs" :key="t.id" @click="$emit('update:modelValue', t.id)">
                  {{ t.label }}
                </button>
              </div>
            `,
          },
        },
      },
    });

    expect(wrapper.text()).toContain('Audit Manajemen Stok');

    const auditBtn = wrapper.findAll('.tabs-stub button').find(b => b.text().includes('Audit Manajemen Stok'));
    expect(auditBtn).toBeDefined();
    await auditBtn!.trigger('click');

    expect(wrapper.find('[data-testid="inventory-audit-card"]').exists()).toBe(true);
  });

  it('does NOT include "Audit Manajemen Stok" tab for non-consumable models', () => {
    const nonConsumableBarang = { ...sampleBarang, is_consumable: false };
    const wrapper = mount(ModelDetail, {
      props: {
        itemId: 'COMP-NB-001',
        barang: nonConsumableBarang,
        lots: [],
        brands: [],
        uoms: [],
        organizers: [],
        vendors: [],
        locations: [],
        projects: [],
        inventoryLogs: [],
      },
      global: {
        plugins: [i18n],
        stubs: {
          AppLayout: { template: '<div><slot /></div>' },
          Breadcrumb: { template: '<div><slot /></div>' },
          BreadcrumbList: { template: '<div><slot /></div>' },
          BreadcrumbItem: { template: '<div><slot /></div>' },
          BreadcrumbLink: { template: '<div><slot /></div>' },
          BreadcrumbSeparator: { template: '<div><slot /></div>' },
          Button: { template: '<button><slot /></button>' },
          Tabs: {
            props: ['modelValue', 'tabs'],
            template: `
              <div class="tabs-stub">
                <button v-for="t in tabs" :key="t.id">
                  {{ t.label }}
                </button>
              </div>
            `,
          },
        },
      },
    });

    expect(wrapper.text()).not.toContain('Audit Manajemen Stok');
  });
});
