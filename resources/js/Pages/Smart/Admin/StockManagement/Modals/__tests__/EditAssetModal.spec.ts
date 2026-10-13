import { describe, it, expect, beforeEach, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import EditAssetModal from '../EditAssetModal.vue';
import { i18n, setI18nLanguage } from '@/locales';

vi.mock('@inertiajs/vue3', async (importOriginal) => {
  const actual: any = await importOriginal();
  return {
    ...actual,
    router: {
      post: vi.fn(),
    },
    useForm: (initialData: any) => ({
      ...initialData,
      _method: 'PUT',
      errors: {},
      transform: vi.fn((fn: any) => ({
        post: vi.fn(),
      })),
      post: vi.fn(),
      reset: vi.fn(),
      clearErrors: vi.fn(),
    }),
  };
});

describe('EditAssetModal.vue - Status Disabling Logic', () => {
  beforeEach(() => {
    setI18nLanguage('id');
    vi.clearAllMocks();
  });

  const baseProps = {
    open: true,
    lot: { id: 1, imageUrl: null, unitPrice: 100000 },
    barang: { id: 1, category: 'Elektronik' },
    locations: [{ id: 1, name: 'Warehouse A' }],
  };

  const mountModal = (items: any[]) => {
    return mount(EditAssetModal, {
      props: {
        ...baseProps,
        items,
      },
      global: {
        plugins: [i18n],
        stubs: {
          Teleport: true,
          Transition: false,
          LocationCombobox: { template: '<div></div>' },
          Combobox: { template: '<div></div>' },
          DropdownMenu: { template: '<div><slot /></div>' },
          DropdownMenuTrigger: { template: '<div><slot /></div>' },
          DropdownMenuContent: { template: '<div><slot /></div>' },
          DropdownMenuItem: { template: '<div><slot /></div>' },
        },
      },
    });
  };

  it('allows single edit of status when unit status is Tersedia', () => {
    const wrapper = mountModal([{ id: 1, number: 'AST-001', status: 'Tersedia', condition: 'Bagus' }]);
    // When enabled, the status button inside DropdownMenuTrigger is rendered instead of disabled div
    const disabledDiv = wrapper.find('.cursor-not-allowed.select-none');
    expect(disabledDiv.exists()).toBe(false);
  });

  it('allows single edit of status when unit status is Standby', () => {
    const wrapper = mountModal([{ id: 1, number: 'AST-001', status: 'Standby', condition: 'Bagus' }]);
    const disabledDiv = wrapper.find('.cursor-not-allowed.select-none');
    expect(disabledDiv.exists()).toBe(false);
  });

  it('disallows single edit of status when unit status is Dipinjam', () => {
    const wrapper = mountModal([{ id: 1, number: 'AST-001', status: 'Dipinjam', condition: 'Bagus' }]);
    const disabledDiv = wrapper.find('.cursor-not-allowed.select-none');
    expect(disabledDiv.exists()).toBe(true);
    expect(disabledDiv.text()).toContain('Dipinjam');
  });

  it('disallows single edit of status when unit status is Belum Diverifikasi', () => {
    const wrapper = mountModal([{ id: 1, number: 'AST-001', status: 'Belum Diverifikasi', condition: 'Bagus' }]);
    const disabledDiv = wrapper.find('.cursor-not-allowed.select-none');
    expect(disabledDiv.exists()).toBe(true);
    expect(disabledDiv.text()).toContain('Belum Diverifikasi');
  });

  it('disallows single edit of status when unit status is Tidak Aktif and displays localized status', () => {
    // Indonesian localization
    setI18nLanguage('id');
    const wrapperId = mountModal([{ id: 1, number: 'AST-001', status: 'Tidak Aktif', condition: 'Lelang/Hibah' }]);
    const disabledDivId = wrapperId.find('.cursor-not-allowed.select-none');
    expect(disabledDivId.exists()).toBe(true);
    expect(disabledDivId.text()).toContain('Tidak Aktif');
    expect(disabledDivId.text()).not.toContain('Pending');

    // English localization
    setI18nLanguage('en');
    const wrapperEn = mountModal([{ id: 1, number: 'AST-001', status: 'Tidak Aktif', condition: 'Lelang/Hibah' }]);
    const disabledDivEn = wrapperEn.find('.cursor-not-allowed.select-none');
    expect(disabledDivEn.exists()).toBe(true);
    expect(disabledDivEn.text()).toContain('Inactive');
    expect(disabledDivEn.text()).not.toContain('Pending');
  });

  it('allows mass edit of status when all units have status Tersedia or Standby', () => {
    const wrapper = mountModal([
      { id: 1, number: 'AST-001', status: 'Tersedia', condition: 'Bagus' },
      { id: 2, number: 'AST-002', status: 'Standby', condition: 'Bagus' },
    ]);
    const disabledDiv = wrapper.find('.cursor-not-allowed.select-none');
    expect(disabledDiv.exists()).toBe(false);
  });

  it('disallows mass edit of status when at least one unit has status other than Tersedia or Standby (e.g. Dipinjam)', () => {
    const wrapper = mountModal([
      { id: 1, number: 'AST-001', status: 'Tersedia', condition: 'Bagus' },
      { id: 2, number: 'AST-002', status: 'Dipinjam', condition: 'Bagus' },
    ]);
    const disabledDiv = wrapper.find('.cursor-not-allowed.select-none');
    expect(disabledDiv.exists()).toBe(true);
  });

  it('disallows mass edit of status when at least one unit has status Belum Diverifikasi', () => {
    const wrapper = mountModal([
      { id: 1, number: 'AST-001', status: 'Tersedia', condition: 'Bagus' },
      { id: 2, number: 'AST-002', status: 'Belum Diverifikasi', condition: 'Bagus' },
    ]);
    const disabledDiv = wrapper.find('.cursor-not-allowed.select-none');
    expect(disabledDiv.exists()).toBe(true);
  });

  it('populates burden on single edit and validates project when burden is Project', () => {
    const wrapper = mountModal([
      { id: 1, number: 'AST-001', status: 'Tersedia', condition: 'Bagus', burden: 'Corporate' },
    ]);
    const vm = wrapper.vm as any;
    expect(vm.form.burden).toBe('Corporate');

    vm.form.location_id = 1;
    vm.form.status = 'Tersedia';
    vm.form.condition = 'Bagus';
    vm.form.type = 'LT';
    vm.form.classification = 'Aset';
    vm.form.burden = 'Project';
    vm.form.project_id = '';

    vm.handleSubmit();
    expect(vm.errors.project_id).toBeTruthy();
  });

  it('validates type and classification on single edit', () => {
    const wrapper = mountModal([
      { id: 1, number: 'AST-001', status: 'Tersedia', condition: 'Bagus', burden: 'Corporate', type: 'LT', classification: 'Aset' },
    ]);
    const vm = wrapper.vm as any;
    vm.form.location_id = 1;
    vm.form.status = 'Tersedia';
    vm.form.condition = 'Bagus';
    vm.form.type = '';
    vm.form.classification = '';

    vm.handleSubmit();
    expect(vm.errors.type).toBeTruthy();
    expect(vm.errors.classification).toBeTruthy();
  });

  it('defaults burden to Tidak berubah on bulk edit and validates project if changed to Project', () => {
    const wrapper = mountModal([
      { id: 1, number: 'AST-001', status: 'Tersedia', condition: 'Bagus' },
      { id: 2, number: 'AST-002', status: 'Standby', condition: 'Bagus' },
    ]);
    const vm = wrapper.vm as any;
    expect(vm.form.burden).toBe('Tidak berubah');

    vm.form.burden = 'Project';
    vm.form.project_id = '';

    vm.handleSubmit();
    expect(vm.errors.project_id).toBeTruthy();
  });
});
