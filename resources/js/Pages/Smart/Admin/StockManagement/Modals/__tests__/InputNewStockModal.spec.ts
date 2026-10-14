import { describe, it, expect, beforeEach, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import InputNewStockModal from '../InputNewStockModal.vue';
import { i18n, setI18nLanguage } from '@/locales';

describe('InputNewStockModal.vue', () => {
  beforeEach(() => {
    setI18nLanguage('en');
  });

  const sampleProps = {
    open: true,
    categories: [
      { id: 1, code: 'COMP', name: 'Computer' },
      { id: 2, code: 'KEND', name: 'Kendaraan' },
      { id: 3, code: 'ATK', name: 'Office Supplies' },
    ],
    types: [
      { id: 10, code: 'COMP-NB', name: 'Notebook', category_id: 1, is_consumable: false },
      { id: 20, code: 'KEND-MOB', name: 'Mobil', category_id: 2, is_consumable: false },
      { id: 30, code: 'ATK-KTS', name: 'Kertas', category_id: 3, is_consumable: true },
    ],
    brands: [
      { id: 100, name: 'Dell' },
      { id: 200, name: 'Toyota' },
    ],
    uoms: [
      { id: 1000, name: 'Unit' },
      { id: 2000, name: 'Rim' },
    ],
    models: [
      { id: 50, number: 'COMP-NB-0001', name: 'Latitude 5420', brand_id: 100, type_id: 10, category_id: 1, is_consumable: false },
    ],
    lots: [
      { id: 500, number: 'LOT-0001-26-COMP-NB-0001', model_id: 50, po_number: 'PO-001' },
    ],
    organizers: [
      { id: 1, name: 'CFS' },
      { id: 10001, name: 'IFS' },
    ],
    locations: [
      { id: 1, name: 'Head Office - Room 101', full_name: 'HO / Room 101' },
    ],
    projects: [
      { id_project: 99, project_name: 'Smart Implementation', no_project: 'PRJ-01' },
    ],
  };

  const mountModal = (customProps: any = {}) => {
    return mount(InputNewStockModal, {
      props: {
        ...sampleProps,
        ...customProps,
      },
      global: {
        plugins: [i18n],
        stubs: {
          Teleport: true,
          Transition: false,
          LocationCombobox: {
            props: ['modelValue'],
            template: '<div data-testid="location-combobox">{{ modelValue }}</div>',
          },
          Combobox: {
            props: ['modelValue', 'disabled', 'options'],
            template: '<div data-testid="combobox" :data-disabled="disabled">{{ modelValue }}</div>',
          },
          DropdownMenu: {
            template: '<div><slot /></div>',
          },
          DropdownMenuTrigger: {
            template: '<div><slot /></div>',
          },
          DropdownMenuContent: {
            template: '<div><slot /></div>',
          },
          DropdownMenuItem: {
            template: '<div role="menuitem" @click="$emit(\'select\')"><slot /></div>',
            emits: ['select'],
          },
        },
      },
    });
  };

  it('renders modal header with "Input New Stock"', () => {
    const wrapper = mountModal();
    expect(wrapper.text()).toContain('Input New Stock');
  });

  it('renders localized modal header in Indonesian', async () => {
    setI18nLanguage('id');
    const wrapper = mountModal();
    expect(wrapper.text()).toContain('Input Stok Baru');
  });

  it('renders cascading initial fields for category, type, and brand', () => {
    const wrapper = mountModal();
    expect(wrapper.text()).toContain('Choose category');
    expect(wrapper.text()).toContain('Choose type');
    expect(wrapper.text()).toContain('Choose brand');
    expect(wrapper.text()).toContain('Choose existing model');
    expect(wrapper.text()).toContain('Create new type with these data above');
  });

  it('toggles dynamic new type fields when "Create new type with these data above" is checked', async () => {
    const wrapper = mountModal();

    // Before checking, model name field should not exist
    expect(wrapper.find('#new-model-name').exists()).toBe(false);

    // Find and check create new type checkbox
    const checkbox = wrapper.find('#create-new-type-check');
    expect(checkbox.exists()).toBe(true);
    await checkbox.trigger('click');

    // After checking, model name field should exist
    expect(wrapper.find('#new-model-name').exists()).toBe(true);
  });

  it('emits update:open false when cancel button is clicked', async () => {
    const wrapper = mountModal();
    const cancelButton = wrapper.findAll('button').find(b => b.text().includes('Cancel'));
    expect(cancelButton).toBeDefined();
    await cancelButton?.trigger('click');

    expect(wrapper.emitted('update:open')).toBeTruthy();
    expect(wrapper.emitted('update:open')?.[0]).toEqual([false]);
  });
});
