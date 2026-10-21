import { describe, it, expect, beforeEach, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import EditModelModal from '../EditModelModal.vue';
import { i18n, setI18nLanguage } from '@/locales';

const mockPost = vi.fn();
let currentFormState: any = {};

vi.mock('@inertiajs/vue3', async (importOriginal) => {
  const actual: any = await importOriginal();
  return {
    ...actual,
    router: {
      post: vi.fn(),
    },
    useForm: (initialData: any) => {
      currentFormState = {
        ...initialData,
        errors: {},
        processing: false,
        transform: vi.fn((fn: any) => ({
          post: (url: string, options: any) => {
            mockPost(url, fn(currentFormState), options);
          },
        })),
        post: vi.fn(),
        reset: vi.fn(),
        clearErrors: vi.fn(),
      };
      return currentFormState;
    },
  };
});

describe('EditModelModal.vue', () => {
  beforeEach(() => {
    setI18nLanguage('id');
    vi.clearAllMocks();
  });

  const sampleUoms = [
    { id: 1, name: 'Unit' },
    { id: 2, name: 'Pcs' },
  ];

  const sampleBrands = [
    { id: 10, name: 'Dell' },
    { id: 20, name: 'Logitech' },
  ];

  const sampleSingleItem = {
    id: 101,
    code: 'MDL-001',
    category: 'Elektronik',
    subcategory: 'Keyboard',
    brand_id: 20,
    uom_id: 1,
    name: 'MK220',
    specification: 'Wireless Combo',
    is_consumable: false,
  };

  const mountModal = (props: {
    open: boolean;
    items: any[];
    uoms?: any[];
    brands?: any[];
    lots?: any[];
  }) => {
    // Mount initial wrapper with open=false then update to open=true to trigger watch(props.open)
    const wrapper = mount(EditModelModal, {
      props: {
        uoms: sampleUoms,
        brands: sampleBrands,
        ...props,
        open: false,
      },
      global: {
        plugins: [i18n],
        stubs: {
          Teleport: true,
          Transition: false,
          Combobox: {
            props: ['modelValue', 'options', 'defaultLabel', 'searchPlaceholder', 'error', 'widthClass'],
            template: '<div class="stubbed-combobox" :data-value="modelValue">{{ defaultLabel }}</div>',
          },
        },
      },
    });

    if (props.open) {
      wrapper.setProps({ open: true });
    }
    return wrapper;
  };

  it('renders modal with single model item and populates initial values', async () => {
    const wrapper = mountModal({ open: true, items: [sampleSingleItem] });
    await wrapper.vm.$nextTick();

    expect(wrapper.text()).toContain('Edit Detail Model');

    const inputs = wrapper.findAll('input');
    const inputValues = inputs.map(i => (i.element as HTMLInputElement).value);

    // Disabled classification fields
    expect(inputValues).toContain('MDL-001');
    expect(inputValues).toContain('Elektronik');
    expect(inputValues).toContain('Keyboard');

    // Editable name and spec
    expect(inputValues).toContain('MK220');
    expect(inputValues).toContain('Wireless Combo');
  });

  it('does NOT render photo upload input, file selector, or photo delete button', async () => {
    const wrapper = mountModal({ open: true, items: [sampleSingleItem] });
    await wrapper.vm.$nextTick();

    // File input shouldn't exist
    expect(wrapper.find('input[type="file"]').exists()).toBe(false);
    expect(wrapper.find('#edit-tipe-photo-upload').exists()).toBe(false);

    // No photo labels or deletion icons
    expect(wrapper.text()).not.toContain('Foto default');
    expect(wrapper.text()).not.toContain('Default photo');
    expect(wrapper.find('button[title="Hapus Foto"]').exists()).toBe(false);
  });

  it('places the unit (UoM) combobox in the second column alongside brand and name', async () => {
    const wrapper = mountModal({ open: true, items: [sampleSingleItem] });
    await wrapper.vm.$nextTick();

    const columns = wrapper.findAll('.space-y-6');
    expect(columns.length).toBeGreaterThanOrEqual(2);

    const leftColumn = columns[0];
    const rightColumn = columns[1];

    // Left column contains only the 3 classification items
    expect(leftColumn.text()).toContain('Nomor Model');
    expect(leftColumn.text()).toContain('Kategori');
    expect(leftColumn.text()).toContain('Jenis');
    expect(leftColumn.find('.stubbed-combobox').exists()).toBe(false);

    // Right column contains Brand, Type Name, and UoM
    expect(rightColumn.text()).toContain('Merek');
    expect(rightColumn.text()).toContain('Nama Model');
    expect(rightColumn.text()).toContain('Satuan');
    expect(rightColumn.findAll('.stubbed-combobox').length).toBe(2); // Brand combobox + UoM combobox
  });

  it('submits update payload without any photo-related fields', async () => {
    const wrapper = mountModal({ open: true, items: [sampleSingleItem] });
    await wrapper.vm.$nextTick();

    currentFormState.uom_id = 1;
    currentFormState.brand_id = 20;
    currentFormState.name = 'MK220 Updated';
    currentFormState.specification = 'Updated Spec';

    const saveButton = wrapper.findAll('button').find(b => b.text().includes('Simpan Perubahan'));
    expect(saveButton).toBeDefined();
    await saveButton!.trigger('click');

    expect(mockPost).toHaveBeenCalledTimes(1);
    const [url, payload] = mockPost.mock.calls[0];

    expect(url).toBe('/smart/stock-management/models/101');
    expect(payload).toMatchObject({
      _method: 'PUT',
      uom_id: 1,
      brand_id: 20,
      name: 'MK220 Updated',
      specification: 'Updated Spec',
    });
    expect(payload).not.toHaveProperty('image_url');
    expect(payload).not.toHaveProperty('photo');
    expect(payload).not.toHaveProperty('delete_image');
  });

  it('handles bulk edit mode without photo fields in payload', async () => {
    const item1 = { ...sampleSingleItem, id: 101 };
    const item2 = { ...sampleSingleItem, id: 102, code: 'MDL-002' };

    const wrapper = mountModal({ open: true, items: [item1, item2] });
    await wrapper.vm.$nextTick();
    expect(wrapper.text()).toContain('Edit Model Terpilih');

    currentFormState.uom_id = 2;
    currentFormState.name = 'Bulk Name';

    const saveButton = wrapper.findAll('button').find(b => b.text().includes('Simpan Perubahan Massal'));
    expect(saveButton).toBeDefined();
    await saveButton!.trigger('click');

    expect(mockPost).toHaveBeenCalledTimes(1);
    const [url, payload] = mockPost.mock.calls[0];

    expect(url).toBe('/smart/stock-management/models/bulk');
    expect(payload).toMatchObject({
      _method: 'PUT',
      ids: [101, 102],
      uom_id: 2,
      name: 'Bulk Name',
    });
    expect(payload).not.toHaveProperty('image_url');
    expect(payload).not.toHaveProperty('photo');
  });

  it('blocks specification input as "Cannot be changed" in mass edit if at least one selected model is COMP', async () => {
    const compItem = {
      id: 201,
      code: 'MDL-COMP-1',
      category: 'Computer',
      category_code: 'COMP',
      subcategory: 'Laptop',
      brand_id: 10,
      uom_id: 1,
      name: 'ThinkPad T14',
      specification: '',
      is_consumable: false,
    };
    const nonCompItem = { ...sampleSingleItem, id: 202 };

    const wrapper = mountModal({ open: true, items: [compItem, nonCompItem] });
    await wrapper.vm.$nextTick();

    // Specification input should be disabled with "Tidak dapat diubah"
    const specInput = wrapper.findAll('input').find(i => (i.element as HTMLInputElement).value === 'Tidak dapat diubah');
    expect(specInput).toBeDefined();
    expect((specInput!.element as HTMLInputElement).disabled).toBe(true);

    currentFormState.uom_id = 2;
    currentFormState.specification = 'Should Not Be Sent';

    const saveButton = wrapper.findAll('button').find(b => b.text().includes('Simpan Perubahan Massal'));
    await saveButton!.trigger('click');

    expect(mockPost).toHaveBeenCalledTimes(1);
    const [, payload] = mockPost.mock.calls[0];
    expect(payload.uom_id).toBe(2);
    expect(payload).not.toHaveProperty('specification');
  });

  it('allows editable specification input in mass edit if none of the models are COMP', async () => {
    const nonComp1 = { ...sampleSingleItem, id: 301, category_code: 'FURN', category: 'Furniture' };
    const nonComp2 = { ...sampleSingleItem, id: 302, category_code: 'FURN', category: 'Furniture' };

    const wrapper = mountModal({ open: true, items: [nonComp1, nonComp2] });
    await wrapper.vm.$nextTick();

    const specInput = wrapper.findAll('input').find(i => (i.element as HTMLInputElement).placeholder.toLowerCase() === 'tidak berubah');
    expect(specInput).toBeDefined();
    expect((specInput!.element as HTMLInputElement).disabled).toBe(false);

    currentFormState.uom_id = 1;
    currentFormState.specification = 'Bulk Spec Allowed';

    const saveButton = wrapper.findAll('button').find(b => b.text().includes('Simpan Perubahan Massal'));
    await saveButton!.trigger('click');

    expect(mockPost).toHaveBeenCalledTimes(1);
    const [, payload] = mockPost.mock.calls[0];
    expect(payload.specification).toBe('Bulk Spec Allowed');
  });

  it('omits specification input in single edit if item is COMP', async () => {
    const compItem = {
      id: 401,
      code: 'MDL-COMP-SINGLE',
      category: 'Computer',
      category_code: 'COMP',
      subcategory: 'Desktop',
      brand_id: 10,
      uom_id: 1,
      name: 'OptiPlex',
      specification: '',
      is_consumable: false,
    };

    const wrapper = mountModal({ open: true, items: [compItem] });
    await wrapper.vm.$nextTick();

    expect(wrapper.text()).not.toContain('Spesifikasi');
  });

  it('resets form state when modal closes or is submitted successfully', async () => {
    const nonComp1 = { ...sampleSingleItem, id: 301 };
    const nonComp2 = { ...sampleSingleItem, id: 302 };

    const wrapper = mountModal({ open: true, items: [nonComp1, nonComp2] });
    await wrapper.vm.$nextTick();

    currentFormState.name = 'New Bulk Name';
    currentFormState.uom_id = 2;

    const vm = wrapper.vm as any;
    vm.closeModal();

    expect(currentFormState.name).toBe('');
    expect(currentFormState.uom_id).toBeNull();
    expect(currentFormState.reset).toHaveBeenCalled();
  });
});
