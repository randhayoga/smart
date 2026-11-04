import { describe, it, expect, beforeEach, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import ConsumableLotDetail from '../ConsumableLotDetail.vue';
import { i18n, setI18nLanguage } from '@/locales';
import axios from 'axios';

vi.mock('axios');

vi.mock('@/composables/usePermissions', () => ({
  usePermissions: () => ({
    can: () => true,
    hasRole: () => true,
    hasAnyRole: () => true,
  }),
}));

describe('ConsumableLotDetail.vue', () => {
  beforeEach(() => {
    setI18nLanguage('id');
    vi.clearAllMocks();
  });

  const sampleLot = {
    id: 10,
    number: 'LOT-CONS-001',
    barang_category: 'ATK',
    barang_subcategory: 'Kertas',
    barang_brand: 'PaperOne',
    barang_nama: 'A4 80gsm',
    barang_specification: '500 lembar',
    barang_uom: 'Rim',
    current_quantity: 45,
    initial_quantity: 100,
    location: 'Gudang Logistik',
    floor: 'Lt. 1',
    room: 'Ruang A',
    entry_type: 'Procurement',
    po_number: 'PO-2026-0099',
    date_of_receipt: '2026-03-01',
    organizer: 'PT Mitra Sukses',
    vendor: 'PT Kertas Nusantara',
    vendor_id: 5,
    burden: 'Corporate',
    updated_at: '01-04-2026 10:00',
    imageUrl: null,
  };

  it('renders consumable lot details in single-column layout', async () => {
    (axios.get as any).mockResolvedValueOnce({ data: sampleLot });

    const wrapper = mount(ConsumableLotDetail, {
      props: {
        isOpen: true,
        lotId: 10,
      },
      global: {
        plugins: [i18n],
        stubs: {
          Teleport: true,
          Transition: false,
          Tabs: { template: '<div><slot /></div>' },
        },
      },
    });

    // Wait for axios fetch
    await new Promise((r) => setTimeout(r, 20));

    const text = wrapper.text();
    expect(text).toContain('LOT-CONS-001');
    expect(text).toContain('ATK (Kertas)');
    expect(text).toContain('PaperOne A4 80gsm 500 lembar');
    expect(text).toContain('45 Rim / 100 Rim');
    expect(text).toContain('Lokasi:');
    expect(text).toContain('Gudang Logistik, Lt. 1, Ruang A');
    expect(text).toContain('Nomor PO:');
    expect(text).toContain('PO-2026-0099');
    expect(text).toContain('Tanggal Registrasi:');
    expect(text).toContain('PT Mitra Sukses');
    expect(text).toContain('PT Kertas Nusantara');
    expect(text).toContain('Corporate');
  });

  it('renders dynamic memo link and grant date when entry_type is Hibah', async () => {
    const hibahLot = {
      ...sampleLot,
      entry_type: 'Hibah',
      memo_number: 'MEMO-HIBAH-01',
      memo_url: 'memos/hibah_atk.pdf',
      date_of_grant: '2026-04-01',
    };

    (axios.get as any).mockResolvedValueOnce({ data: hibahLot });

    const wrapper = mount(ConsumableLotDetail, {
      props: {
        isOpen: true,
        lotId: 10,
      },
      global: {
        plugins: [i18n],
        stubs: {
          Teleport: true,
          Transition: false,
          Tabs: { template: '<div><slot /></div>' },
        },
      },
    });

    await new Promise((r) => setTimeout(r, 20));

    expect(wrapper.text()).toContain('Nomor Memo:');
    expect(wrapper.text()).not.toContain('Nomor PO:');
    expect(wrapper.text()).toContain('Tanggal Hibah:');

    const memoLink = wrapper.find('a[href="/media/memos/hibah_atk.pdf"]');
    expect(memoLink.exists()).toBe(true);
    expect(memoLink.attributes('target')).toBe('_blank');
  });
});
