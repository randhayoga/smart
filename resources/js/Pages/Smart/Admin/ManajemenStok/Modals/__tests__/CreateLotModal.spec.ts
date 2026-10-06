import { describe, it, expect, beforeEach, vi } from 'vitest';
import { reactive } from 'vue';
import { mount } from '@vue/test-utils';
import CreateLotModal from '../CreateLotModal.vue';
import { i18n, setI18nLanguage } from '@/locales';

vi.mock('@inertiajs/vue3', async (importOriginal) => {
  const actual: any = await importOriginal();
  return {
    ...actual,
    useForm: (initialData: any) => {
      return reactive({
        ...initialData,
        _method: 'POST',
        processing: false,
        errors: {},
        post: vi.fn((url: string, options: any) => {
          if (options?.onSuccess) {
            options.onSuccess();
          }
        }),
        transform: vi.fn((fn: any) => fn(initialData)),
        reset: vi.fn(),
        clearErrors: vi.fn(),
      });
    },
  };
});

describe('CreateLotModal.vue', () => {
  beforeEach(() => {
    setI18nLanguage('id');
    vi.clearAllMocks();
  });

  const sampleProps = {
    open: true,
    barang: {
      id: 1,
      code: 'LP',
      category: 'Perangkat Kantor',
      subcategory: 'Laptop',
      brand: 'Lenovo',
      name: 'ThinkPad X1',
      specification: 'Core i7',
      image_url: null,
      uom: 'Unit',
      is_consumable: false,
    },
    lots: [],
    organizers: [{ id: 1, name: 'DIV-IT' }],
    vendors: [{ id: 1, name: 'Vendor A' }],
    locations: [{ id: 1, name: 'HQ' }],
    projects: [],
  };

  const mountModal = (props: any = {}) => {
    return mount(CreateLotModal, {
      props: {
        ...sampleProps,
        ...props,
        barang: {
          ...sampleProps.barang,
          ...(props.barang || {}),
        },
      },
      global: {
        plugins: [i18n],
        stubs: {
          Teleport: true,
          Transition: false,
          LocationCombobox: { template: '<div></div>' },
          Combobox: { template: '<div></div>' },
          DropdownMenu: { template: '<div><slot /></div>' },
          DropdownMenuTrigger: { template: '<div><slot /></div>' },
          DropdownMenuContent: { template: '<div><slot /></div>' },
          DropdownMenuItem: { template: '<div><slot /></div>' },
          Checkbox: { template: '<input type="checkbox" />' },
          RadioGroup: { template: '<div><slot /></div>' },
          RadioGroupItem: { template: '<div><slot /></div>' },
        },
      },
    });
  };

  it('submits lot creation form successfully and closes modal', async () => {
    const wrapper = mountModal();
    const vm = wrapper.vm as any;
    vm.lotForm.number = 'LOT-0001-26-LP';
    vm.lotForm.organizer_id = 1;
    vm.lotForm.location_id = 1;
    vm.lotForm.date_of_receipt = '2026-10-02';
    vm.lotForm.unit_price = '7500000';

    vm.handleSubmit();

    expect(vm.lotForm.post).toHaveBeenCalledWith('/smart/inventory/lots', expect.any(Object));
    expect(wrapper.emitted('update:open')?.[0]).toEqual([false]);
    expect(wrapper.emitted('success')).toHaveLength(1);
  });

  it('blocks Escape key from closing modal while isProcessing is true', async () => {
    const wrapper = mountModal();
    const vm = wrapper.vm as any;
    vm.lotForm.processing = true;

    // Simulate pressing Escape key on window
    const escapeEvent = new KeyboardEvent('keydown', { key: 'Escape', cancelable: true });
    window.dispatchEvent(escapeEvent);

    // Modal should NOT be closed
    expect(wrapper.emitted('update:open')).toBeUndefined();

    // After processing is false, Escape key should close modal
    vm.lotForm.processing = false;
    window.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', cancelable: true }));
    expect(wrapper.emitted('update:open')?.[0]).toEqual([false]);
  });

  it('renders remove photo button and clicking it clears selected or inherited photo', async () => {
    const wrapper = mountModal({
      barang: {
        id: 1,
        code: 'LP',
        image_url: 'items/sample-barang.jpg',
      },
    });

    // Delete photo button exists
    expect(wrapper.find('button[title="Hapus Foto"]').attributes('disabled')).toBeDefined();

    // Click "Samakan"
    const sameAsParentBtn = wrapper.findAll('button').find(b => b.text().trim() === 'Samakan');
    expect(sameAsParentBtn).toBeDefined();
    await sameAsParentBtn?.trigger('click');

    // Delete button should now be enabled
    const enabledDeleteBtn = wrapper.find('button[title="Hapus Foto"]');
    expect(enabledDeleteBtn.attributes('disabled')).toBeUndefined();
    expect(wrapper.text()).toContain('sample-barang.jpg');

    // Click delete photo
    await enabledDeleteBtn.trigger('click');

    // Photo should be cleared and delete button disabled again
    expect(wrapper.text()).toContain('Belum ada foto yang dipilih');
    expect(wrapper.find('button[title="Hapus Foto"]').attributes('disabled')).toBeDefined();
  });

  it('hides burden fields when parent barang is not consumable', () => {
    const wrapper = mountModal({
      barang: {
        is_consumable: false,
      },
    });

    expect(wrapper.text()).not.toContain('Pembebanan');
  });

  it('shows burden fields when parent barang is consumable and validates project', async () => {
    const wrapper = mountModal({
      barang: {
        is_consumable: true,
      },
    });

    expect(wrapper.text()).toContain('Pembebanan');
    const vm = wrapper.vm as any;
    vm.lotForm.number = 'LOT-0001-26-CS';
    vm.lotForm.organizer_id = 1;
    vm.lotForm.location_id = 1;
    vm.lotForm.date_of_receipt = '2026-10-02';
    vm.lotForm.burden = 'Project';
    vm.lotForm.project_id = '';

    vm.handleSubmit();

    expect(vm.errors.project_id).toBeTruthy();
    expect(vm.lotForm.post).not.toHaveBeenCalled();
  });

  it('requires location_id when parent barang is consumable', async () => {
    const wrapper = mountModal({
      barang: {
        is_consumable: true,
      },
    });

    const vm = wrapper.vm as any;
    vm.lotForm.number = 'LOT-0001-26-CS';
    vm.lotForm.organizer_id = 1;
    vm.lotForm.location_id = '';
    vm.lotForm.date_of_receipt = '2026-10-02';
    vm.lotForm.unit_price = '100000';

    vm.handleSubmit();

    expect(vm.errors.location_id).toBeTruthy();
    expect(vm.lotForm.post).not.toHaveBeenCalled();
  });

  it('allows empty location_id when parent barang is not consumable', async () => {
    const wrapper = mountModal({
      barang: {
        is_consumable: false,
      },
    });

    const vm = wrapper.vm as any;
    vm.lotForm.number = 'LOT-0002-26-NC';
    vm.lotForm.organizer_id = 1;
    vm.lotForm.location_id = '';
    vm.lotForm.date_of_receipt = '2026-10-02';
    vm.lotForm.unit_price = '7500000';

    vm.handleSubmit();

    expect(vm.errors.location_id).toBeFalsy();
    expect(vm.lotForm.post).toHaveBeenCalledWith('/smart/inventory/lots', expect.any(Object));
  });
});
