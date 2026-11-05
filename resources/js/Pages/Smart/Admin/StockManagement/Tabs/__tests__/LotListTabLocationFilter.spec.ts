import { describe, it, expect, beforeEach } from 'vitest';
import { mount } from '@vue/test-utils';
import LotListTab from '../LotListTab.vue';
import { i18n, setI18nLanguage } from '@/locales';

vi.mock('../Modals/CreateLotModal.vue', () => ({ default: { template: '<div></div>' } }));
vi.mock('../Modals/EditLotModal.vue', () => ({ default: { template: '<div></div>' } }));
vi.mock('../ConsumableLotDetail.vue', () => ({ default: { template: '<div></div>' } }));
vi.mock('@/Components/DeleteConfirmationModal.vue', () => ({ default: { template: '<div></div>' } }));

vi.mock('@inertiajs/vue3', async (importOriginal) => {
  const actual: any = await importOriginal();
  return {
    ...actual,
    usePage: () => ({
      props: { auth: { user: { id: 1 }, roles: ['Admin'] }, flash: {} },
    }),
    router: {
      delete: vi.fn(),
    },
  };
});

describe('LotListTab.vue - Location Filter for Consumables', () => {
  beforeEach(() => {
    setI18nLanguage('id');
  });

  const sampleConsumableBarang = {
    id: 10,
    code: 'ATK-001',
    category: 'ATK',
    subcategory: 'Kertas',
    brand: 'PaperOne',
    name: 'A4 80gsm',
    specification: '500 sheets',
    lastUpdate: '2026-03-01',
    amount: 50,
    image_url: null,
    uom: 'Rim',
    subcategory_id: 1,
    category_id: 1,
    brand_id: 1,
    uom_id: 1,
    is_consumable: true,
  };

  const sampleLocations = [
    { id: 1, name: 'Gudang Utama', parent_id: null },
    { id: 2, name: 'Lantai 1', parent_id: 1 },
    { id: 3, name: 'Ruang A', parent_id: 2 },
    { id: 4, name: 'Gudang Cabang', parent_id: null },
  ];

  const sampleLots = [
    {
      id: 101,
      number: 'LOT-CONS-01',
      po_number: 'PO-01',
      date_of_receipt: '2026-03-01',
      organizer: 'DIV-IT',
      organizer_id: 1,
      vendor: 'Vendor A',
      vendor_id: 1,
      location: 'Gudang Utama, Lantai 1, Ruang A',
      location_id: 3,
      unitPrice: 50000,
      imageUrl: '',
      assetCount: 0,
      availableAssetCount: 0,
      current_quantity: 30,
      initial_quantity: 50,
      updated_at: '2026-03-01',
    },
    {
      id: 102,
      number: 'LOT-CONS-02',
      po_number: 'PO-02',
      date_of_receipt: '2026-03-02',
      organizer: 'DIV-HR',
      organizer_id: 2,
      vendor: 'Vendor B',
      vendor_id: 2,
      location: 'Gudang Cabang',
      location_id: 4,
      unitPrice: 52000,
      imageUrl: '',
      assetCount: 0,
      availableAssetCount: 0,
      current_quantity: 20,
      initial_quantity: 20,
      updated_at: '2026-03-02',
    },
  ];

  it('renders location filter combobox for consumables and filters lots by descendant location', async () => {
    const wrapper = mount(LotListTab, {
      props: {
        barang: sampleConsumableBarang,
        lots: sampleLots,
        organizers: [],
        vendors: [],
        locations: sampleLocations,
        projects: [],
      },
      global: {
        plugins: [i18n],
        stubs: {
          DataTable: {
            props: ['data'],
            template: `
              <div class="datatable-stub">
                <span v-for="row in data" :key="row.id" class="row-num">{{ row.number }}</span>
              </div>
            `,
          },
          LocationCombobox: {
            props: ['modelValue'],
            template: `<div class="location-combobox-stub" @click="$emit('update:modelValue', '1')"></div>`,
          },
        },
      },
    });

    // Check initial rows
    expect(wrapper.text()).toContain('LOT-CONS-01');
    expect(wrapper.text()).toContain('LOT-CONS-02');

    // Filter by location id 1 (Parent of id 3)
    const locBox = wrapper.find('.location-combobox-stub');
    expect(locBox.exists()).toBe(true);
    await locBox.trigger('click');

    // Only LOT-CONS-01 is under location 1 (descendant 3)
    const rowNums = wrapper.findAll('.row-num').map(el => el.text());
    expect(rowNums).toContain('LOT-CONS-01');
    expect(rowNums).not.toContain('LOT-CONS-02');
  });

  it('does NOT render location filter combobox when barang is NOT consumable', () => {
    const nonConsumableBarang = { ...sampleConsumableBarang, is_consumable: false };
    const wrapper = mount(LotListTab, {
      props: {
        barang: nonConsumableBarang,
        lots: sampleLots,
        organizers: [],
        vendors: [],
        locations: sampleLocations,
        projects: [],
      },
      global: {
        plugins: [i18n],
        stubs: {
          DataTable: true,
          LocationCombobox: true,
        },
      },
    });

    expect(wrapper.findComponent({ name: 'LocationCombobox' }).exists()).toBe(false);
  });
});
