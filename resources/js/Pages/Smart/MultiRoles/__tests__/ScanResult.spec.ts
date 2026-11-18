import { describe, it, expect, beforeEach, vi } from 'vitest';
import { reactive } from 'vue';
import { mount } from '@vue/test-utils';
import { i18n, setI18nLanguage } from '@/locales';
import ScanResult from '../ScanResult.vue';
import ScanAssetDetailTab from '../ScanResult/ScanAssetDetailTab.vue';
import ScanEditAssetTab from '../ScanResult/ScanEditAssetTab.vue';
import ScanMaintenanceTab from '../ScanResult/ScanMaintenanceTab.vue';

const { mockRouterPost } = vi.hoisted(() => ({
  mockRouterPost: vi.fn(),
}));

vi.mock('@inertiajs/vue3', async (importOriginal) => {
  const actual: any = await importOriginal();
  return {
    ...actual,
    router: {
      post: mockRouterPost,
    },
    useForm: (initialData: any) => reactive({
      ...initialData,
      processing: false,
      transform: vi.fn().mockReturnThis(),
      post: vi.fn(),
      reset: vi.fn(),
    }),
    usePage: () => ({
      props: {
        auth: {
          permissions: ['inventory.view', 'inventory.manage', 'inventory.maintenance'],
        },
      },
    }),
  };
});

vi.mock('@/composables/usePermissions', () => ({
  usePermissions: () => ({
    can: (perm: string) => true,
    hasRole: () => true,
    hasAnyRole: () => true,
  }),
}));

