import { describe, it, expect, beforeEach } from 'vitest';
import { mount } from '@vue/test-utils';
import ModelDetailTab from '../ModelDetailTab.vue';
import { i18n, setI18nLanguage } from '@/locales';

describe('ModelDetailTab.vue', () => {
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
    specification: 'Intel Core i5, 16GB, 512GB SSD',
    lastUpdate: '15-01-2026 09:30',
    amount: 10,
    image_url: null,
    uom: 'Unit',
    subcategory_id: 1,
    category_id: 1,
    category_code: 'COMP',
    brand_id: 1,
    uom_id: 1,
    is_consumable: false,
    min_stock_threshold: 5,
  };

  const sampleLots = [
    {
      id: 1,
      number: 'LOT-NC-2026-01',
      po_number: 'PO-2026-0001',
      date_of_receipt: '2026-01-15',
      organizer: 'PT Mitra Solusi',
      organizer_id: 1,
      vendor: 'Dell Official Store',
      vendor_id: 10,
      location: 'Gd. IT',
      location_id: 1,
      floor: 'Lt. 2',
      floor_id: 2,
      room: 'Server Room',
      room_id: 3,
      unitPrice: 15000000,
      imageUrl: '',
      assetCount: 10,
      availableAssetCount: 8,
      updated_at: '15-01-2026 09:30',
    },
  ];

  it('renders model details container with standard flex gap-1.5 spacing', () => {
    const wrapper = mount(ModelDetailTab, {
      props: {
        barang: sampleBarang,
        lots: sampleLots,
        organizers: [],
        vendors: [],
        locations: [],
        projects: [],
      },
      global: {
        plugins: [i18n],
        stubs: {
          AssetPhotoGrid: { template: '<div></div>' },
          LotListTab: { template: '<div></div>' },
        },
      },
    });

    const infoContainer = wrapper.find('.flex-grow.flex.flex-col.gap-1\\.5');
    expect(infoContainer.exists()).toBe(true);
    expect(infoContainer.classes()).toContain('text-foreground');
    expect(wrapper.text()).toContain('Dell');
    expect(wrapper.text()).toContain('Latitude 5420');
  });
});
