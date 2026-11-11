import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import { i18n, setI18nLanguage } from '@/locales';
import UserManagementTab from '../UserManagementTab.vue';

describe('UserManagementTab.vue', () => {
  const sampleUsers = [
    {
      id: 1,
      employee_id: '1001',
      employee_name: 'Alice Johnson',
      display_name: '1001 - Alice Johnson',
      department: 'Finance & Accounting',
      manager_of: ['Finance & Accounting', 'PRJ-2024-001', 'PRJ-2024-002'],
      role: 'superadmin',
      roles: ['superadmin'],
    },
    {
      id: 2,
      employee_id: '1002',
      employee_name: 'Bob Smith',
      display_name: '1002 - Bob Smith',
      department: 'Human Resources',
      manager_of: [],
      role: 'user',
      roles: ['user'],
    },
  ];

  const sampleRoles = [
    { id: 1, name: 'superadmin', label: 'Super Administrator', permissions: [], users_count: 1 },
    { id: 2, name: 'admin', label: 'Administrator', permissions: [], users_count: 0 },
    { id: 3, name: 'user', label: 'Employee', permissions: [], users_count: 1 },
  ];

  it('renders all four table columns in correct order in English', () => {
    setI18nLanguage('en');
    const wrapper = mount(UserManagementTab, {
      props: {
        users: sampleUsers,
        roles: sampleRoles,
      },
      global: {
        plugins: [i18n],
      },
    });

    const headers = wrapper.findAll('th');
    expect(headers).toHaveLength(4);
    expect(headers[0].text()).toContain('User');
    expect(headers[1].text()).toContain('Departement');
    expect(headers[2].text()).toContain('Role');
    expect(headers[3].text()).toContain('DM/PM of');
  });

  it('renders table columns in correct order in Indonesian', () => {
    setI18nLanguage('id');
    const wrapper = mount(UserManagementTab, {
      props: {
        users: sampleUsers,
        roles: sampleRoles,
      },
      global: {
        plugins: [i18n],
      },
    });

    const headers = wrapper.findAll('th');
    expect(headers).toHaveLength(4);
    expect(headers[0].text()).toContain('Pengguna');
    expect(headers[1].text()).toContain('Departemen');
    expect(headers[2].text()).toContain('Peran');
    expect(headers[3].text()).toContain('DM/PM dari');
  });

  it('renders user department and newline-separated DM/PM list or fallback dash', () => {
    setI18nLanguage('en');
    const wrapper = mount(UserManagementTab, {
      props: {
        users: sampleUsers,
        roles: sampleRoles,
      },
      global: {
        plugins: [i18n],
      },
    });

    const rows = wrapper.findAll('tbody tr');
    expect(rows).toHaveLength(2);

    // Row 1: Alice Johnson
    const row1Cells = rows[0].findAll('td');
    expect(row1Cells[0].text()).toContain('1001 - Alice Johnson');
    expect(row1Cells[1].text()).toBe('Finance & Accounting');
    // DM/PM of column renders multiple lines
    expect(row1Cells[3].text()).toContain('Finance & Accounting');
    expect(row1Cells[3].text()).toContain('PRJ-2024-001');
    expect(row1Cells[3].text()).toContain('PRJ-2024-002');

    // Row 2: Bob Smith (not a manager)
    const row2Cells = rows[1].findAll('td');
    expect(row2Cells[0].text()).toContain('1002 - Bob Smith');
    expect(row2Cells[1].text()).toBe('Human Resources');
    // DM/PM of column renders '-'
    expect(row2Cells[3].text()).toBe('-');
  });

  it('renders shortened role dropdown in table (w-[150px]) and filter dropdown (w-[150px])', () => {
    const wrapper = mount(UserManagementTab, {
      props: {
        users: sampleUsers,
        roles: sampleRoles,
      },
      global: {
        plugins: [i18n],
      },
    });

    // Check UserRoleSelect button width
    const userRoleSelectBtn = wrapper.findComponent({ name: 'UserRoleSelect' }).find('button');
    expect(userRoleSelectBtn.classes()).toContain('w-[150px]');

    // Check role filter container width
    const filterContainer = wrapper.find('.w-\\[150px\\]');
    expect(filterContainer.exists()).toBe(true);
  });

  it('displays "DM/PM" as role name for manager role in both EN and ID', async () => {
    const managerUsers = [
      {
        id: 3,
        employee_id: '1003',
        employee_name: 'Charlie Brown',
        display_name: '1003 - Charlie Brown',
        department: 'Operations',
        manager_of: ['Operations'],
        role: 'manager',
        roles: ['manager'],
      },
    ];
    const roles = [
      { id: 4, name: 'manager', label: 'Department / Project Manager', permissions: [], users_count: 1 },
    ];

    setI18nLanguage('en');
    const wrapper = mount(UserManagementTab, {
      props: {
        users: managerUsers,
        roles,
      },
      global: {
        plugins: [i18n],
      },
    });

    expect(wrapper.findComponent({ name: 'UserRoleSelect' }).text()).toContain('DM/PM');

    setI18nLanguage('id');
    const wrapperId = mount(UserManagementTab, {
      props: {
        users: managerUsers,
        roles,
      },
      global: {
        plugins: [i18n],
      },
    });

    expect(wrapperId.findComponent({ name: 'UserRoleSelect' }).text()).toContain('DM/PM');
  });

  it('renders updated search placeholder in English and Indonesian', () => {
    setI18nLanguage('en');
    const wrapperEn = mount(UserManagementTab, {
      props: {
        users: sampleUsers,
        roles: sampleRoles,
      },
      global: {
        plugins: [i18n],
      },
    });
    const inputEn = wrapperEn.find('input[type="text"]');
    expect(inputEn.attributes('placeholder')).toBe('Search NPK, Name, Departement, or Project Number...');

    setI18nLanguage('id');
    const wrapperId = mount(UserManagementTab, {
      props: {
        users: sampleUsers,
        roles: sampleRoles,
      },
      global: {
        plugins: [i18n],
      },
    });
    const inputId = wrapperId.find('input[type="text"]');
    expect(inputId.attributes('placeholder')).toBe('Cari NPK, Nama, Departemen, atau Nomor Project...');
  });

  it('filters table by NPK, name, department, or project number, but ignores role text', async () => {
    const testUsers = [
      {
        id: 1,
        employee_id: '255578',
        employee_name: 'Radifa Azizi',
        display_name: '255578 - Radifa Azizi',
        department: 'Integrated Facility Services',
        manager_of: ['IFS-01'],
        role: 'admin_cfs',
      },
      {
        id: 2,
        employee_id: '053573',
        employee_name: 'Abdi Farid',
        display_name: '053573 - Abdi Farid',
        department: 'Information Communication Technology',
        manager_of: ['PRJ-NET-99'],
        role: 'admin_ict',
      },
      {
        id: 3,
        employee_id: '123456',
        employee_name: 'Regular Worker',
        display_name: '123456 - Regular Worker',
        department: 'Human Resources',
        manager_of: [],
        role: 'user',
      },
    ];

    setI18nLanguage('en');
    const wrapper = mount(UserManagementTab, {
      props: {
        users: testUsers,
        roles: sampleRoles,
      },
      global: {
        plugins: [i18n],
      },
    });

    const searchInput = wrapper.findComponent({ name: 'TableSearch' });

    // 1. Search by NPK '255578'
    await searchInput.vm.$emit('update:modelValue', '255578');
    expect(wrapper.text()).toContain('255578 - Radifa Azizi');
    expect(wrapper.text()).not.toContain('053573 - Abdi Farid');
    expect(wrapper.text()).not.toContain('123456 - Regular Worker');

    // 2. Search by Name 'Abdi'
    await searchInput.vm.$emit('update:modelValue', 'Abdi');
    expect(wrapper.text()).toContain('053573 - Abdi Farid');
    expect(wrapper.text()).not.toContain('255578 - Radifa Azizi');

    // 3. Search by Department 'Integrated Facility'
    await searchInput.vm.$emit('update:modelValue', 'Integrated Facility');
    expect(wrapper.text()).toContain('255578 - Radifa Azizi');
    expect(wrapper.text()).not.toContain('053573 - Abdi Farid');

    // 4. Search by Project Number 'PRJ-NET-99'
    await searchInput.vm.$emit('update:modelValue', 'PRJ-NET-99');
    expect(wrapper.text()).toContain('053573 - Abdi Farid');
    expect(wrapper.text()).not.toContain('255578 - Radifa Azizi');

    // 5. Searching by Role string 'admin_cfs' should match nothing (role is excluded from global search)
    await searchInput.vm.$emit('update:modelValue', 'admin_cfs');
    expect(wrapper.text()).not.toContain('255578 - Radifa Azizi');
    expect(wrapper.text()).not.toContain('053573 - Abdi Farid');
  });
});
