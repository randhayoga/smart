import { describe, it, expect, beforeEach, vi } from 'vitest';
import { reactive } from 'vue';
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
    useForm: (initialData: any) => reactive({
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

describe('EditAssetModal.vue - Single & Mass Edit Logic and Layout', () => {
  beforeEach(() => {
    setI18nLanguage('id');
    vi.clearAllMocks();
  });

  const baseProps = {
    open: true,
    lot: { id: 1, imageUrl: null, unitPrice: 100000 },
    barang: { id: 1, category: 'Elektronik' },
    locations: [{ id: 1, name: 'Warehouse A' }],
    projects: [{ id: 10, no_project: 'PRJ-01', project_name: 'Alpha Project', client_id: 'CL-1' }],
  };

  const mountModal = (items: any[], extraProps: any = {}) => {
    return mount(EditAssetModal, {
      props: {
        ...baseProps,
        ...extraProps,
        items,
      },
      global: {
        plugins: [i18n],
        stubs: {
          Teleport: true,
          Transition: false,
          LocationCombobox: { template: '<div data-testid="location-combobox"></div>' },
          Combobox: { template: '<div data-testid="project-combobox"></div>' },
          DropdownMenu: { template: '<div><slot /></div>' },
          DropdownMenuTrigger: { template: '<div><slot /></div>' },
          DropdownMenuContent: { template: '<div><slot /></div>' },
          DropdownMenuItem: {
            template: '<div role="menuitem" @click="$emit(\'select\')"><slot /></div>',
            emits: ['select'],
          },
        },
      },
    });
  };

  it('removes all Match Parent (Samakan) buttons and uses Price (Harga) label instead of Unit Price', () => {
    setI18nLanguage('id');
    const wrapper = mountModal([{ id: 1, number: 'AST-001', status: 'Tersedia', condition: 'Bagus' }]);

    const samakanBtns = wrapper.findAll('button').filter(b => b.text().trim() === 'Samakan');
    expect(samakanBtns.length).toBe(0);
    expect(wrapper.text()).toContain('Harga');
    expect(wrapper.text()).not.toContain('Harga Satuan');
    expect(wrapper.text()).toContain('Tipe Peminjaman');
  });

  it('renders status field as disabled with informative helper text and tooltip', () => {
    const wrapper = mountModal([{ id: 1, number: 'AST-001', status: 'Tersedia', condition: 'Bagus' }]);
    const disabledDiv = wrapper.find('.cursor-not-allowed.select-none');
    expect(disabledDiv.exists()).toBe(true);
    expect(disabledDiv.text()).toContain('Tersedia');
    expect(wrapper.text()).toContain('Status tidak dapat diubah secara manual');
    expect(disabledDiv.attributes('title')).toBe('Status tidak dapat diubah secara manual');
  });

  it('does not contain Standby in the status options or dropdown', () => {
    const wrapper = mountModal([{ id: 1, number: 'AST-001', status: 'Tersedia', condition: 'Bagus' }]);
    expect(wrapper.text()).not.toContain('Standby');
  });

  it('does not contain QC Passed or Lelang/Hibah in the condition options or dropdown', () => {
    const wrapper = mountModal([{ id: 1, number: 'AST-001', status: 'Tersedia', condition: 'Bagus' }]);
    expect(wrapper.text()).not.toContain('QC Passed');
    expect(wrapper.text()).not.toContain('Lelang/Hibah');
  });

  it('disallows edit of status when unit status is Dipinjam and displays localized status', () => {
    const wrapper = mountModal([{ id: 1, number: 'AST-001', status: 'Dipinjam', condition: 'Bagus' }]);
    const disabledDiv = wrapper.find('.cursor-not-allowed.select-none');
    expect(disabledDiv.exists()).toBe(true);
    expect(disabledDiv.text()).toContain('Dipinjam');
  });

  it('disallows edit of status when unit status is Belum Diverifikasi', () => {
    const wrapper = mountModal([{ id: 1, number: 'AST-001', status: 'Belum Diverifikasi', condition: 'Bagus' }]);
    const disabledDiv = wrapper.find('.cursor-not-allowed.select-none');
    expect(disabledDiv.exists()).toBe(true);
    expect(disabledDiv.text()).toContain('Belum Diverifikasi');
  });

  it('disallows edit of status when unit status is Tidak Aktif and displays localized status', () => {
    setI18nLanguage('id');
    const wrapperId = mountModal([{ id: 1, number: 'AST-001', status: 'Tidak Aktif', condition: 'Rusak Total' }]);
    const disabledDivId = wrapperId.find('.cursor-not-allowed.select-none');
    expect(disabledDivId.exists()).toBe(true);
    expect(disabledDivId.text()).toContain('Tidak Aktif');
    expect(disabledDivId.text()).not.toContain('Pending');

    setI18nLanguage('en');
    const wrapperEn = mountModal([{ id: 1, number: 'AST-001', status: 'Tidak Aktif', condition: 'Rusak Total' }]);
    const disabledDivEn = wrapperEn.find('.cursor-not-allowed.select-none');
    expect(disabledDivEn.exists()).toBe(true);
    expect(disabledDivEn.text()).toContain('Inactive');
    expect(disabledDivEn.text()).not.toContain('Pending');
  });

  it('renders disabled status for mass edit too', () => {
    const wrapper = mountModal([
      { id: 1, number: 'AST-001', status: 'Tersedia', condition: 'Bagus' },
      { id: 2, number: 'AST-002', status: 'Tersedia', condition: 'Bagus' },
    ]);
    const disabledDiv = wrapper.find('.cursor-not-allowed.select-none');
    expect(disabledDiv.exists()).toBe(true);
  });

  it('populates burden dropdown on single edit and validates project when burden is Project', async () => {
    const wrapper = mountModal([
      { id: 1, number: 'AST-001', status: 'Tersedia', condition: 'Bagus', burden: 'Corporate' },
    ]);
    const vm = wrapper.vm as any;
    expect(vm.form.burden).toBe('Corporate');

    // Select Project from Burden dropdown
    const menuItems = wrapper.findAll('[role="menuitem"]');
    const projectItem = menuItems.find(item => item.text().trim() === 'Project');
    await projectItem?.trigger('click');

    expect(vm.form.burden).toBe('Project');
    expect(wrapper.find('[data-testid="project-combobox"]').exists()).toBe(true);

    vm.form.location_id = 1;
    vm.form.status = 'Tersedia';
    vm.form.condition = 'Bagus';
    vm.form.type = 'LT';
    vm.form.classification = 'Aset';
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
      { id: 2, number: 'AST-002', status: 'Tersedia', condition: 'Bagus' },
    ]);
    const vm = wrapper.vm as any;
    expect(vm.form.burden).toBe('Tidak berubah');

    vm.form.burden = 'Project';
    vm.form.project_id = '';

    vm.handleSubmit();
    expect(vm.errors.project_id).toBeTruthy();
  });

  it('resets form state and clears errors when modal is closed or submitted', () => {
    const wrapper = mountModal([
      { id: 1, number: 'AST-001', status: 'Tersedia', condition: 'Bagus' },
      { id: 2, number: 'AST-002', status: 'Tersedia', condition: 'Bagus' },
    ]);
    const vm = wrapper.vm as any;
    vm.form.condition = 'Rusak';
    vm.form.type = 'LT';
    vm.errors.condition = 'Some error';

    vm.closeModal();

    expect(vm.form.condition).toBe('');
    expect(vm.form.type).toBe('');
    expect(vm.errors.condition).toBe('');
    expect(vm.form.burden).toBe('Tidak berubah');
  });
});
