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

  it('filters active employees by default and allows switching to all employees', async () => {
    const wrapper = mount(EmployeeList, {
      props: {
        employees: [
          { id: 1, employee_id: '001', name: 'Active User', department: 'IT', is_active: true },
          { id: 2, employee_id: '002', name: 'Inactive User', department: 'HR', is_active: false },
        ] as any,
        departments: ['IT', 'HR'],
      },
      global: {
        plugins: [i18n],
      },
    });

    const dataTable = wrapper.findComponent({ name: 'DataTable' });
    expect(dataTable.props('data')).toHaveLength(1);
    expect((dataTable.props('data') as any)[0].name).toBe('Active User');

    // Switch statusFilter to 'all'
    const vm = wrapper.vm as any;
    vm.statusFilter = 'all';
    await wrapper.vm.$nextTick();

    expect(dataTable.props('data')).toHaveLength(2);
  });

  it('applies red row class to inactive employees', () => {
    const wrapper = mount(EmployeeList, {
      props: {
        employees: [],
        departments: [],
      },
      global: {
        plugins: [i18n],
      },
    });

    const vm = wrapper.vm as any;
    expect(vm.getRowClass({ is_active: false })).toContain('bg-red-50');
    expect(vm.getRowClass({ is_active: true })).toBe('');
  });

  it('provides a stable defaultSorting reference to DataTable when modal opens and closes', async () => {
    const wrapper = mount(EmployeeList, {
      props: {
        employees: [
          { id: 1, employee_id: '001', name: 'Active User', department: 'IT', is_active: true },
        ] as any,
        departments: ['IT'],
      },
      global: {
        plugins: [i18n],
      },
    });

    const dataTable = wrapper.findComponent({ name: 'DataTable' });
    const initialDefaultSorting = dataTable.props('defaultSorting');
    expect(initialDefaultSorting).toEqual([{ id: 'employee_id', desc: false }]);

    // Open detail modal
    const vm = wrapper.vm as any;
    vm.openDetailModal({ id: 1, employee_id: '001', name: 'Active User', department: 'IT', is_active: true });
    await wrapper.vm.$nextTick();

    // defaultSorting prop reference should remain identical and not trigger resets
    expect(dataTable.props('defaultSorting')).toBe(initialDefaultSorting);

    // Close detail modal
    vm.isDetailModalOpen = false;
    await wrapper.vm.$nextTick();
    expect(dataTable.props('defaultSorting')).toBe(initialDefaultSorting);
  });
});
