import { describe, it, expect, beforeEach } from 'vitest';
import { mount } from '@vue/test-utils';
import NonConsumableLotDetailTab from '../NonConsumableLotDetailTab.vue';
import { i18n, setI18nLanguage } from '@/locales';

describe('NonConsumableLotDetailTab.vue', () => {
  beforeEach(() => {
    setI18nLanguage('id');
  });

  const sampleLot = {
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
    updated_at: '15-01-2026 09:30',
    barang_id: 100,
    barang_code: 'COMP-NB-001',
    barang_brand: 'Dell',
    barang_nama: 'Latitude 5420',
    barang_specification: 'Intel Core i5, 16GB, 512GB SSD',
    barang_category: 'Komputer',
    barang_category_code: 'COMP',
    barang_subcategory: 'Laptop',
    barang_uom: 'Unit',
  };

  const sampleUnits = [
    { id: 1, number: 'AST-001', status: 'Tersedia', condition: 'Bagus', location: 'Gd. IT', location_id: 1, floor: 'Lt. 2', floor_id: 2, room: 'Server Room', room_id: 3, price: 15000000, image_url: 'units/u1.jpg', vehicle_registration: null, updated_at: '15-01-2026' },
    { id: 2, number: 'AST-002', status: 'Dipinjam (ST)', condition: 'Bagus', location: 'Gd. IT', location_id: 1, floor: 'Lt. 2', floor_id: 2, room: 'Server Room', room_id: 3, price: 15000000, image_url: 'units/u2.jpg', vehicle_registration: null, updated_at: '15-01-2026' },
    { id: 3, number: 'AST-003', status: 'Tersedia', condition: 'Bagus', location: 'Gd. IT', location_id: 1, floor: 'Lt. 2', floor_id: 2, room: 'Server Room', room_id: 3, price: 15000000, image_url: 'units/u3.jpg', vehicle_registration: null, updated_at: '15-01-2026' },
    { id: 4, number: 'AST-004', status: 'Tersedia', condition: 'Bagus', location: 'Gd. IT', location_id: 1, floor: 'Lt. 2', floor_id: 2, room: 'Server Room', room_id: 3, price: 15000000, image_url: 'units/u4.jpg', vehicle_registration: null, updated_at: '15-01-2026' },
  ];

  it('renders non-consumable lot overview in single-column layout with 2x2 photo grid from units', () => {
    const wrapper = mount(NonConsumableLotDetailTab, {
      props: {
        lot: sampleLot,
        units: sampleUnits,
        locations: [],
      },
      global: {
        plugins: [i18n],
        stubs: {
          NonConsumableListTab: { template: '<div></div>' },
          CreateAssetModal: { template: '<div></div>' },
        },
      },
    });

    const text = wrapper.text();
    expect(text).not.toContain('Nomor LOT');
    expect(text).not.toContain('LOT-NC-2026-01');
    expect(text).toContain('Komputer (Laptop)');
    expect(text).toContain('Dell Latitude 5420 Intel Core i5, 16GB, 512GB SSD');
    expect(text).toContain('3 Unit / 4 Unit');
    expect(text).toContain('PO-2026-0001');
    expect(text).toContain('PT Mitra Solusi');

    // Vendor and location should NOT be displayed for non-consumable LOT
    expect(text).not.toContain('Vendor:');
    expect(text).not.toContain('Lokasi default:');

    // Photos should form a 2x2 grid from the 4 distinct unit photos
    const imgs = wrapper.findAll('img');
    expect(imgs.length).toBe(4);
  });
});
