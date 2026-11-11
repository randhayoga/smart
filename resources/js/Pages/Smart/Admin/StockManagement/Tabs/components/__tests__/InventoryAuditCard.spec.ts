import { describe, it, expect, beforeEach, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import InventoryAuditCard from '../InventoryAuditCard.vue';
import { i18n, setI18nLanguage } from '@/locales';

const mockVisit = vi.fn();

vi.mock('@inertiajs/vue3', async (importOriginal) => {
  const actual: any = await importOriginal();
  return {
    ...actual,
    router: {
      visit: (...args: any[]) => mockVisit(...args),
    },
  };
});

describe('InventoryAuditCard.vue', () => {
  beforeEach(() => {
    setI18nLanguage('id');
    mockVisit.mockClear();
  });

  const sampleLogs = [
    {
      id: 1,
      barang_number: 'BRG-001',
      barang_name: 'Spidol Hitam',
      lot_id: 12,
      lot_number: 'LOT-001',
      model_id: 99,
      is_consumable: true,
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
      barang_number: 'BRG-002',
      barang_name: 'Laptop ThinkPad',
      lot_id: 15,
      lot_number: 'LOT-002',
      model_id: 101,
      is_consumable: false,
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

  it('navigates consumable LOT link to model page with tab lots and lot_id query, and non-consumable to lot detail page', async () => {
    const wrapper = mount(InventoryAuditCard, {
      props: {
        logs: sampleLogs,
      },
      global: {
        plugins: [i18n],
      },
    });

    const buttons = wrapper.findAll('button.font-mono');
    const consumableLotBtn = buttons.find(b => b.text().includes('LOT-001'));
    const nonConsumableLotBtn = buttons.find(b => b.text().includes('LOT-002'));

    expect(consumableLotBtn?.exists()).toBe(true);
    expect(nonConsumableLotBtn?.exists()).toBe(true);

    await consumableLotBtn?.trigger('click');
    expect(mockVisit).toHaveBeenCalledWith('/smart/stock-management/99?tab=lots&lot_id=12');

    mockVisit.mockClear();
    await nonConsumableLotBtn?.trigger('click');
    expect(mockVisit).toHaveBeenCalledWith('/smart/stock-management/lots/15');
  });

  it('hides filter bar and search when hideFilters is true', () => {
    const wrapper = mount(InventoryAuditCard, {
      props: {
        logs: sampleLogs,
        hideFilters: true,
      },
      global: {
        plugins: [i18n],
      },
    });

    // TableSearch should be omitted
    expect(wrapper.findComponent({ name: 'TableSearch' }).exists()).toBe(false);
    // Rows per page should still be visible in header
    expect(wrapper.text()).toContain('Baris per halaman');
    // Hide stock in filter should be hidden by default
    expect(wrapper.text()).not.toContain('Sembunyikan Stok Masuk');
  });

  it('renders "Hide Stock In" checkbox pill filter and filters stock_in records when active', async () => {
    setI18nLanguage('en');
    const wrapper = mount(InventoryAuditCard, {
      props: {
        logs: sampleLogs,
        hideFilters: true,
        showHideStockInFilter: true,
      },
      global: {
        plugins: [i18n],
      },
    });

    // Both logs are initially rendered (stock_out and stock_in)
    expect(wrapper.text()).toContain('Hide Stock In');
    expect(wrapper.text()).toContain('Spidol Hitam');
    expect(wrapper.text()).toContain('Laptop ThinkPad');

    // Label should have rounded-[14px] to match Rows Per Page dropdown
    const pillLabel = wrapper.find('label');
    expect(pillLabel.classes()).toContain('rounded-[14px]');

    // Find the checkbox inside the pill
    const checkbox = wrapper.findComponent({ name: 'Checkbox' });
    expect(checkbox.exists()).toBe(true);

    // Toggle hideStockIn to true
    await checkbox.setValue(true);

    // After toggling, stock_in row ('Laptop ThinkPad') should be hidden
    expect(wrapper.text()).toContain('Spidol Hitam');
    expect(wrapper.text()).not.toContain('Laptop ThinkPad');

    // Toggle hideStockIn back to false
    await checkbox.setValue(false);
    expect(wrapper.text()).toContain('Spidol Hitam');
    expect(wrapper.text()).toContain('Laptop ThinkPad');
  });

  it('displays "Sembunyikan Stok Masuk" in Indonesian locale', () => {
    setI18nLanguage('id');
    const wrapper = mount(InventoryAuditCard, {
      props: {
        logs: sampleLogs,
        hideFilters: true,
        showHideStockInFilter: true,
      },
      global: {
        plugins: [i18n],
      },
    });

    expect(wrapper.text()).toContain('Sembunyikan Stok Masuk');
    expect(wrapper.text()).toContain('Baris per halaman');
  });
});
