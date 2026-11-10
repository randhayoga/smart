import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import { i18n } from '@/locales';
import ClassicDashboardTab from '../ClassicDashboardTab.vue';

describe('ClassicDashboardTab.vue', () => {
  it('renders classic inventory stats and cards correctly', () => {
    const consumableSubcategoryStats = [
      { subcategory_name: 'Kertas HVS', total_quantity: 150 },
    ];
    const cfsCategoryStats = [
      { category_name: 'Kursi Kantor', total_units: 42 },
    ];
    const ictCategoryStats = [
      { category_name: 'Laptop', total_units: 18 },
    ];

    const wrapper = mount(ClassicDashboardTab, {
      props: {
        consumableSubcategoryStats,
        cfsCategoryStats,
        ictCategoryStats,
      },
      global: {
        plugins: [i18n],
      },
    });

    const text = wrapper.text();
    expect(text).toContain('150');
    expect(text).toContain('42');
    expect(text).toContain('18');
  });
});
