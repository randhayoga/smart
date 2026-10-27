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
          AssetBorrowFormTab: { template: '<div></div>' },
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

  it('renders deletion note (admin_note) immediately below Condition', () => {
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
});
