import { describe, it, expect, beforeEach, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import InputNewStockModal from '../InputNewStockModal.vue';
import ModelSection from '../InputNewStock/ModelSection.vue';
import LotSection from '../InputNewStock/LotSection.vue';
import AssetSection from '../InputNewStock/AssetSection.vue';
import Combobox from '@/Components/Combobox.vue';
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
      { id: 50, number: 'COMP-NB-0001', name: 'Latitude 5420', specification: 'i7 16GB 512GB', brand_id: 100, type_id: 10, category_id: 1, is_consumable: false },
      { id: 51, number: 'COMP-NB-0002', name: 'OptiPlex 7090', specification: null, brand_id: 100, type_id: 10, category_id: 1, is_consumable: false },
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
    vendors: [
      { id: 1, name: 'PT Vendor Utama' },
      { id: 2, name: 'Beragam' },
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
            props: ['modelValue', 'disabled', 'options', 'placeholder'],
            template: '<div data-testid="combobox" :data-disabled="disabled" :data-placeholder="placeholder">{{ modelValue }}</div>',
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
    expect(wrapper.text()).toContain('Buat model baru');
  });

  it('renders cascading initial fields for category, type, and brand', () => {
    const wrapper = mountModal();
    expect(wrapper.text()).toContain('Category');
    expect(wrapper.text()).toContain('Type');
    expect(wrapper.text()).toContain('Brand');
    expect(wrapper.text()).toContain('Choose existing model');
    expect(wrapper.text()).toContain('Create new model');
  });

  it('renders price field with "Price" in English and "Harga" in Indonesian when asset section is active', async () => {
    // English
    setI18nLanguage('en');
    const wrapperEn = mountModal();
    await wrapperEn.find('#input-lot-check').trigger('click');
    await wrapperEn.find('#create-new-lot-check').trigger('click');
    await wrapperEn.find('#input-asset-check').trigger('click');
    expect(wrapperEn.find('#new-asset-price').exists()).toBe(true);
    expect(wrapperEn.text()).toContain('Price');
    expect(wrapperEn.text()).not.toContain('inventory.price');

    // Indonesian
    setI18nLanguage('id');
    const wrapperId = mountModal();
    await wrapperId.find('#input-lot-check').trigger('click');
    await wrapperId.find('#create-new-lot-check').trigger('click');
    await wrapperId.find('#input-asset-check').trigger('click');
    expect(wrapperId.find('#new-asset-price').exists()).toBe(true);
    expect(wrapperId.text()).toContain('Harga');
    expect(wrapperId.text()).not.toContain('inventory.price');
  });

  it('toggles dynamic new model fields when "Create new model" is checked', async () => {
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

  it('renders auto-create checkbox for non-vehicle category when asset section is active', async () => {
    // English
    setI18nLanguage('en');
    const wrapperEn = mountModal();
    await wrapperEn.find('#input-lot-check').trigger('click');
    await wrapperEn.find('#create-new-lot-check').trigger('click');
    await wrapperEn.find('#input-asset-check').trigger('click');

    const autoCreateCheckbox = wrapperEn.find('#auto-create-checkbox');
    expect(autoCreateCheckbox.exists()).toBe(true);
    expect(wrapperEn.text()).toContain('Create');
    expect(wrapperEn.text()).toContain('assets automatically with these data');

    // Indonesian
    setI18nLanguage('id');
    const wrapperId = mountModal();
    await wrapperId.find('#input-lot-check').trigger('click');
    await wrapperId.find('#create-new-lot-check').trigger('click');
    await wrapperId.find('#input-asset-check').trigger('click');

    expect(wrapperId.find('#auto-create-checkbox').exists()).toBe(true);
    expect(wrapperId.text()).toContain('Buat');
    expect(wrapperId.text()).toContain('aset secara otomatis dengan data yang sama');
  });

  it('hides auto-create checkbox for vehicle category in AssetSection', () => {
    const form = {
      input_asset: true,
      price: '',
      classification: 'Aset',
      condition: 'Bagus',
      borrowing_type: 'LT',
      location_id: 1,
      asset_specification: '',
      photo: null,
      photo_name: '',
      plate_number: '',
      is_bulk: false,
      bulk_quantity: '',
    };

    const wrapperKend = mount(AssetSection, {
      props: {
        form,
        errors: {},
        locations: [],
        isComp: false,
        isKend: true,
        lotReady: true,
      },
      global: { plugins: [i18n] },
    });
    expect(wrapperKend.find('#auto-create-checkbox').exists()).toBe(false);

    const wrapperNonKend = mount(AssetSection, {
      props: {
        form,
        errors: {},
        locations: [],
        isComp: false,
        isKend: false,
        lotReady: true,
      },
      global: { plugins: [i18n] },
    });
    expect(wrapperNonKend.find('#auto-create-checkbox').exists()).toBe(true);
  });

  it('validates bulk_quantity when auto-create is checked but quantity is empty', async () => {
    setI18nLanguage('en');
    const wrapper = mountModal();
    await wrapper.find('#input-lot-check').trigger('click');
    await wrapper.find('#create-new-lot-check').trigger('click');
    await wrapper.find('#input-asset-check').trigger('click');

    const checkbox = wrapper.find('#auto-create-checkbox');
    expect(checkbox.exists()).toBe(true);

    // Quantity input is disabled before checking
    const qtyInput = wrapper.find('input[type="number"][placeholder="..."]');
    expect(qtyInput.exists()).toBe(true);
    expect(qtyInput.attributes('disabled')).toBeDefined();

    // Check auto-create
    await checkbox.trigger('click');
    expect(qtyInput.attributes('disabled')).toBeUndefined();

    // Submit form with empty quantity
    const submitBtn = wrapper.findAll('button').find(b => b.text().includes('Input New Stock') && !b.text().includes('Cancel'));
    await submitBtn?.trigger('click');

    expect(wrapper.text()).toContain('Asset count is required');
  });

  it('shows vendor combobox when entry_type is Procurement and hides it when Hibah', async () => {
    setI18nLanguage('en');
    const wrapper = mountModal();
    await wrapper.find('#input-lot-check').trigger('click');
    await wrapper.find('#create-new-lot-check').trigger('click');

    // Default entry_type is Procurement -> Vendor label / combobox is present
    expect(wrapper.text()).toContain('Vendor');
    expect(wrapper.find('#new-lot-vendor').exists()).toBe(true);

    // Switch entry_type to Hibah via the dropdown item
    const hibahItem = wrapper.findAll('[role="menuitem"]').find(item => item.text().trim() === 'Hibah');
    await hibahItem?.trigger('click');

    expect(wrapper.find('#new-lot-vendor').exists()).toBe(false);
  });

  it('resets form state when modal closes and reopens', async () => {
    const wrapper = mountModal();
    await wrapper.find('#input-lot-check').trigger('click');
    expect(wrapper.vm.form.input_lot).toBe(true);

    // Close modal via prop update
    await wrapper.setProps({ open: false });
    expect(wrapper.vm.form.input_lot).toBe(false);
    expect(wrapper.vm.form.category_id).toBeNull();
    expect(wrapper.vm.form.vendor_id).toBeNull();
  });

  it('formats "Choose existing model" as "Name - Specification" without model number, or simply "Name" when spec is null', async () => {
    const wrapper = mount(ModelSection, {
      props: {
        form: {
          category_id: 1,
          type_id: 10,
          brand_id: 100,
          model_id: null,
          create_new_type: false,
        },
        errors: {},
        categories: sampleProps.categories,
        types: sampleProps.types,
        brands: sampleProps.brands,
        uoms: sampleProps.uoms,
        models: sampleProps.models,
      },
      global: {
        plugins: [i18n],
      },
    });

    const comboboxes = wrapper.findAllComponents(Combobox);
    const modelCombobox = comboboxes[3];
    expect(modelCombobox.exists()).toBe(true);

    const options = modelCombobox.props('options');
    expect(options).toEqual([
      { id: 50, name: 'Latitude 5420 - i7 16GB 512GB' },
      { id: 51, name: 'OptiPlex 7090' },
    ]);
  });

  it('provides all brands even when create_new_type is unchecked', () => {
    const wrapper = mount(ModelSection, {
      props: {
        form: {
          category_id: 1,
          type_id: 10,
          brand_id: null,
          model_id: null,
          create_new_type: false, // unchecked
        },
        errors: {},
        categories: sampleProps.categories,
        types: sampleProps.types,
        brands: [
          { id: 100, name: 'Dell' },
          { id: 200, name: 'Toyota' },
          { id: 300, name: 'Apple' }, // Not in existing models for type 10
        ],
        uoms: sampleProps.uoms,
        models: [
          { id: 50, brand_id: 100, type_id: 10, name: 'Latitude 5420' },
        ],
      },
      global: {
        plugins: [i18n],
      },
    });

    const comboboxes = wrapper.findAllComponents(Combobox);
    const brandCombobox = comboboxes[2];
    expect(brandCombobox.props('options')).toEqual([
      { id: 100, name: 'Dell' },
      { id: 200, name: 'Toyota' },
      { id: 300, name: 'Apple' },
    ]);
  });

  it('renders total stock and photo inputs when creating a new LOT for a consumable item', async () => {
    setI18nLanguage('en');
    const wrapper = mountModal();

    wrapper.vm.form.category_id = 3;
    wrapper.vm.form.type_id = 30; // consumable kertas
    wrapper.vm.form.brand_id = 100;
    wrapper.vm.form.create_new_type = true;
    wrapper.vm.form.model_name = 'Kertas A4';
    wrapper.vm.form.uom_id = 2000;
    wrapper.vm.form.min_stock_threshold = 10;
    wrapper.vm.form.input_lot = true;
    wrapper.vm.form.create_new_lot = true;
    await wrapper.vm.$nextTick();

    // Total Stock input should exist and display the UOM
    const stockInput = wrapper.find('#new-lot-total-stock');
    expect(stockInput.exists()).toBe(true);
    expect(wrapper.text()).toContain('Total Stock');
    expect(wrapper.text()).toContain('Rim');

    // Photo input should exist
    expect(wrapper.text()).toContain('Photo');
    expect(wrapper.text()).toContain('Choose File');
  });

  it('hides total stock and photo in LotSection when item is non-consumable', async () => {
    setI18nLanguage('en');
    const wrapper = mountModal();

    wrapper.vm.form.category_id = 1;
    wrapper.vm.form.type_id = 10; // non-consumable notebook
    wrapper.vm.form.brand_id = 100;
    wrapper.vm.form.input_lot = true;
    wrapper.vm.form.create_new_lot = true;
    await wrapper.vm.$nextTick();

    // Total Stock input should NOT exist in LotSection for non-consumable
    expect(wrapper.find('#new-lot-total-stock').exists()).toBe(false);
  });

  it('validates total stock when creating a new lot for a consumable item', async () => {
    setI18nLanguage('en');
    const wrapper = mountModal();

    wrapper.vm.form.category_id = 3;
    wrapper.vm.form.type_id = 30; // consumable kertas
    wrapper.vm.form.brand_id = 100;
    wrapper.vm.form.create_new_type = true;
    wrapper.vm.form.model_name = 'Kertas A4';
    wrapper.vm.form.uom_id = 2000;
    wrapper.vm.form.min_stock_threshold = 10;
    wrapper.vm.form.input_lot = true;
    wrapper.vm.form.create_new_lot = true;
    wrapper.vm.form.entry_type = 'Procurement';
    wrapper.vm.form.vendor_id = 1;
    wrapper.vm.form.po_number = 'PO-01';
    wrapper.vm.form.purchase_date = '2026-10-16';
    wrapper.vm.form.organizer_id = 1;
    wrapper.vm.form.burden = 'Corporate';
    wrapper.vm.form.initial_quantity = ''; // empty!
    await wrapper.vm.$nextTick();

    const submitBtn = wrapper.findAll('button').find(b => b.text().includes('Input New Stock') && !b.text().includes('Cancel'));
    await submitBtn?.trigger('click');

    expect(wrapper.text()).toContain('Stock quantity is required');
  });

  it('formats project options as "[<number>] <name>" and excludes projects without number or name in LotSection', () => {
    setI18nLanguage('en');
    const wrapper = mount(LotSection, {
      props: {
        form: {
          input_lot: true,
          create_new_lot: true,
          burden: 'Project',
          project_id: null,
        },
        errors: {},
        lots: [],
        organizers: [],
        vendors: [],
        locations: [],
        projects: [
          { id_project: 99, project_name: 'Smart Implementation', no_project: 'PRJ-01' },
          { id_project: 100, project_name: 'Internal Overhaul' }, // missing no_project
          { id_project: 101, project_name: '', no_project: 'PRJ-EMPTY-NAME' }, // empty name
          { id_project: 102, project_name: 'Empty Number', no_project: '' }, // empty number
          { id_project: 103, project_name: 'Enterprise Setup', no_project: 'PRJ-02' },
        ],
        activeModelId: 50,
        isConsumable: true,
      },
      global: {
        plugins: [i18n],
      },
    });

    const comboboxes = wrapper.findAllComponents(Combobox);
    const projectCombobox = comboboxes.find(c => c.props('placeholder') === 'Select project');
    expect(projectCombobox?.props('options')).toEqual([
      { id: 99, name: '[PRJ-01] Smart Implementation' },
      { id: 103, name: '[PRJ-02] Enterprise Setup' },
    ]);
  });

  it('hides burden and project selection in LotSection when isConsumable is false', () => {
    setI18nLanguage('en');
    const wrapper = mount(LotSection, {
      props: {
        form: {
          input_lot: true,
          create_new_lot: true,
          burden: 'Corporate',
          project_id: null,
        },
        errors: {},
        lots: [],
        organizers: [],
        vendors: [],
        locations: [],
        projects: [{ id_project: 1, project_name: 'PRJ', no_project: 'P1' }],
        activeModelId: 50,
        isConsumable: false,
      },
      global: {
        plugins: [i18n],
      },
    });

    expect(wrapper.text()).not.toContain('Burden');
    expect(wrapper.text()).not.toContain('Corporate');
  });

  it('renders burden and project options in AssetSection between classification and photo', () => {
    setI18nLanguage('en');
    const wrapper = mount(AssetSection, {
      props: {
        form: {
          input_asset: true,
          classification: 'Aset',
          burden: 'Project',
          project_id: null,
          photo: null,
        },
        errors: {},
        locations: [],
        projects: [
          { id_project: 10, project_name: 'Asset Project', no_project: 'PRJ-A' },
        ],
        isComp: false,
        isKend: false,
        lotReady: true,
      },
      global: {
        plugins: [i18n],
      },
    });

    expect(wrapper.text()).toContain('Burden');
    expect(wrapper.text()).toContain('Project');

    const projectCombobox = wrapper.findComponent(Combobox);
    expect(projectCombobox.exists()).toBe(true);
    expect(projectCombobox.props('options')).toEqual([
      { id: 10, name: '[PRJ-A] Asset Project' },
    ]);
  });

  it('completely hides AssetSection and prevents asset input when consumable type is active', async () => {
    setI18nLanguage('en');
    const wrapper = mountModal();

    // Select consumable category ATK (id 3) and type (id 30, is_consumable: true)
    const modelSection = wrapper.findComponent(ModelSection);
    modelSection.vm.form.category_id = 3;
    modelSection.vm.form.type_id = 30;
    await wrapper.vm.$nextTick();

    // Check Input LOT and create new lot
    await wrapper.find('#input-lot-check').trigger('click');
    await wrapper.find('#create-new-lot-check').trigger('click');
    await wrapper.vm.$nextTick();

    // Verify AssetSection is NOT rendered at all
    expect(wrapper.find('#input-asset-check').exists()).toBe(false);
    expect(wrapper.findComponent(AssetSection).exists()).toBe(false);
    expect(wrapper.text()).not.toContain('Input asset with LOT above');

    // Verify LotSection displays consumable fields (Total Stock, Unit Price, Burden)
    expect(wrapper.text()).toContain('Total Stock');
    expect(wrapper.text()).toContain('Unit Price');
    expect(wrapper.text()).toContain('Burden');
  });

  it('resets form.input_asset and removes AssetSection when switching from non-consumable to consumable', async () => {
    setI18nLanguage('en');
    const wrapper = mountModal();

    // Start with non-consumable (category 1, type 10)
    const modelSection = wrapper.findComponent(ModelSection);
    modelSection.vm.form.category_id = 1;
    modelSection.vm.form.type_id = 10;
    await wrapper.vm.$nextTick();

    // Activate LOT and Asset
    await wrapper.find('#input-lot-check').trigger('click');
    await wrapper.find('#create-new-lot-check').trigger('click');
    await wrapper.find('#input-asset-check').trigger('click');
    await wrapper.vm.$nextTick();

    // Verify asset section is present
    expect(wrapper.find('#input-asset-check').exists()).toBe(true);
    expect(wrapper.find('#new-asset-price').exists()).toBe(true);
    expect(wrapper.vm.form.input_asset).toBe(true);

    // Switch type to consumable (type 30)
    modelSection.vm.form.type_id = 30;
    await wrapper.vm.$nextTick();

    // Verify AssetSection is immediately removed and form.input_asset is forced to false
    expect(wrapper.find('#input-asset-check').exists()).toBe(false);
    expect(wrapper.findComponent(AssetSection).exists()).toBe(false);
    expect(wrapper.vm.form.input_asset).toBe(false);
  });
});