describe('ScanResult Componentization, Maintenance Tab, and Parity', () => {
  beforeEach(() => {
    setI18nLanguage('id');
    vi.clearAllMocks();
  });

  const mockAsset = {
    id: 10,
    number: 'AST-2026-0001',
    legacy_number: 'LEGACY-999',
    status: 'Tersedia',
    condition: 'Bagus',
    qc_pass: true,
    last_qc: '2026-11-01 10:00',
    location: 'Gudang Utama',
    location_id: 1,
    floor: null,
    room: null,
    price: 15000000,
    borrowing_type: 'LT',
    type: 'LT',
    classification: 'Aset',
    lot_organizer: 'IT Dept',
    burden: 'Project',
    project_id: 101,
    project_no: 'PRJ-101',
    project_name: 'Alpha Project',
    updated_at: '06-11-2026 15:30',
    barang_brand: 'Lenovo',
    barang_nama: 'ThinkPad T14',
    barang_category: 'Komputer',
    barang_category_code: 'COMP',
    barang_subcategory: 'Laptop',
    comp_specification: 'Intel Core i7 16GB RAM 512GB SSD',
    specification: 'Intel Core i7 16GB RAM 512GB SSD',
    active_borrowing: {
      user_id: 42,
    },
  };

  const mockLocations = [
    { id: 1, name: 'Gudang Utama', is_active: true },
  ];

  const mockProjects = [
    { id: 101, no_project: 'PRJ-101', project_name: 'Alpha Project' },
  ];

  describe('ScanResult.vue - Responsive Tab Bar and Navigation', () => {
    it('renders shortened tab titles in Indonesian ("Detail", "Edit", "Perawatan") and English ("Details", "Edit", "Maintain")', async () => {
      const wrapper = mount(ScanResult, {
        props: {
          asset: mockAsset,
          locations: mockLocations,
          projects: mockProjects,
        },
        global: {
          plugins: [i18n],
          stubs: {
            AppLayout: { template: '<div><slot /></div>' },
            Breadcrumb: { template: '<div><slot /></div>' },
            BreadcrumbList: { template: '<div><slot /></div>' },
            BreadcrumbItem: { template: '<div><slot /></div>' },
            BreadcrumbLink: { template: '<a><slot /></a>' },
            BreadcrumbSeparator: { template: '<span>/</span>' },
            BreadcrumbPage: { template: '<span><slot /></span>' },
          },
        },
      });

      // ID language
      expect(wrapper.text()).toContain('Detail');
      expect(wrapper.text()).toContain('Edit');
      expect(wrapper.text()).toContain('Perawatan');

      // EN language
      setI18nLanguage('en');
      await wrapper.vm.$nextTick();
      expect(wrapper.text()).toContain('Details');
      expect(wrapper.text()).toContain('Edit');
      expect(wrapper.text()).toContain('Maintain');
    });
  });

  describe('ScanAssetDetailTab.vue', () => {
    it('renders first card with <brand> <name>, pure spec, and no category/type', () => {
      const wrapper = mount(ScanAssetDetailTab, {
        props: {
          asset: mockAsset,
        },
        global: {
          plugins: [i18n],
        },
      });

      expect(wrapper.text()).toContain('Lenovo ThinkPad T14');
      expect(wrapper.text()).toContain('LEGACY-999');
      expect(wrapper.text()).toContain('Intel Core i7 16GB RAM 512GB SSD');
      expect(wrapper.find('img').exists()).toBe(true);
    });

    it('renders Quality Control using the datatable format with status and formatted date', () => {
      const wrapper = mount(ScanAssetDetailTab, {
        props: {
          asset: {
            ...mockAsset,
            qc_pass: true,
            last_qc: '2026-11-01',
          },
        },
        global: {
          plugins: [i18n],
        },
      });

      expect(wrapper.text()).toContain('QC Pass');
      expect(wrapper.text()).toContain('01-11-2026');
    });

    it('renders third card collapsed by default and expands on click to show collapsible info', async () => {
      const wrapper = mount(ScanAssetDetailTab, {
        props: {
          asset: mockAsset,
        },
        global: {
          plugins: [i18n],
        },
      });

      expect(wrapper.text()).not.toContain('Rp15.000.000');
      const moreInfoBtn = wrapper.findAll('button').find(b => b.text().includes('Info Lebih Lanjut') || b.text().includes('MORE INFO'));
      expect(moreInfoBtn).toBeDefined();
      await moreInfoBtn!.trigger('click');

      expect(wrapper.text()).toContain('Rp15.000.000');
      expect(wrapper.text()).toContain('LT');
      expect(wrapper.text()).toContain('Aset');
      expect(wrapper.text()).toContain('IT Dept');
    });
  });

  describe('ScanEditAssetTab.vue', () => {
    it('has no "Samakan" or "Apply to All" buttons and no save icon', () => {
      const wrapper = mount(ScanEditAssetTab, {
        props: {
          asset: mockAsset,
          locations: mockLocations,
          projects: mockProjects,
        },
        global: {
          plugins: [i18n],
        },
      });

      expect(wrapper.text()).not.toContain('Samakan');
      expect(wrapper.text()).not.toContain('Apply to All');
      // Save button exists without Save icon class
      const saveBtn = wrapper.findAll('button').find(b => b.text().includes('Simpan') || b.text().includes('Save'));
      expect(saveBtn).toBeDefined();
      expect(saveBtn!.find('svg.lucide-save').exists()).toBe(false);
    });

    it('renders photo field with split Upload & Camera buttons and red trash button', () => {
      const wrapper = mount(ScanEditAssetTab, {
        props: {
          asset: mockAsset,
          locations: mockLocations,
          projects: mockProjects,
        },
        global: {
          plugins: [i18n],
        },
      });

      expect(wrapper.text()).toContain('Unggah');
      expect(wrapper.text()).toContain('Ambil Foto');
      // Red delete button exists
      const trashBtn = wrapper.findAll('button').find(b => b.attributes('title') === 'Hapus Foto' || b.find('svg.lucide-trash-2').exists());
      expect(trashBtn).toBeDefined();
    });
  });

  describe('ScanMaintenanceTab.vue', () => {
    it('renders all maintenance inputs including proof camera selector and submits properly', async () => {
      const mockUsers = [
        { id: 42, name: 'Budi Santoso' },
        { id: 99, name: 'Siti Rahma' },
      ];

      const wrapper = mount(ScanMaintenanceTab, {
        props: {
          asset: mockAsset,
          users: mockUsers,
        },
        global: {
          plugins: [i18n],
        },
      });

      // Verify inputs exist
      expect(wrapper.text()).toContain('Sumber');
      expect(wrapper.text()).toContain('Tipe Perawatan');
      expect(wrapper.text()).toContain('User');
      expect(wrapper.text()).toContain('Kondisi Setelah Perawatan');
      expect(wrapper.text()).toContain('Tanggal');
      expect(wrapper.text()).toContain('Biaya');
      expect(wrapper.text()).toContain('Bukti Perawatan');
      expect(wrapper.text()).toContain('Permasalahan');
      expect(wrapper.text()).toContain('Solusi');

      // Verify Proof input has camera button and upload button
      expect(wrapper.text()).toContain('Pilih File');
      expect(wrapper.text()).toContain('Ambil Foto');

      // Fill in required fields
      const textareas = wrapper.findAll('textarea');
      await textareas[0].setValue('Keyboard tombol spasi macet');
      await textareas[1].setValue('Dibersihkan dan diberi pelumas khusus');

      // Trigger submit
      const submitBtn = wrapper.findAll('button').find(b => b.text().includes('Simpan Perawatan') || b.text().includes('Save'));
      expect(submitBtn).toBeDefined();
      await submitBtn!.trigger('click');

      expect(mockRouterPost).toHaveBeenCalledWith(
        `/smart/stock-management/assets/${mockAsset.id}/maintenances`,
        expect.objectContaining({
          source: 'SMART',
          type: 'Preventive',
          user_id: 42,
          problem: 'Keyboard tombol spasi macet',
          solution: 'Dibersihkan dan diberi pelumas khusus',
          condition: 'Tidak Berubah',
        }),
        expect.anything()
      );
    });
  });

  describe('Serial Number and Warranty Date Parity', () => {
    it('renders serial_number and conditional warranty_date in ScanAssetDetailTab', async () => {
      const wrapper = mount(ScanAssetDetailTab, {
        props: {
          asset: {
            ...mockAsset,
            serial_number: 'SN-SCAN-01',
            warranty_date: '2099-12-31',
            is_non_owned: true,
            lot_age: 3,
          },
          locations: mockLocations,
        },
        global: { plugins: [i18n] },
      });

      expect(wrapper.text()).toContain('Tanggal Akhir Garansi');
      expect(wrapper.text()).toContain('31-12-2099');
      expect(wrapper.text()).toContain('Umur');
      expect(wrapper.text()).toContain('3 tahun');

      // Expand More Info to verify Serial Number is above Last Update and Ownership is above Organizer
      const moreBtn = wrapper.findAll('button').find(b => b.text().toLowerCase().includes('info lebih lanjut'));
      await moreBtn?.trigger('click');
      const text = wrapper.text();
      expect(text).toContain('Nomor Seri');
      expect(text).toContain('SN-SCAN-01');
      expect(text.indexOf('Nomor Seri')).toBeLessThan(text.indexOf('Pembaruan Terakhir'));
      expect(text).toContain('Kepemilikan');
      expect(text).toContain('Hanya Dikelola');
      expect(text.indexOf('Kepemilikan')).toBeLessThan(text.indexOf('Organizer'));
    });

    it('renders serial_number and warranty_date at the bottom of ADDITIONAL DETAILS in ScanEditAssetTab', async () => {
      const wrapper = mount(ScanEditAssetTab, {
        props: {
          asset: {
            ...mockAsset,
            serial_number: 'SN-EDIT-01',
            warranty_date: '2028-05-10',
          },
          locations: mockLocations,
          projects: mockProjects,
        },
        global: { plugins: [i18n] },
      });

      const detailsBtn = wrapper.findAll('button').find(b => b.text().toLowerCase().includes('detail tambahan'));
      await detailsBtn?.trigger('click');

      const snInput = wrapper.find('#scan-edit-serial-number');
      const wdInput = wrapper.find('#scan-edit-warranty-date');
      expect(snInput.exists()).toBe(true);
      expect(wdInput.exists()).toBe(true);
      expect((snInput.element as HTMLInputElement).value).toBe('SN-EDIT-01');
      expect((wdInput.element as HTMLInputElement).value).toBe('2028-05-10');
    });

    it('renders Assign ke Lokasi and conditional PIC Assignment Lokasi in ScanEditAssetTab', async () => {
      setI18nLanguage('id');
      const wrapper = mount(ScanEditAssetTab, {
        props: {
          asset: {
            ...mockAsset,
            status: 'Tersedia',
            is_assigned_to_location: false,
            location_pic_id: null,
          },
          locations: mockLocations,
          projects: mockProjects,
          users: [{ id: 10, name: 'Budi PIC (99001)', employee_id: '99001' }],
        },
        global: { plugins: [i18n] },
      });

      const vm = wrapper.vm as any;
      expect(wrapper.text()).toContain('Assign ke Lokasi');
      expect(wrapper.text()).not.toContain('PIC Assignment Lokasi');

      vm.handleAssignToLocationSelect(true);
      await wrapper.vm.$nextTick();
      expect(wrapper.text()).toContain('PIC Assignment Lokasi');
      expect(vm.form.status).toBe('Di-assign (LT)');

      vm.handleSubmit();
      await wrapper.vm.$nextTick();
      expect(vm.errors.location_pic_id).toBeTruthy();

      vm.form.location_pic_id = 10;
      vm.handleAssignToLocationSelect(false);
      await wrapper.vm.$nextTick();
      expect(vm.form.location_pic_id).toBeNull();
      expect(vm.form.status).toBe('Tersedia');
      expect(wrapper.text()).not.toContain('PIC Assignment Lokasi');
    });
  });
});
