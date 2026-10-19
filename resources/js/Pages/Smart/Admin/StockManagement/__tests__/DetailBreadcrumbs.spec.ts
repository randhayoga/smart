import { describe, it, expect, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import { i18n } from '@/locales';
import ModelDetail from '../ModelDetail.vue';
import NonConsumableLotDetail from '../NonConsumableLotDetail.vue';

// Mock subcomponents and layouts to isolate breadcrumb testing
vi.mock('@/Layouts/AppLayout.vue', () => ({
  default: {
    name: 'AppLayout',
    template: '<div><slot /></div>',
  },
}));

vi.mock('../Tabs/ModelDetailTab.vue', () => ({ default: { template: '<div></div>' } }));
vi.mock('../Tabs/LotListTab.vue', () => ({ default: { template: '<div></div>' } }));
vi.mock('../Tabs/NonConsumableLotDetailTab.vue', () => ({ default: { template: '<div></div>' } }));
vi.mock('../Modals/EditModelModal.vue', () => ({ default: { template: '<div></div>' } }));
vi.mock('../Modals/EditLotModal.vue', () => ({ default: { template: '<div></div>' } }));
vi.mock('../Modals/ConsumableRequestModal.vue', () => ({ default: { template: '<div></div>' } }));
vi.mock('@/Components/DeleteConfirmationModal.vue', () => ({ default: { template: '<div></div>' } }));

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

describe('Stock Management Detail Breadcrumbs', () => {
  it('ModelDetail.vue breadcrumb links to /smart/stock-management', () => {
    const wrapper = mount(ModelDetail, {
      props: {
        itemId: 'COMP-NB-0001',
        barang: {
          id: 1,
          code: 'COMP-NB-0001',
          name: 'Laptop Dell',
          category: 'Computer',
          subcategory: 'Notebook',
          brand: 'Dell',
          amount: 5,
          is_consumable: false,
          lastUpdate: '2026-10-16',
        },
        lots: [],
        locations: [],
        organizers: [],
        vendors: [],
        brands: [],
        uoms: [],
        projects: [],
      } as any,
      global: {
        plugins: [i18n],
      },
    });

    const breadcrumbLinks = wrapper.findAll('a');
    const stockManagementLink = breadcrumbLinks.find(link => link.attributes('href') === '/smart/stock-management');
    expect(stockManagementLink).toBeDefined();
    expect(stockManagementLink?.attributes('href')).toBe('/smart/stock-management');

    // Confirm it does NOT link to /smart/inventory
    const brokenLink = breadcrumbLinks.find(link => link.attributes('href') === '/smart/inventory');
    expect(brokenLink).toBeUndefined();
  });

  it('NonConsumableLotDetail.vue breadcrumb links to /smart/stock-management', () => {
    const wrapper = mount(NonConsumableLotDetail, {
      props: {
        lot: {
          id: 10,
          number: 'LOT-0001-26-COMP-NB-0001',
          barang_code: 'COMP-NB-0001',
          barang_id: 1,
          model_name: 'Laptop Dell',
          units: [],
        },
        units: [],
        locations: [],
        organizers: [],
        vendors: [],
        brands: [],
        uoms: [],
        projects: [],
      } as any,
      global: {
        plugins: [i18n],
      },
    });

    const breadcrumbLinks = wrapper.findAll('a');
    const stockManagementLink = breadcrumbLinks.find(link => link.attributes('href') === '/smart/stock-management');
    expect(stockManagementLink).toBeDefined();
    expect(stockManagementLink?.attributes('href')).toBe('/smart/stock-management');

    // Confirm it does NOT link to /smart/inventory
    const brokenLink = breadcrumbLinks.find(link => link.attributes('href') === '/smart/inventory');
    expect(brokenLink).toBeUndefined();
  });
});
