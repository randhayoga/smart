import { describe, it, expect, beforeEach } from 'vitest';
import { mount } from '@vue/test-utils';
import StockMovementRankingCard from '../StockMovementRankingCard.vue';
import type { RankingRow } from '../StockMovementRankingCard.vue';
import { i18n, setI18nLanguage } from '@/locales';

describe('StockMovementRankingCard.vue', () => {
  beforeEach(() => {
    setI18nLanguage('en');
  });

  const sampleRanking: RankingRow[] = [
    {
      category_name: 'Office Stationery',
      category_row_span: 2,
      type_name: 'A4 Paper',
      type_row_span: 2,
      model_name: 'HVS A4 80gr',
      model_row_span: 2,
      destination: 'Finance Department',
      request_count: 5,
      stock_out_qty: 120,
    },
    {
      category_name: 'Office Stationery',
      category_row_span: 0,
      type_name: 'A4 Paper',
      type_row_span: 0,
      model_name: 'HVS A4 80gr',
      model_row_span: 0,
      destination: 'Dieng Project',
      request_count: 2,
      stock_out_qty: 40,
    },
  ];

  it('renders the 6 table headers and card title', () => {
    const wrapper = mount(StockMovementRankingCard, {
      props: {
        ranking: sampleRanking,
      },
      global: {
        plugins: [i18n],
      },
    });

    expect(wrapper.text()).toContain('Stock Movement Ranking');
    const titleEl = wrapper.find('h2');
    expect(titleEl.exists()).toBe(true);
    expect(titleEl.classes()).toContain('text-lg');
    expect(titleEl.classes()).toContain('font-bold');

    const headers = wrapper.findAll('th');
    expect(headers).toHaveLength(6);
    expect(headers[0].classes()).toContain('h-10');
    expect(headers[0].classes()).toContain('text-sm');
    expect(headers[0].classes()).toContain('font-semibold');
    expect(headers[0].text()).toContain('Category');
    expect(headers[1].text()).toContain('Type');
    expect(headers[2].text()).toContain('Model');
    expect(headers[3].text()).toContain('Departements/Projects');
    expect(headers[4].text()).toContain('Request');
    expect(headers[5].text()).toContain('Stock Out');
  });

  it('renders row-spanned cells and omits merged cells on secondary rows', () => {
    const wrapper = mount(StockMovementRankingCard, {
      props: {
        ranking: sampleRanking,
      },
      global: {
        plugins: [i18n],
      },
    });

    const rows = wrapper.findAll('tbody tr');
    expect(rows).toHaveLength(2);

    // First row should have 6 cells (Category, Type, Model, Destination, Request, Stock Out)
    const firstRowCells = rows[0].findAll('td');
    expect(firstRowCells).toHaveLength(6);
    expect(firstRowCells[0].attributes('rowspan')).toBe('2');
    expect(firstRowCells[0].text()).toBe('Office Stationery');
    expect(firstRowCells[0].classes()).not.toContain('bg-muted/15');
    expect(firstRowCells[1].attributes('rowspan')).toBe('2');
    expect(firstRowCells[1].text()).toBe('A4 Paper');
    expect(firstRowCells[1].classes()).not.toContain('bg-muted/5');
    expect(firstRowCells[2].attributes('rowspan')).toBe('2');
    expect(firstRowCells[2].text()).toBe('HVS A4 80gr');
    expect(firstRowCells[3].text()).toBe('Finance Department');
    expect(firstRowCells[4].text()).toBe('5');
    expect(firstRowCells[4].classes()).not.toContain('font-mono');
    expect(firstRowCells[5].text()).toBe('120');
    expect(firstRowCells[5].classes()).toContain('text-rose-600');
    expect(firstRowCells[5].classes()).not.toContain('font-mono');

    // Second row should have only 3 cells because Category, Type, and Model are merged
    const secondRowCells = rows[1].findAll('td');
    expect(secondRowCells).toHaveLength(3);
    expect(secondRowCells[0].text()).toBe('Dieng Project');
    expect(secondRowCells[1].text()).toBe('2');
    expect(secondRowCells[2].text()).toBe('40');
  });

  it('displays empty state when ranking data is empty', () => {
    const wrapper = mount(StockMovementRankingCard, {
      props: {
        ranking: [],
      },
      global: {
        plugins: [i18n],
      },
    });

    expect(wrapper.text()).toContain('No stock movement ranking data available for the selected filter.');
  });
});
