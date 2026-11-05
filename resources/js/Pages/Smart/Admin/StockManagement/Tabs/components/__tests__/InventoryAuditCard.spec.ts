import { describe, it, expect, beforeEach } from 'vitest';
import { mount } from '@vue/test-utils';
import InventoryAuditCard from '../InventoryAuditCard.vue';
import { i18n, setI18nLanguage } from '@/locales';

describe('InventoryAuditCard.vue', () => {
  beforeEach(() => {
    setI18nLanguage('id');
  });

  const sampleLogs = [
    {
      id: 1,
      barang_number: 'BRG-001',
      barang_name: 'Spidol Hitam',
      lot_number: 'LOT-001',
      action_type: 'stock_out',
      quantity_change: -5,
      actor: 'Admin',
      note: 'Permintaan 112026-0001 untuk Corporate HRD, dengan catatan "Untuk onboarding"',
      request_id: 42,
      request_number: '112026-0001',
      fulfillment_id: 88,
      waktu: '05-11-2026 10:00:00',
      created_at_raw: '2026-11-05T10:00:00Z',
    },
    {
      id: 2,
      barang_number: 'BRG-001',
      barang_name: 'Spidol Hitam',
      lot_number: 'LOT-001',
      action_type: 'stock_in',
      quantity_change: 20,
      actor: 'Admin',
      note: 'Penerimaan stok awal',
      request_id: null,
      request_number: null,
      fulfillment_id: null,
      waktu: '01-11-2026 09:00:00',
      created_at_raw: '2026-11-01T09:00:00Z',
    },
  ];

  it('renders log records and turns request code into interactive hyperlink to request detail', () => {
    const wrapper = mount(InventoryAuditCard, {
      props: {
        logs: sampleLogs,
      },
      global: {
        plugins: [i18n],
      },
    });

    const link = wrapper.find('a[href="/smart/requests/42"]');
    expect(link.exists()).toBe(true);
    expect(link.text()).toContain('112026-0001');

    // Row without request should not have request link
    expect(wrapper.text()).toContain('Penerimaan stok awal');
  });
});
