import { describe, it, expect, beforeEach, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import DetailAssetModal from '../DetailAssetModal.vue';
import { i18n, setI18nLanguage } from '@/locales';

vi.mock('@inertiajs/vue3', async (importOriginal) => {
  const actual: any = await importOriginal();
  return {
    ...actual,
    router: {
      post: vi.fn(),
    },
    usePage: () => ({
      props: {
        auth: {
          permissions: ['inventory.view', 'inventory.manage', 'inventory.status_approval.request'],
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

describe('DetailAssetModal.vue - Status, BoD/BoC Buttons, and Deletion Note', () => {
  beforeEach(() => {
    setI18nLanguage('id');
    vi.clearAllMocks();
  });

  const baseProps = {
    open: true,
    lot: { id: 1, number: 'LOT-001', vendor: 'Vendor A' },
    users: [],
  };

  const mountModal = (asset: any) => {
    return mount(DetailAssetModal, {
      props: {
        ...baseProps,
        asset,
      },
      global: {
        plugins: [i18n],
        stubs: {
          Teleport: true,
          Transition: false,
          Tabs: { template: '<div><slot /></div>' },
          AuditTrailTab: { template: '<div></div>' },
        },
      },
    });
  };

  it('renders status and approval_status badges in a single line', () => {
    const asset = {
      id: 1,
      number: 'AST-001',
      status: 'Tidak Aktif',
      approval_status: 'Pending:BoD/BoC',
      condition: 'Rusak Total',
    };

    const wrapper = mountModal(asset);
    const text = wrapper.text();

    expect(text).toContain('Status');
    expect(text).toContain('Tidak Aktif');
    expect(text).toContain('Pending: BoD/BoC');
  });

  it('renders deletion note (admin_note) immediately below Condition', async () => {
    const asset = {
      id: 1,
      number: 'AST-001',
      status: 'Tidak Aktif',
      approval_status: 'Pending:BoD/BoC',
      condition: 'Rusak Total',
      admin_note: 'Unit terbakar di generator room',
      price: 15000000,
    };

    const wrapper = mountModal(asset);
    
    // Expand More Info collapsible to expose Nilai
    const moreInfoBtn = wrapper.findAll('button').find(b => b.text().includes('Info Lebih Lanjut'));
    await moreInfoBtn?.trigger('click');

    const html = wrapper.html();

    const conditionIndex = html.indexOf('Kondisi:');
    const adminNoteIndex = html.indexOf('Catatan penghapusan:');
    const valueIndex = html.indexOf('Nilai:');

    expect(conditionIndex).toBeGreaterThan(-1);
    expect(adminNoteIndex).toBeGreaterThan(-1);
    expect(valueIndex).toBeGreaterThan(-1);

    // Assert admin note appears AFTER Condition and BEFORE Value/Price
    expect(adminNoteIndex).toBeGreaterThan(conditionIndex);
    expect(adminNoteIndex).toBeLessThan(valueIndex);
    expect(wrapper.text()).toContain('Unit terbakar di generator room');
  });

  it('renders green and red BoD/BoC document upload buttons when approval_status is Pending:BoD/BoC', () => {
    const asset = {
      id: 1,
      number: 'AST-001',
      status: 'Tidak Aktif',
      approval_status: 'Pending:BoD/BoC',
      condition: 'Rusak Total',
    };

    const wrapper = mountModal(asset);
    const text = wrapper.text();

    // In Indonesian locale
    expect(text).toContain('Unggah Dokumen Persetujuan BoD/BoC');
    expect(text).toContain('Unggah Dokumen Penolakan BoD/BoC');
  });

  it('does not render BoD/BoC upload buttons when asset is not Pending:BoD/BoC', () => {
    const asset = {
      id: 1,
      number: 'AST-001',
      status: 'Tersedia',
      approval_status: null,
      condition: 'Bagus',
    };

    const wrapper = mountModal(asset);
    const text = wrapper.text();

    expect(text).not.toContain('Unggah Dokumen Persetujuan BoD/BoC');
    expect(text).not.toContain('Unggah Dokumen Penolakan BoD/BoC');
  });

  it('renders document view buttons when bod_boc_approval_url or bod_boc_disapproval_url exist', () => {
    const asset = {
      id: 1,
      number: 'AST-001',
      status: 'Dihapus',
      approval_status: null,
      condition: 'Rusak Total',
      bod_boc_approval_url: 'bod_approvals/doc1.pdf',
      bod_boc_disapproval_url: 'bod_disapprovals/doc2.pdf',
    };

    const wrapper = mountModal(asset);
    const text = wrapper.text();

    expect(text).toContain('Buka Form Persetujuan BoD/BoC');
    expect(text).toContain('Buka Form Penolakan BoD/BoC');
  });

  it('fetches lifecycles on-demand when switching to Jejak Audit tab with an asset missing lifecycles array', async () => {
    const mockLifecycles = [
      { id: 101, action_type: 'Pendaftaran', actor_name: 'Admin', created_at: '01/01/2026' },
    ];
    const fetchSpy = vi.spyOn(globalThis, 'fetch').mockResolvedValue({
      ok: true,
      json: async () => ({
        id: 42,
        lifecycles: mockLifecycles,
      }),
    } as Response);

    const asset: any = {
      id: 42,
      number: 'AST-042',
      status: 'Tersedia',
      condition: 'Bagus',
    };

    const wrapper = mountModal(asset);
    expect(fetchSpy).not.toHaveBeenCalled();

    (wrapper.vm as any).detailActiveTab = 'Jejak Audit';
    await new Promise((r) => setTimeout(r, 10));

    expect(fetchSpy).toHaveBeenCalledWith('/smart/stock-management/assets/42', {
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    });
    expect(asset.lifecycles).toEqual(mockLifecycles);

    fetchSpy.mockRestore();
  });

  it('hides Perawatan tab if asset status is Tidak Aktif, Belum Diverifikasi, Verifikasi Ditolak, or Lisensi Kedaluwarsa', () => {
    const disallowed = ['Tidak Aktif', 'Belum Diverifikasi', 'Verifikasi Ditolak', 'Lisensi Kedaluwarsa'];

    for (const status of disallowed) {
      const wrapper = mountModal({
        id: 1,
        number: 'AST-001',
        status,
        condition: 'Bagus',
      });
      const tabIds = (wrapper.vm as any).tabs.map((t: any) => t.id);
      expect(tabIds).not.toContain('Perawatan');
    }

    // When status is allowed, Perawatan tab is present
    const allowedWrapper = mountModal({
      id: 2,
      number: 'AST-002',
      status: 'Tersedia',
      condition: 'Bagus',
    });
    const allowedTabIds = (allowedWrapper.vm as any).tabs.map((t: any) => t.id);
    expect(allowedTabIds).toContain('Perawatan');
  });

  describe('New Single-Column Asset Detail Layout & Dynamic Fields', () => {
    it('renders legacy_number ONLY when it differs from number', () => {
      // 1. Same number -> legacy number NOT rendered
      const wrapperSame = mountModal({
        id: 1,
        number: 'AST-001',
        legacy_number: 'AST-001',
        status: 'Tersedia',
        condition: 'Bagus',
      });
      expect(wrapperSame.text()).not.toContain('Nomor Aset Lama:');

      // 2. Different number -> legacy number rendered
      const wrapperDiff = mountModal({
        id: 2,
        number: 'AST-002',
        legacy_number: 'LEGACY-999',
        status: 'Tersedia',
        condition: 'Bagus',
      });
      expect(wrapperDiff.text()).toContain('Nomor Aset Lama:');
      expect(wrapperDiff.text()).toContain('LEGACY-999');
    });

    it('renders category and type together as Category (Type)', () => {
      const wrapper = mountModal({
        id: 1,
        number: 'AST-001',
        barang_category: 'Komputer',
        barang_subcategory: 'Laptop',
        status: 'Tersedia',
        condition: 'Bagus',
      });
      expect(wrapper.text()).toContain('Kategori:');
      expect(wrapper.text()).toContain('Komputer (Laptop)');
    });

    it('renders dynamic model specifications: comp_specification for COMP and model spec for other', () => {
      // COMP category uses asset comp_specification
      const compWrapper = mountModal({
        id: 1,
        number: 'AST-COMP-01',
        barang_brand: 'Lenovo',
        barang_nama: 'ThinkPad T14',
        barang_specification: 'Base Model Spec',
        comp_specification: 'Core i7 32GB 1TB SSD',
        barang_category_code: 'COMP',
        status: 'Tersedia',
        condition: 'Bagus',
      });
      expect(compWrapper.text()).toContain('Model:');
      expect(compWrapper.text()).toContain('Lenovo ThinkPad T14 Core i7 32GB 1TB SSD');
      expect(compWrapper.text()).not.toContain('Base Model Spec');

      // Non-COMP category uses model specification
      const nonCompWrapper = mountModal({
        id: 2,
        number: 'AST-FURN-01',
        barang_brand: 'Informa',
        barang_nama: 'Ergonomic Chair',
        barang_specification: 'Black Mesh Hydraulic',
        barang_category_code: 'FURN',
        status: 'Tersedia',
        condition: 'Bagus',
      });
      expect(nonCompWrapper.text()).toContain('Informa Ergonomic Chair Black Mesh Hydraulic');
    });

    it('renders Nopol for vehicle category only', () => {
      const vehicleWrapper = mountModal({
        id: 1,
        number: 'AST-VEH-01',
        barang_category: 'Kendaraan',
        vehicle_registration: 'B 1234 XYZ',
        status: 'Tersedia',
        condition: 'Bagus',
      });
      expect(vehicleWrapper.text()).toContain('Nopol:');
      expect(vehicleWrapper.text()).toContain('B 1234 XYZ');

      const nonVehicleWrapper = mountModal({
        id: 2,
        number: 'AST-NON-01',
        barang_category: 'Elektronik',
        vehicle_registration: 'B 1234 XYZ',
        status: 'Tersedia',
        condition: 'Bagus',
      });
      expect(nonVehicleWrapper.text()).not.toContain('Nopol:');
    });

    it('renders external link icon next to status when Dipinjam or Di-assign and valid request_id exists', () => {
      // Dipinjam with active_borrowing.request_id
      const borrowedWrapper = mountModal({
        id: 1,
        number: 'AST-001',
        status: 'Dipinjam (ST)',
        condition: 'Bagus',
        active_borrowing: { request_id: 123 },
      });
      const borrowLink = borrowedWrapper.find('a[href="/smart/borrowed/123"]');
      expect(borrowLink.exists()).toBe(true);
      expect(borrowLink.attributes('target')).toBe('_blank');

      // Di-assign with request_id -> now correctly routes to /smart/borrowed/200
      const assignedWrapper = mountModal({
        id: 2,
        number: 'AST-002',
        status: 'Di-assign (LT)',
        condition: 'Bagus',
        active_borrowing: { user_id: 45, request_id: 200 },
      });
      const assignLink = assignedWrapper.find('a[href="/smart/borrowed/200"]');
      expect(assignLink.exists()).toBe(true);
      expect(assignLink.attributes('target')).toBe('_blank');

      // Di-assign resolving request_id via lifecycles FK
      const assignedWithLifecycleWrapper = mountModal({
        id: 4,
        number: 'AST-004',
        status: 'Di-assign (LT)',
        condition: 'Bagus',
        lifecycles: [
          { id: 10, request_id: 300, action_type: 'Assignment', status: 'Di-assign (LT)' }
        ],
      });
      const lifecycleLink = assignedWithLifecycleWrapper.find('a[href="/smart/borrowed/300"]');
      expect(lifecycleLink.exists()).toBe(true);
      expect(lifecycleLink.attributes('target')).toBe('_blank');

      // Di-assign without request_id -> external link is NOT rendered (only when valid)
      const invalidAssignedWrapper = mountModal({
        id: 5,
        number: 'AST-005',
        status: 'Di-assign (LT)',
        condition: 'Bagus',
      });
      expect(invalidAssignedWrapper.find('a[title="Buka Halaman Peminjaman / Assignment"]').exists()).toBe(false);

      // Tersedia -> No external link icon
      const availableWrapper = mountModal({
        id: 3,
        number: 'AST-003',
        status: 'Tersedia',
        condition: 'Bagus',
      });
      expect(availableWrapper.find('a[title="Buka Halaman Peminjaman / Assignment"]').exists()).toBe(false);
    });

    it('renders label Lokasi (not Lokasi Penyimpanan) and Jenis Peminjaman', async () => {
      const wrapper = mountModal({
        id: 1,
        number: 'AST-001',
        status: 'Tersedia',
        condition: 'Bagus',
        location: 'Gd. Utama Lt. 2',
        borrowing_type: 'LT',
      });

      expect(wrapper.text()).toContain('Lokasi:');
      expect(wrapper.text()).not.toContain('Lokasi Penyimpanan:');

      // Hidden initially under More Info
      expect(wrapper.text()).not.toContain('Jenis Peminjaman:');

      // Click More Info to reveal
      const moreInfoBtn = wrapper.findAll('button').find(b => b.text().includes('Info Lebih Lanjut'));
      expect(moreInfoBtn?.exists()).toBe(true);
      await moreInfoBtn?.trigger('click');

      expect(wrapper.text()).toContain('Jenis Peminjaman:');
      expect(wrapper.text()).toContain('LT');
    });

    it('formats project burden with project number', async () => {
      const wrapper = mountModal({
        id: 1,
        number: 'AST-001',
        status: 'Tersedia',
        condition: 'Bagus',
        burden: 'Project',
        project_no: 'PRJ-2026-001',
        project_name: 'Smart Office Implementation',
      });

      // Expand More Info collapsible
      const moreInfoBtn = wrapper.findAll('button').find(b => b.text().includes('Info Lebih Lanjut'));
      await moreInfoBtn?.trigger('click');

      expect(wrapper.text()).toContain('Pembebanan:');
      expect(wrapper.text()).toContain('Project [PRJ-2026-001] Smart Office Implementation');
    });

    it('renders Category in bold and toggles collapsible More Info', async () => {
      const wrapper = mountModal({
        id: 1,
        number: 'AST-001',
        status: 'Tersedia',
        condition: 'Bagus',
        barang_category: 'Hardware',
        barang_subcategory: 'Laptop',
        price: 20000000,
        classification: 'Aset',
      });

      // Check Category is rendered bold
      const categoryParagraph = wrapper.findAll('p').find(p => p.text().includes('Kategori:'));
      expect(categoryParagraph?.classes()).toContain('font-bold');

      // Initial state: More Info hidden
      expect(wrapper.text()).not.toContain('Nilai:');
      expect(wrapper.text()).not.toContain('Klasifikasi:');

      // Click to expand
      const moreInfoBtn = wrapper.findAll('button').find(b => b.text().includes('Info Lebih Lanjut'));
      await moreInfoBtn?.trigger('click');

      expect(wrapper.text()).toContain('Nilai:');
      expect(wrapper.text()).toContain('Rp20.000.000');
      expect(wrapper.text()).toContain('Klasifikasi:');
    });
  });
});
