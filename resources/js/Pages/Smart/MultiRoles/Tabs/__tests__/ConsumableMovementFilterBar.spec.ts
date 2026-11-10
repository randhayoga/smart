import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import { i18n } from '@/locales';
import ConsumableMovementFilterBar from '../components/ConsumableMovementFilterBar.vue';

describe('ConsumableMovementFilterBar.vue', () => {
  const categories = [{ id: 1, code: 'ATK', name: 'Alat Tulis Kantor' }];
  const types = [{ id: 10, code: 'HVS', name: 'Kertas HVS', category_id: 1 }];
  const models = [{ id: 100, number: 'MDL-HVS-01', name: 'Kertas A4 Sidu', type_id: 10 }];
  const departments = [{ id: 5, name: 'Finance' }];
  const projects = [{ id: 25, name: 'Expansion Jakarta', no_project: 'PRJ-01' }];
  const availableDecades = [2020];
  const availableYears = [2026, 2025];

  it('renders filter labels and comboboxes', () => {
    const wrapper = mount(ConsumableMovementFilterBar, {
      props: {
        categories,
        types,
        models,
        departments,
        projects,
        availableDecades,
        availableYears,
        selectedDecade: '2020',
        selectedCategory: 'all',
        selectedType: 'all',
        selectedModel: 'all',
        selectedUtilization: 'all',
        selectedDepartment: 'all',
        selectedProject: 'all',
        selectedYear: '2026',
        selectedMonth: '11',
      },
      global: {
        plugins: [i18n],
      },
    });

    const text = wrapper.text();
    expect(text).toContain('Decade');
    expect(text).toContain('Category');
    expect(text).toContain('Type');
    expect(text).toContain('Model');
    expect(text).toContain('Utilization');
    expect(text).toContain('Year');
    expect(text).toContain('Month');
  });

  it('dynamically displays department filter when utilization is corporate', async () => {
    const wrapper = mount(ConsumableMovementFilterBar, {
      props: {
        categories,
        types,
        models,
        departments,
        projects,
        availableDecades,
        availableYears,
        selectedDecade: '2020',
        selectedCategory: 'all',
        selectedType: 'all',
        selectedModel: 'all',
        selectedUtilization: 'corporate',
        selectedDepartment: 'all',
        selectedProject: 'all',
        selectedYear: '2026',
        selectedMonth: '11',
      },
      global: {
        plugins: [i18n],
      },
    });

    expect(wrapper.text()).toContain('Department');
    expect(wrapper.text()).not.toContain('Project');
  });

  it('dynamically displays project filter when utilization is project', async () => {
    const wrapper = mount(ConsumableMovementFilterBar, {
      props: {
        categories,
        types,
        models,
        departments,
        projects,
        availableDecades,
        availableYears,
        selectedDecade: '2020',
        selectedCategory: 'all',
        selectedType: 'all',
        selectedModel: 'all',
        selectedUtilization: 'project',
        selectedDepartment: 'all',
        selectedProject: 'all',
        selectedYear: '2026',
        selectedMonth: '11',
      },
      global: {
        plugins: [i18n],
      },
    });

    expect(wrapper.text()).toContain('Project');
    expect(wrapper.text()).not.toContain('Department');
  });

  it('shows reset button when year is active and hides when all filters are all/default', async () => {
    const wrapper = mount(ConsumableMovementFilterBar, {
      props: {
        categories,
        types,
        models,
        departments,
        projects,
        availableDecades,
        availableYears,
        selectedDecade: '2020',
        selectedCategory: 'all',
        selectedType: 'all',
        selectedModel: 'all',
        selectedUtilization: 'all',
        selectedDepartment: 'all',
        selectedProject: 'all',
        selectedYear: '2026',
        selectedMonth: 'all',
      },
      global: {
        plugins: [i18n],
      },
    });

    expect(wrapper.findComponent({ name: 'ResetFilterButton' }).exists()).toBe(true);

    await wrapper.setProps({
      selectedYear: '',
      selectedMonth: 'all',
    });

    expect(wrapper.findComponent({ name: 'ResetFilterButton' }).exists()).toBe(false);
  });
});
