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

  it('renders modal title as Input LOT Baru and does not display LOT Number input', () => {
    const wrapper = mountModal();
    expect(wrapper.text()).toContain('Input LOT Baru');
    // Ensure no LOT Number / Code input
    expect(wrapper.find('input[placeholder="Kode LOT digenerate otomatis"]').exists()).toBe(false);
  });

  it('submits procurement lot creation form successfully and closes modal', async () => {
    const wrapper = mountModal();
    const vm = wrapper.vm as any;
    vm.lotForm.entry_type = 'Procurement';
    vm.lotForm.po_number = 'PO-0001';
    vm.lotForm.purchase_date = '2026-10-02';
    vm.lotForm.organizer_id = 1;
    vm.lotForm.vendor_id = 1;

    vm.handleSubmit();

    expect(vm.lotForm.post).toHaveBeenCalledWith('/smart/stock-management/lots', expect.any(Object));
    expect(wrapper.emitted('update:open')?.[0]).toEqual([false]);
    expect(wrapper.emitted('success')).toHaveLength(1);
  });

  it('submits hibah lot creation form with memo fields successfully', async () => {
    const wrapper = mountModal();
    const vm = wrapper.vm as any;
    vm.onSelectEntryType('Hibah');
    vm.lotForm.memo_number = 'MEMO-999';
    vm.lotForm.date_of_grant = '2026-10-03';
    vm.lotForm.organizer_id = 1;

    vm.handleSubmit();

    expect(vm.lotForm.post).toHaveBeenCalledWith('/smart/stock-management/lots', expect.any(Object));
    expect(wrapper.emitted('update:open')?.[0]).toEqual([false]);
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

  it('validates consumable lot fields correctly and does not render Match Parent button', async () => {
    const wrapper = mountModal({
      barang: {
        is_consumable: true,
        image_url: 'items/parent.jpg',
      },
    });

    // Ensure Match Parent / Samakan button is not rendered
    const buttons = wrapper.findAll('button').map(b => b.text());
    expect(buttons).not.toContain('Samakan');

    const vm = wrapper.vm as any;
    vm.lotForm.entry_type = 'Procurement';
    vm.lotForm.po_number = 'PO-CS-01';
    vm.lotForm.purchase_date = '2026-10-02';
    vm.lotForm.organizer_id = 1;
    vm.lotForm.vendor_id = 1;
    vm.lotForm.initial_quantity = '';
    vm.lotForm.location_id = '';
    vm.lotForm.burden = 'Project';
    vm.lotForm.project_id = '';

    vm.handleSubmit();

    expect(vm.errors.initial_quantity).toBeTruthy();
    expect(vm.errors.location_id).toBeTruthy();
    expect(vm.errors.project_id).toBeTruthy();
    expect(vm.lotForm.post).not.toHaveBeenCalled();
  });

  it('renders AssetSection for non-consumable items and submits asset info', async () => {
    const wrapper = mountModal({
      barang: {
        is_consumable: false,
        category: 'COMP',
      },
    });

    const vm = wrapper.vm as any;
    vm.lotForm.entry_type = 'Procurement';
    vm.lotForm.po_number = 'PO-0002';
    vm.lotForm.purchase_date = '2026-10-02';
    vm.lotForm.organizer_id = 1;
    vm.lotForm.vendor_id = 1;

    // Enable asset input
    vm.lotForm.input_asset = true;
    vm.lotForm.asset_specification = '16GB RAM';
    vm.lotForm.borrowing_type = 'LT';
    vm.lotForm.condition = 'Bagus';
    vm.lotForm.location_id = 1;
    vm.lotForm.classification = 'Aset';
    vm.lotForm.burden = 'Corporate';

    vm.handleSubmit();

    expect(vm.lotForm.post).toHaveBeenCalledWith('/smart/stock-management/lots', expect.any(Object));
  });
});
