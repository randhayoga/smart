import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import { i18n } from '@/locales';
import ConsumableMovementMetricCards from '../components/ConsumableMovementMetricCards.vue';

describe('ConsumableMovementMetricCards.vue', () => {
  it('renders 5 summary metrics cleanly from left to right', () => {
    const summary = {
      total_in: 150,
      total_out: 40,
      total_in_events: 12,
      total_out_events: 8,
      net_movement: 110,
      total_events: 20,
    };

    const wrapper = mount(ConsumableMovementMetricCards, {
      props: {
        summary,
        isLoading: false,
      },
      global: {
        plugins: [i18n],
      },
    });

    const text = wrapper.text();
    expect(text).toContain('Total Input');
    expect(text).toContain('Total Stock In');
    expect(text).toContain('Total Request');
    expect(text).toContain('Total Stock Out');
    expect(text).toContain('Net Stock Changes');

    expect(text).toContain('12');
    expect(text).toContain('150');
    expect(text).toContain('8');
    expect(text).toContain('40');
    expect(text).toContain('+110');

    // Verify subheadings and badges remain absent
    expect(text).not.toContain('New stock received');
    expect(text).not.toContain('Surplus');
    expect(text).not.toContain('Deficit');
  });

  it('renders skeleton loaders when isLoading is true', () => {
    const wrapper = mount(ConsumableMovementMetricCards, {
      props: {
        isLoading: true,
      },
      global: {
        plugins: [i18n],
      },
    });

    expect(wrapper.findAll('.animate-pulse').length).toBeGreaterThan(0);
  });
});
