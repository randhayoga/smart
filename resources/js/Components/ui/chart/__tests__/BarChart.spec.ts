import { describe, it, expect, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import BarChart from '../BarChart.vue';

// Mock all @unovis/vue components with prop capturing templates for happy-dom compatibility
vi.mock('@unovis/vue', () => ({
  VisXYContainer: {
    name: 'VisXYContainer',
    props: ['data', 'margin', 'height', 'xDomain', 'yDomain', 'preventEmptyDomain'],
    template: '<div data-testid="xy-container"><slot /></div>',
  },
  VisGroupedBar: {
    name: 'VisGroupedBar',
    props: ['x', 'y', 'color', 'roundedCorners', 'barPadding', 'groupPadding'],
    template: '<div data-testid="grouped-bar" />',
  },
  VisAxis: {
    name: 'VisAxis',
    props: ['type', 'tickValues', 'tickFormat', 'tickTextHideOverlapping', 'gridLine'],
    template: '<div data-testid="vis-axis" :data-type="type" />',
  },
  VisTooltip: {
    name: 'VisTooltip',
    props: ['horizontalShift', 'verticalShift', 'triggers'],
    template: '<div data-testid="vis-tooltip" />',
  },
  VisBulletLegend: {
    name: 'VisBulletLegend',
    props: ['items'],
    template: '<div data-testid="vis-bullet-legend" />',
  },
}));

describe('BarChart.vue', () => {
  const monthsData = [
    { label: 'Jan', in: 10, out: 5 },
    { label: 'Feb', in: 20, out: 10 },
    { label: 'Mar', in: 30, out: 15 },
    { label: 'Apr', in: 40, out: 20 },
    { label: 'May', in: 50, out: 25 },
    { label: 'Jun', in: 60, out: 30 },
    { label: 'Jul', in: 70, out: 35 },
    { label: 'Aug', in: 80, out: 40 },
    { label: 'Sep', in: 90, out: 45 },
    { label: 'Oct', in: 100, out: 50 },
    { label: 'Nov', in: 110, out: 55 },
    { label: 'Dec', in: 120, out: 60 },
  ];

  const daysData = Array.from({ length: 30 }, (_, i) => ({
    label: String(i + 1).padStart(2, '0'),
    in: (i + 1) * 2,
    out: i + 1,
  }));

  it('provides exact tick values for discrete datasets of 12 or fewer points', () => {
    const wrapper = mount(BarChart, {
      props: {
        data: monthsData,
        index: 'label',
        categories: ['in', 'out'],
      },
    });

    const axis = wrapper.findAllComponents({ name: 'VisAxis' });
    const xAxis = axis.find((a) => a.props('type') === 'x');
    expect(xAxis).toBeDefined();

    // Ticks must match all 12 indices exactly so every month has a tick mark
    expect(xAxis?.props('tickValues')).toEqual([0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11]);
    expect(xAxis?.props('tickTextHideOverlapping')).toBe(true);
  });

  it('provides exact tick values for monthly day datasets (up to 31 points)', () => {
    const wrapper = mount(BarChart, {
      props: {
        data: daysData,
        index: 'label',
        categories: ['in', 'out'],
      },
    });

    const axis = wrapper.findAllComponents({ name: 'VisAxis' });
    const xAxis = axis.find((a) => a.props('type') === 'x');
    expect(xAxis).toBeDefined();

    // Ticks must match all 30 days exactly so every day has a tick mark
    const expectedDays = Array.from({ length: 30 }, (_, i) => i);
    expect(xAxis?.props('tickValues')).toEqual(expectedDays);
    expect(xAxis?.props('tickTextHideOverlapping')).toBe(true);
  });

  it('leaves tickValues undefined for large datasets (>31) allowing Unovis automatic spacing', () => {
    const largeData = Array.from({ length: 50 }, (_, i) => ({
      label: String(i + 1),
      in: i,
      out: i,
    }));

    const wrapper = mount(BarChart, {
      props: {
        data: largeData,
        index: 'label',
        categories: ['in', 'out'],
      },
    });

    const axis = wrapper.findAllComponents({ name: 'VisAxis' });
    const xAxis = axis.find((a) => a.props('type') === 'x');
    expect(xAxis).toBeDefined();
    expect(xAxis?.props('tickValues')).toBeUndefined();
    expect(xAxis?.props('tickTextHideOverlapping')).toBe(true);
  });

  it('correctly formats tick labels using the numeric domain coordinate rather than the tick iteration index', () => {
    const wrapper = mount(BarChart, {
      props: {
        data: monthsData,
        index: 'label',
        categories: ['in', 'out'],
      },
    });

    const axis = wrapper.findAllComponents({ name: 'VisAxis' });
    const xAxis = axis.find((a) => a.props('type') === 'x');
    const tickFormat = xAxis?.props('tickFormat') as (tick: number, i: number, ticks: number[]) => string;
    expect(tickFormat).toBeTypeOf('function');

    // Simulated sampled ticks: [0, 2, 4, 6] (representing Jan, Mar, May, Jul)
    // In previous buggy version, tick 2 (i=1) returned props.data[1] ('Feb') below March!
    // With fix, tick 2 (i=1) must return props.data[2] ('Mar') directly below March.
    const sampledTicks = [0, 2, 4, 6];
    expect(tickFormat(0, 0, sampledTicks)).toBe('Jan');
    expect(tickFormat(2, 1, sampledTicks)).toBe('Mar');
    expect(tickFormat(4, 2, sampledTicks)).toBe('May');
    expect(tickFormat(6, 3, sampledTicks)).toBe('Jul');
  });

  it('correctly formats daily dates during sampled intervals without label drifting', () => {
    const wrapper = mount(BarChart, {
      props: {
        data: daysData,
        index: 'label',
        categories: ['in', 'out'],
      },
    });

    const axis = wrapper.findAllComponents({ name: 'VisAxis' });
    const xAxis = axis.find((a) => a.props('type') === 'x');
    const tickFormat = xAxis?.props('tickFormat') as (tick: number, i: number, ticks: number[]) => string;

    // Sampled ticks every 2 days: [0, 2, 4, 6, 28]
    // Ticks must match day 01, 03, 05, 07, 29 without offset
    expect(tickFormat(0, 0, [0, 2, 4])).toBe('01');
    expect(tickFormat(2, 1, [0, 2, 4])).toBe('03');
    expect(tickFormat(4, 2, [0, 2, 4])).toBe('05');
    expect(tickFormat(28, 14, [0, 2, 4])).toBe('29');
  });

  it('supplies [0, 10] yDomain when all data values are zero or empty', () => {
    const zeroData = [
      { label: 'Jan', in: 0, out: 0 },
      { label: 'Feb', in: 0, out: 0 },
      { label: 'Mar', in: 0, out: 0 },
    ];

    const wrapper = mount(BarChart, {
      props: {
        data: zeroData,
        index: 'label',
        categories: ['in', 'out'],
      },
    });

    const xyContainer = wrapper.findComponent({ name: 'VisXYContainer' });
    expect(xyContainer.exists()).toBe(true);
    expect(xyContainer.props('yDomain')).toEqual([0, 10]);
  });
});
