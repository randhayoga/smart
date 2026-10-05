import { describe, it, expect, beforeEach, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import { i18n, setI18nLanguage } from '@/locales';

vi.mock('../Modals/DetailAssetModal.vue', () => ({
  default: {
    name: 'DetailAssetModal',
    template: '<div></div>',
  },
}));

vi.mock('../Modals/EditAssetModal.vue', () => ({
  default: {
    name: 'EditAssetModal',
    template: '<div></div>',
  },
}));

vi.mock('@inertiajs/vue3', async (importOriginal) => {
  const actual: any = await importOriginal();
  return {
    ...actual,
    router: {
      post: vi.fn(),
      delete: vi.fn(),
    },
    usePage: () => ({
      props: {
        flash: {},
        auth: { user: { id: 1 } },
      },
    }),
  };
});

import DaftarAsetTab from '../DaftarAsetTab.vue';

describe('DaftarAsetTab.vue - statusScope Flag', () => {
  beforeEach(() => {
    setI18nLanguage('id');
    vi.clearAllMocks();
  });

  const sampleUnits = [
    {
      id: 1,
      number: '00001-LP-PTRE-26',
      status: 'Belum Diverifikasi',
      condition: 'Bagus',
      price: 1000000,
      image_url: '',
      vehicle_registration: null,
      updated_at: '01-01-2026 10:00',
      location: 'Gudang Pusat',
      location_id: 1,
      floor: null,
      floor_id: null,
      room: null,
      room_id: null,
    },
    {
      id: 2,
      number: '00002-LP-PTRE-26',
      status: 'Pending:BoD/BoC',
      condition: 'Rusak Total',
      price: 2000000,
      image_url: '',
      vehicle_registration: null,
      updated_at: '01-01-2026 10:00',
      location: 'Gudang Pusat',
      location_id: 1,
      floor: null,
      floor_id: null,
      room: null,
      room_id: null,
    },
    {
      id: 3,
      number: '00003-LP-PTRE-26',
      status: 'Pending:DM',
      condition: 'Rusak',
      price: 3000000,
      image_url: '',
      vehicle_registration: null,
      updated_at: '01-01-2026 10:00',
      location: 'Gudang Pusat',
      location_id: 1,
      floor: null,
      floor_id: null,
      room: null,
      room_id: null,
    },
    {
      id: 4,
      number: '00004-LP-PTRE-26',
      status: 'Tersedia',
      condition: 'Bagus',
      price: 4000000,
      image_url: '',
      vehicle_registration: null,
      updated_at: '01-01-2026 10:00',
      location: 'Gudang Pusat',
      location_id: 1,
      floor: null,
      floor_id: null,
      room: null,
      room_id: null,
    },
    {
      id: 5,
      number: '00005-LP-PTRE-26',
      status: 'Tidak Aktif',
      condition: 'Lelang/Hibah',
      price: 5000000,
      image_url: '',
      vehicle_registration: null,
      updated_at: '01-01-2026 10:00',
      location: 'Gudang Pusat',
      location_id: 1,
      floor: null,
      floor_id: null,
      room: null,
      room_id: null,
    },
    {
      id: 6,
      number: '00006-LP-PTRE-26',
      status: 'Standby',
      condition: 'Bagus',
      price: 6000000,
      image_url: '',
      vehicle_registration: null,
      updated_at: '01-01-2026 10:00',
      location: 'Gudang Pusat',
      location_id: 1,
      floor: null,
      floor_id: null,
      room: null,
      room_id: null,
    },
  ];

  const mountTab = (props: any = {}) => {
    return mount(DaftarAsetTab, {
      props: {
        units: sampleUnits,
        locations: [{ id: 1, name: 'Gudang Pusat' }],
        ...props,
      },
      global: {
        plugins: [i18n],
        stubs: {
          DataTable: {
            props: ['data', 'columns'],
            template: '<div data-testid="data-table"><div v-for="item in data" :key="item.id" class="row">{{ item.number }} - {{ item.status }}</div></div>',
          },
          LocationCombobox: { template: '<div></div>' },
          Combobox: { template: '<div></div>' },
          DropdownMenu: { template: '<div><slot /></div>' },
          DropdownMenuTrigger: { template: '<div><slot /></div>' },
          DropdownMenuContent: { template: '<div><slot /></div>' },
          DropdownMenuItem: { template: '<div><slot /></div>' },
          StatusBadge: { template: '<span></span>' },
          DeleteErrorModal: { template: '<div></div>' },
          ExportButtonGroup: { template: '<div></div>' },
          ResetFilterButton: { template: '<div></div>' },
        },
      },
    });
  };

  it('defaults to statusScope="standard" which excludes unverified and pending units', () => {
    const wrapper = mountTab();
    const vm = wrapper.vm as any;

    const numbers = vm.filteredUnits.map((u: any) => u.number);
    expect(numbers).toContain('00004-LP-PTRE-26'); // Tersedia
    expect(numbers).toContain('00005-LP-PTRE-26'); // Tidak Aktif
    expect(numbers).toContain('00006-LP-PTRE-26'); // Standby
    expect(numbers).not.toContain('00001-LP-PTRE-26'); // Belum Diverifikasi
    expect(numbers).not.toContain('00002-LP-PTRE-26'); // Pending:BoD/BoC
    expect(numbers).not.toContain('00003-LP-PTRE-26'); // Pending:DM
  });

  it('filters to only unverified units when statusScope="unverified"', () => {
    const wrapper = mountTab({ statusScope: 'unverified' });
    const vm = wrapper.vm as any;

    const numbers = vm.filteredUnits.map((u: any) => u.number);
    expect(numbers).toEqual(['00001-LP-PTRE-26']);
  });

  it('filters to only BoD/BoC pending units when statusScope="pending:bod/boc"', () => {
    const wrapper = mountTab({ statusScope: 'pending:bod/boc' });
    const vm = wrapper.vm as any;

    const numbers = vm.filteredUnits.map((u: any) => u.number);
    expect(numbers).toEqual(['00002-LP-PTRE-26']);
  });

  it('filters to only DM pending units when statusScope="pending:manager"', () => {
    const wrapper = mountTab({ statusScope: 'pending:manager' });
    const vm = wrapper.vm as any;

    const numbers = vm.filteredUnits.map((u: any) => u.number);
    expect(numbers).toEqual(['00003-LP-PTRE-26']);
  });

  it('retains all units without status exclusion when statusScope="all"', () => {
    const wrapper = mountTab({ statusScope: 'all' });
    const vm = wrapper.vm as any;

    const numbers = vm.filteredUnits.map((u: any) => u.number);
    expect(numbers).toHaveLength(6);
    expect(numbers).toContain('00001-LP-PTRE-26');
    expect(numbers).toContain('00002-LP-PTRE-26');
    expect(numbers).toContain('00003-LP-PTRE-26');
    expect(numbers).toContain('00004-LP-PTRE-26');
    expect(numbers).toContain('00005-LP-PTRE-26');
    expect(numbers).toContain('00006-LP-PTRE-26');
  });

  it('populates availableStatuses accurately according to statusScope', () => {
    const standardWrapper = mountTab({ statusScope: 'standard' });
    const standardVm = standardWrapper.vm as any;
    expect(standardVm.availableStatuses).not.toContain('Belum Diverifikasi');
    expect(standardVm.availableStatuses).not.toContain('Pending:BoD/BoC');
    expect(standardVm.availableStatuses).toContain('Tersedia');

    const allWrapper = mountTab({ statusScope: 'all' });
    const allVm = allWrapper.vm as any;
    expect(allVm.availableStatuses).toContain('Belum Diverifikasi');
    expect(allVm.availableStatuses).toContain('Pending:BoD/BoC');
    expect(allVm.availableStatuses).toContain('Pending:DM');
    expect(allVm.availableStatuses).toContain('Tersedia');
  });
});
