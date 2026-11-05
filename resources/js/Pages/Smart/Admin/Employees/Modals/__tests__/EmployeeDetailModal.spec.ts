import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount } from '@vue/test-utils';
import { i18n, setI18nLanguage } from '@/locales';
import EmployeeDetailModal from '../EmployeeDetailModal.vue';

// Mock axios
vi.mock('axios', () => ({
  default: {
    get: vi.fn().mockResolvedValue({ data: { active: [], history: [] } }),
  },
}));

// Mock subcomponents
vi.mock('@/Components/DataTable.vue', () => ({
  default: {
    name: 'DataTable',
    props: ['columns', 'data'],
    template: '<div data-testid="data-table"></div>',
  },
}));

vi.mock('@/Components/Tabs.vue', () => ({
  default: {
    name: 'Tabs',
    props: ['modelValue', 'tabs'],
    template: '<div data-testid="tabs"></div>',
  },
}));

vi.mock('@/Pages/Smart/Admin/StockManagement/Modals/DetailAssetModal.vue', () => ({
  default: {
    name: 'DetailAssetModal',
    props: ['open', 'asset'],
    template: '<div></div>',
  },
}));

describe('EmployeeDetailModal.vue', () => {
  beforeEach(() => {
    setI18nLanguage('id');
    vi.clearAllMocks();
  });

  it('renders employee work status below department in Indonesian', () => {
    setI18nLanguage('id');
    const wrapper = mount(EmployeeDetailModal, {
      props: {
        open: true,
        employee: {
          id: 1,
          employee_id: '12345',
          name: 'Budi Santoso',
          department: 'Information Technology',
          is_active: true,
        },
      },
      global: {
        plugins: [i18n],
        stubs: {
          Teleport: true,
        },
      },
    });

    const text = wrapper.text();
    expect(text).toContain('Departemen: Information Technology');
    expect(text).toContain('Status Karyawan: Aktif');
  });

  it('renders employee work status as inactive when is_active is false', () => {
    setI18nLanguage('id');
    const wrapper = mount(EmployeeDetailModal, {
      props: {
        open: true,
        employee: {
          id: 2,
          employee_id: '67890',
          name: 'Ani Wijaya',
          department: 'Human Resources',
          is_active: false,
        },
      },
      global: {
        plugins: [i18n],
        stubs: {
          Teleport: true,
        },
      },
    });

    const text = wrapper.text();
    expect(text).toContain('Departemen: Human Resources');
    expect(text).toContain('Status Karyawan: Tidak Aktif');
  });

  it('renders employee work status in English', () => {
    setI18nLanguage('en');
    const wrapper = mount(EmployeeDetailModal, {
      props: {
        open: true,
        employee: {
          id: 1,
          employee_id: '12345',
          name: 'Budi Santoso',
          department: 'Information Technology',
          is_active: true,
        },
      },
      global: {
        plugins: [i18n],
        stubs: {
          Teleport: true,
        },
      },
    });

    const text = wrapper.text();
    expect(text).toContain('Department: Information Technology');
    expect(text).toContain('Employee Status: Active');
  });
});
