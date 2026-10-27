import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount } from '@vue/test-utils';
import { i18n, setI18nLanguage } from '@/locales';
import EmployeeList from '../EmployeeList.vue';

// Mock layout
vi.mock('@/Layouts/AppLayout.vue', () => ({
  default: {
    name: 'AppLayout',
    template: '<div><slot /></div>',
  },
}));

vi.mock('@/Components/DataTable.vue', () => ({
  default: {
    name: 'DataTable',
    props: ['columns', 'data', 'pageSize', 'showSelectionCount', 'defaultSorting'],
    template: '<div data-testid="data-table"></div>',
  },
}));

vi.mock('@/Components/Combobox.vue', () => ({
  default: {
    name: 'Combobox',
    props: ['modelValue', 'options', 'bgClass'],
    template: '<div data-testid="combobox" :data-bg-class="bgClass"></div>',
  },
}));

vi.mock('@/Components/TableSearch.vue', () => ({
  default: {
    name: 'TableSearch',
    props: ['modelValue', 'placeholder', 'bgClass'],
    template: '<input data-testid="table-search" :value="modelValue" :data-bg-class="bgClass" />',
  },
}));

vi.mock('../Modals/EmployeeDetailModal.vue', () => ({
  default: {
    name: 'EmployeeDetailModal',
    props: ['open', 'employee'],
    template: '<div data-testid="employee-detail-modal"></div>',
  },
}));

describe('EmployeeList Layout Tests', () => {
  beforeEach(() => {
    setI18nLanguage('id');
    vi.clearAllMocks();
  });

  it('renders page title as h1 matching /smart/approve-activation design', () => {
    const wrapper = mount(EmployeeList, {
      props: {
        employees: [],
        departments: ['IT', 'HR'],
      },
      global: {
        plugins: [i18n],
      },
    });

    const h1 = wrapper.find('h1');
    expect(h1.exists()).toBe(true);
    expect(h1.classes()).toContain('text-xl');
    expect(h1.classes()).toContain('font-bold');
    expect(h1.text()).toBe('Daftar Karyawan');
  });

  it('does not have an outer enclosing card around the whole page content', () => {
    const wrapper = mount(EmployeeList, {
      props: {
        employees: [],
        departments: ['IT', 'HR'],
      },
      global: {
        plugins: [i18n],
      },
    });

    // Outer card with bg-card rounded-xl border border-border should be removed
    expect(wrapper.find('.bg-card.rounded-xl.border').exists()).toBe(false);
  });

  it('renders TableSearch and Combobox with bg-white matching /smart/approve-activation', () => {
    const wrapper = mount(EmployeeList, {
      props: {
        employees: [],
        departments: ['IT', 'HR'],
      },
      global: {
        plugins: [i18n],
      },
    });

    const search = wrapper.find('[data-testid="table-search"]');
    expect(search.attributes('data-bg-class')).toBe('bg-white');

    const combobox = wrapper.find('[data-testid="combobox"]');
    expect(combobox.attributes('data-bg-class')).toBe('bg-white');
  });
});
