import { describe, it, expect, beforeEach, vi } from 'vitest';
import { reactive } from 'vue';
import { mount } from '@vue/test-utils';
import EditLotModal from '../EditLotModal.vue';
import { i18n, setI18nLanguage } from '@/locales';

vi.mock('@inertiajs/vue3', async (importOriginal) => {
  const actual: any = await importOriginal();
  return {
    ...actual,
    useForm: (initialData: any) => {
      const formObj: any = reactive({
        ...initialData,
        _method: 'PUT',
        processing: false,
        errors: {},
        post: vi.fn((url: string, options: any) => {
          if (options?.onSuccess) {
            options.onSuccess();
          }
        }),
        transform: vi.fn((fn: any) => {
          return formObj;
        }),
        reset: vi.fn(),
        clearErrors: vi.fn(),
      });
      return formObj;
    },
  };
});

describe('EditLotModal.vue', () => {
  beforeEach(() => {
    setI18nLanguage('id');
    vi.clearAllMocks();
  });

  const sampleSingleItem = {
    id: 10,
    number: 'LOT-0001-26-LP',
    barang_id: 1,
    model_id: 1,
    organizer_id: 1,
    vendor_id: 2,
    po_number: 'PO-100',
    purchase_grant_date: '2026-05-15',
    entry_type: 'Procurement',
    burden: 'Corporate',
    project_id: null,
    location_id: 1,
    unit_price: '50000',
    initial_quantity: 10,
    image_url: null,
  };

  const sampleProps = {
    open: true,
    items: [sampleSingleItem],
    isConsumable: false,
    organizers: [{ id: 1, name: 'DIV-IT' }],
    vendors: [{ id: 2, name: 'Vendor Tech' }],
    locations: [{ id: 1, name: 'HQ Warehouse' }],
    projects: [{ id_project: 5, project_name: 'Project Alpha', no_project: 'PRJ-01' }],
    uom: 'Unit',
  };

  const mountModal = (props: any = {}) => {
    return mount(EditLotModal, {
      props: {
        ...sampleProps,
        ...props,
      },
      global: {
        plugins: [i18n],
        stubs: {
          Teleport: true,
          Transition: false,
          LocationCombobox: { template: '<div data-testid="location-combobox"></div>' },
          Combobox: { template: '<div data-testid="combobox"></div>' },
          DropdownMenu: { template: '<div><slot /></div>' },
          DropdownMenuTrigger: { template: '<div><slot /></div>' },
          DropdownMenuContent: { template: '<div><slot /></div>' },
          DropdownMenuItem: { template: '<div><slot /></div>' },
        },
      },
    });
  };

  describe('Single LOT Edit', () => {
    it('renders single edit title and disabled LOT Code', () => {
      const wrapper = mountModal();
      expect(wrapper.text()).toContain('Edit Detail LOT');
      const input = wrapper.find('input[disabled]');
      expect((input.element as HTMLInputElement).value).toBe('LOT-0001-26-LP');
    });

    it('displays Entry Type and dynamic date for Procurement', () => {
      const wrapper = mountModal();
      expect(wrapper.text()).toContain('Jenis Entri');
      expect(wrapper.text()).toContain('Tanggal Pembelian');
      expect(wrapper.text()).toContain('Nomor PO');
    });

    it('requires PO Number when Entry Type is Procurement', async () => {
      const wrapper = mountModal();
      const vm = wrapper.vm as any;
      vm.form.po_number = '';

      vm.handleSubmit();

      expect(vm.errors.po_number).toBeTruthy();
      expect(vm.form.post).not.toHaveBeenCalled();
    });

    it('switches to Hibah: displays memo fields, date of grant, and hides PO number', async () => {
      const wrapper = mountModal({
        items: [{
          ...sampleSingleItem,
          entry_type: 'Hibah',
          memo_number: 'MEMO-555',
          purchase_grant_date: '2026-06-01',
        }],
      });

      expect(wrapper.text()).toContain('Nomor Memo');
      expect(wrapper.text()).toContain('Tanggal Dihibahkan');
      expect(wrapper.text()).toContain('Berita Acara / Memo');
      expect(wrapper.text()).not.toContain('Nomor PO');
    });

    it('does NOT display price, location, or photo inputs for non-consumable lots', () => {
      const wrapper = mountModal({ isConsumable: false });
      expect(wrapper.find('[data-testid="location-combobox"]').exists()).toBe(false);
      expect(wrapper.find('#edit-lot-photo-upload').exists()).toBe(false);
      expect(wrapper.find('#edit-lot-unit-price').exists()).toBe(false);
    });

    it('displays location, unit price, total stock (disabled), and photo for consumable lots', () => {
      const wrapper = mountModal({ isConsumable: true });
      expect(wrapper.find('[data-testid="location-combobox"]').exists()).toBe(true);
      expect(wrapper.find('#edit-lot-photo-upload').exists()).toBe(true);
      expect(wrapper.find('#edit-lot-unit-price').exists()).toBe(true);
      expect(wrapper.find('#edit-lot-total-stock').exists()).toBe(true);
      expect(wrapper.find('#edit-lot-total-stock').attributes('disabled')).toBeDefined();

      // Ensure "Samakan Foto" / "Match Parent" button is NOT present
      const buttons = wrapper.findAll('button').map(b => b.text());
      expect(buttons).not.toContain('Samakan');
    });
  });

  describe('Mass LOT Edit', () => {
    const sampleBulkItems = [
      { id: 10, number: 'LOT-0001' },
      { id: 11, number: 'LOT-0002' },
    ];

    it('renders mass edit title and LOT Code as disabled with "Tidak dapat diubah secara massal"', () => {
      const wrapper = mountModal({ items: sampleBulkItems });
      expect(wrapper.text()).toContain('Edit LOT Terpilih');

      const topLotInput = wrapper.findAll('input[disabled]')[0];
      expect((topLotInput.element as HTMLInputElement).value).toBe('Tidak dapat diubah secara massal');
    });

    it('does not display Entry Type, PO Number, or Memo fields in mass edit', () => {
      const wrapper = mountModal({ items: sampleBulkItems });
      expect(wrapper.text()).not.toContain('Jenis Pemasukan');
      expect(wrapper.find('#edit-lot-po-number').exists()).toBe(false);
      expect(wrapper.find('#edit-lot-memo-number').exists()).toBe(false);
    });

    it('removes red asterisk from Purchase Date in mass edit', () => {
      const wrapper = mountModal({ items: sampleBulkItems });
      const dateField = wrapper.find('#edit-lot-purchase-date');
      expect(dateField.exists()).toBe(true);

      // Verify label has no red asterisk
      const label = dateField.element.closest('div')?.parentElement?.querySelector('label');
      expect(label?.textContent).not.toContain('*');
    });

    it('submits bulk lot updates to /smart/stock-management/lots/bulk', async () => {
      const wrapper = mountModal({ items: sampleBulkItems });
      const vm = wrapper.vm as any;
      vm.form.organizer_id = 1;
      vm.form.purchase_date = '2026-10-15';

      vm.handleSubmit();

      expect(vm.form.post).toHaveBeenCalledWith('/smart/stock-management/lots/bulk', expect.any(Object));
      expect(wrapper.emitted('update:open')?.[0]).toEqual([false]);
    });

    it('resets form state and clears errors when modal is closed', () => {
      const wrapper = mountModal({ items: sampleBulkItems });
      const vm = wrapper.vm as any;
      vm.form.organizer_id = 1;
      vm.form.purchase_date = '2026-10-15';

      vm.closeModal();

      expect(vm.form.organizer_id).toBe('');
      expect(vm.form.purchase_date).toBe('');
      expect(vm.form.reset).toHaveBeenCalled();
    });
  });
});
