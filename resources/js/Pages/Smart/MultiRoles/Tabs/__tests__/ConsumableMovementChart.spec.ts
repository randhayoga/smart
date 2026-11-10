import { describe, it, expect, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import { i18n } from '@/locales';
import ConsumableMovementChart from '../components/ConsumableMovementChart.vue';

vi.mock('@/Components/ui/chart', () => ({
  BarChart: {
    name: 'BarChart',
    props: ['data', 'index', 'categories', 'categoryLabels', 'colors', 'yFormatter', 'height'],
    template: '<div data-testid="bar-chart" :data-count="data?.length || 0" />',
  },
}));

describe('ConsumableMovementChart.vue', () => {
  it('renders BarChart when all trend values are zero without displaying an empty card', () => {
    const zeroTrends = [
      { period: 2020, label: '2020', total_in: 0, total_out: 0 },
      { period: 2021, label: '2021', total_in: 0, total_out: 0 },
    ];

    const wrapper = mount(ConsumableMovementChart, {
      props: {
        trends: zeroTrends,
        isLoading: false,
      },
      global: {
        plugins: [i18n],
      },
    });

    const chart = wrapper.find('[data-testid="bar-chart"]');
    expect(chart.exists()).toBe(true);
    expect(chart.attributes('data-count')).toBe('2');

    // Must NOT contain empty card placeholder text
    expect(wrapper.text()).not.toContain('No stock movement records found');
    expect(wrapper.text()).not.toContain('Tidak ada riwayat pergerakan stok');
  });

  it('renders fallback 12-month series when trends is empty or omitted', () => {
    const wrapper = mount(ConsumableMovementChart, {
      props: {
        trends: [],
        isLoading: false,
      },
      global: {
        plugins: [i18n],
      },
    });

    const chart = wrapper.find('[data-testid="bar-chart"]');
    expect(chart.exists()).toBe(true);
    expect(chart.attributes('data-count')).toBe('12');
  });

  it('renders skeleton loading elements when isLoading is true', () => {
    const wrapper = mount(ConsumableMovementChart, {
      props: {
        trends: [],
        isLoading: true,
      },
      global: {
        plugins: [i18n],
      },
    });

    expect(wrapper.find('[data-testid="bar-chart"]').exists()).toBe(false);
    expect(wrapper.findAllComponents({ name: 'Skeleton' }).length).toBeGreaterThan(0);
  });
});
