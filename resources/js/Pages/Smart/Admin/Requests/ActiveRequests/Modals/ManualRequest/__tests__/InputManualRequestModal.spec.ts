import { describe, it, expect, beforeEach, vi } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';
import { router } from '@inertiajs/vue3';
import InputManualRequestModal from '../InputManualRequestModal.vue';
import RequestInfoSection from '../RequestInfoSection.vue';
import ItemSelectionSection from '../ItemSelectionSection.vue';
import AddedItemsListSection from '../AddedItemsListSection.vue';
import { i18n, setI18nLanguage } from '@/locales';

vi.mock('@inertiajs/vue3', async (importOriginal) => {
  const actual: any = await importOriginal();
  return {
    ...actual,
    router: {
      post: vi.fn(),
    },
  };
});

describe('InputManualRequestModal.vue', () => {
  beforeEach(() => {
    setI18nLanguage('id');
    vi.clearAllMocks();

    vi.stubGlobal(
      'fetch',
      vi.fn(async (url: string) => {
        if (url === '/smart/requests/manual-options') {
          return {
            ok: true,
            json: async () => ({
              employees: [
                { id: 101, employee_id: 'NPK-101', name: 'NPK-101 - Andi Peminjam', orgchart_id: 10 },
                { id: 102, employee_id: 'NPK-102', name: 'NPK-102 - Budi DM', orgchart_id: 10 },
              ],
            }),
          };
        }
        if (url.startsWith('/smart/requests/manual-options?user_id=101')) {
          return {
            ok: true,
            json: async () => ({
              departments: [{ id: 10, name: 'IT Division' }],
              projects: [{ id: 501, name: '[PRJ-501] Smart Project' }],
              auto_approver_id: 102,
              approver_candidates: [
                { id: 101, employee_id: 'NPK-101', name: 'NPK-101 - Andi Peminjam' },
                { id: 102, employee_id: 'NPK-102', name: 'NPK-102 - Budi DM' },
              ],
            }),
          };
        }
        if (url.startsWith('/smart/requests/manual-catalog?type=consumable')) {
          return {
            ok: true,
            json: async () => ({
              items: [
                {
                  id: 201,
                  number: 'MDL-201',
                  code: 'MDL-201',
                  name: 'Kabel LAN Cat6',
                  specification: '5 Meter',
                  brand: 'Belden',
                  brand_name: 'Belden',
                  uom: 'Pcs',
                  uom_name: 'Pcs',
                  type_id: 5,
                  type: 'Kabel',
                  type_name: 'Kabel',
                  category: 'Network',
                  category_name: 'Network',
                  image_url: null,
                  available_stock: 12,
                },
              ],
            }),
          };
        }
        if (url.startsWith('/smart/requests/manual-catalog?type=st')) {
          return {
            ok: true,
            json: async () => ({
              items: [
                {
                  id: 301,
                  number: 'AST-ST-001',
                  asset_number: 'AST-ST-001',
                  borrowing_type: 'ST',
                  condition: 'Bagus',
                  specification: '16GB RAM',
                  image_url: null,
                  location: 'Ruang Server',
                  location_name: 'Ruang Server',
                  model_id: 202,
                  model_number: 'MDL-202',
                  model_code: 'MDL-202',
                  model_name: 'ThinkPad X1',
                  name: 'ThinkPad X1',
                  brand: 'Lenovo',
                  brand_name: 'Lenovo',
                  uom: 'Unit',
                  uom_name: 'Unit',
                  type_id: 6,
                  type: 'Laptop',
                  type_name: 'Laptop',
                  category: 'Computer',
                  category_name: 'Computer',
                },
              ],
            }),
          };
        }
        return {
          ok: true,
          json: async () => ({ assets: [], models: [] }),
        };
      })
    );
  });

  const mountModal = () =>
    mount(InputManualRequestModal, {
      props: {
        open: true,
      },
      global: {
        plugins: [i18n],
        stubs: {
          Teleport: true,
          Transition: false,
        },
      },
    });

  it('renders 95vw modal without subheadings and with Upload Form placed before Notes/Purpose', async () => {
    const wrapper = mountModal();
    await flushPromises();

    const modalEl = wrapper.find('[data-testid="input-manual-request-modal"]');
    expect(modalEl.exists()).toBe(true);
    expect(modalEl.classes()).toContain('w-[95vw]');

    const text = wrapper.text();
    const html = wrapper.html();
    // Subheadings must not exist
    expect(text).not.toContain('1. ');
    expect(text).not.toContain('2. ');
    expect(text).not.toContain('3. ');
    expect(text).not.toContain('Informasi Permintaan/Peminjaman');
    expect(text).not.toContain('Pemilihan Barang/Aset');
    expect(text).not.toContain('Daftar Barang/Aset yang Ditambahkan');

    const uploadIdx = html.indexOf('Unggah Form');
    const notesIdx = html.indexOf('Catatan/Keperluan');

    expect(uploadIdx).toBeGreaterThan(-1);
    expect(notesIdx).toBeGreaterThan(-1);
    expect(uploadIdx).toBeLessThan(notesIdx);
  });

  it('shows validation errors including required form_file and items when submitting empty', async () => {
    const wrapper = mountModal();
    await flushPromises();

    await wrapper.find('[data-testid="submit-manual-request-btn"]').trigger('click');

    expect(wrapper.text()).toContain('File form wajib diunggah.');
    expect(wrapper.text()).toContain('Tambahkan minimal 1 barang atau aset ke daftar.');
    expect(router.post).not.toHaveBeenCalled();
  });

  it('auto-hydrates department and DM on user selection, adds consumable item, and submits via router.post', async () => {
    const wrapper = mountModal();
    await flushPromises();

    const infoSection = wrapper.findComponent(RequestInfoSection);
    infoSection.vm.$emit('change-user', 101);
    await flushPromises();

    const file = new File(['dummy pdf content'], 'form-request.pdf', { type: 'application/pdf' });
    infoSection.vm.$emit('change-file', file);
    await flushPromises();

    const itemSection = wrapper.findComponent(ItemSelectionSection);
    itemSection.vm.$emit('add-item', {
      key: 'consumable-201',
      request_type: 'consumable',
      model_id: 201,
      asset_id: null,
      number: 'MDL-201',
      brand_name: 'Belden',
      name: 'Kabel LAN Cat6',
      specification: '5 Meter',
      category_name: 'Network',
      type_name: 'Kabel',
      uom_name: 'Pcs',
      image_url: null,
      available_stock: 12,
      quantity: 3,
      reasoning: 'Instalasi ruang rapat',
    });
    await flushPromises();

    const addedSection = wrapper.findComponent(AddedItemsListSection);
    expect(addedSection.text()).toContain('Kabel LAN Cat6');
    expect(addedSection.text()).toContain('Instalasi ruang rapat');

    await wrapper.find('[data-testid="submit-manual-request-btn"]').trigger('click');

    expect(router.post).toHaveBeenCalledTimes(1);
    expect(router.post).toHaveBeenCalledWith(
      '/smart/requests/manual',
      expect.objectContaining({
        request_type: 'consumable',
        user_id: 101,
        approver_id: 102,
        utilization: 'corporate',
        org_id: 10,
        form_file: file,
        items: [
          {
            model_id: 201,
            quantity: 3,
            reasoning: 'Instalasi ruang rapat',
          },
        ],
      }),
      expect.objectContaining({
        forceFormData: true,
      })
    );
  });

  it('clears added items and switches date inputs when changing request_type to ST', async () => {
    const wrapper = mountModal();
    await flushPromises();

    const itemSection = wrapper.findComponent(ItemSelectionSection);
    itemSection.vm.$emit('add-item', {
      key: 'consumable-201',
      request_type: 'consumable',
      model_id: 201,
      asset_id: null,
      number: 'MDL-201',
      brand_name: 'Belden',
      name: 'Kabel LAN Cat6',
      specification: '5 Meter',
      category_name: 'Network',
      type_name: 'Kabel',
      uom_name: 'Pcs',
      image_url: null,
      available_stock: 12,
      quantity: 2,
      reasoning: '',
    });
    await flushPromises();

    expect(wrapper.findAll('[data-testid="added-manual-item-card"]')).toHaveLength(1);

    const infoSection = wrapper.findComponent(RequestInfoSection);
    infoSection.vm.$emit('change-request-type', 'st');
    await flushPromises();

    expect(wrapper.findAll('[data-testid="added-manual-item-card"]')).toHaveLength(0);
    expect(wrapper.find('#manual-request-start-date').exists()).toBe(true);
    expect(wrapper.find('#manual-request-end-date').exists()).toBe(true);
  });

  it('highlights amount with red border and shows error when quantity exceeds available stock', async () => {
    const wrapper = mountModal();
    await flushPromises();

    const itemSection = wrapper.findComponent(ItemSelectionSection);
    // Select model 201 which has available_stock = 12
    (itemSection.vm as any).selectedModelId = 201;
    await flushPromises();

    const qtyInput = wrapper.find('#manual-request-item-qty');
    expect(qtyInput.exists()).toBe(true);

    // Enter quantity exceeding stock (e.g. 50 > 12)
    await qtyInput.setValue(50);
    await flushPromises();

    const amountContainer = qtyInput.element.parentElement;
    expect(amountContainer?.className).toContain('border-destructive');
    expect(itemSection.text()).toContain('Jumlah melebihi stok tersedia (12 Pcs)');

    // Enter valid quantity (e.g. 5 <= 12)
    await qtyInput.setValue(5);
    await flushPromises();
    expect(amountContainer?.className).not.toContain('border-destructive');
    expect(amountContainer?.className).toContain('border-input');
  });

  it('clears datepicker error and red border when new date is selected', async () => {
    const wrapper = mountModal();
    await flushPromises();

    // Trigger validation by clicking submit with empty form
    await wrapper.find('[data-testid="submit-manual-request-btn"]').trigger('click');
    await flushPromises();

    // Date is pre-filled with today for consumable, so switch to ST to test start_date & end_date errors
    const infoSection = wrapper.findComponent(RequestInfoSection);
    infoSection.vm.$emit('change-request-type', 'st');
    await flushPromises();

    // Clear start_date and end_date then trigger submit to induce error
    const startDateInput = wrapper.find('#manual-request-start-date');
    const endDateInput = wrapper.find('#manual-request-end-date');

    await startDateInput.setValue('');
    await endDateInput.setValue('');
    await wrapper.find('[data-testid="submit-manual-request-btn"]').trigger('click');
    await flushPromises();

    expect(startDateInput.classes()).toContain('border-destructive');
    expect(endDateInput.classes()).toContain('border-destructive');

    // Input new start_date and end_date
    await startDateInput.setValue('2026-11-01');
    await flushPromises();
    expect(startDateInput.classes()).not.toContain('border-destructive');
    expect(startDateInput.classes()).toContain('border-input');

    await endDateInput.setValue('2026-11-10');
    await flushPromises();
    expect(endDateInput.classes()).not.toContain('border-destructive');
    expect(endDateInput.classes()).toContain('border-input');
  });

  it('submits payload with both start_date and end_date when request_type is rent', async () => {
    const wrapper = mountModal();
    await flushPromises();

    const infoSection = wrapper.findComponent(RequestInfoSection);
    infoSection.vm.$emit('change-request-type', 'rent');
    await flushPromises();

    // Select user
    infoSection.vm.$emit('change-user', 101);
    await flushPromises();

    // Set department and approver
    infoSection.vm.$emit('change-department', 10);
    infoSection.vm.$emit('change-approver', 102);

    // Provide file
    const fakeFile = new File(['dummy'], 'signed.pdf', { type: 'application/pdf' });
    infoSection.vm.$emit('change-file', fakeFile);

    // Provide dates
    const startDateInput = wrapper.find('#manual-request-start-date');
    const endDateInput = wrapper.find('#manual-request-end-date');
    await startDateInput.setValue('2026-11-01');
    await endDateInput.setValue('2026-11-15');

    // Add an item
    const itemSection = wrapper.findComponent(ItemSelectionSection);
    itemSection.vm.$emit('add-item', {
      key: 'item-rent-1',
      request_type: 'rent',
      model_id: 1,
      asset_id: 201,
      number: 'AST-RENT-001',
      brand_name: 'Dell',
      name: 'Latitude',
      specification: 'Core i7',
      category_name: 'Laptop',
      type_name: 'Laptop',
      uom_name: 'Unit',
      available_stock: 1,
      quantity: 1,
      reasoning: 'Testing rent',
    });
    await flushPromises();

    // Submit
    await wrapper.find('[data-testid="submit-manual-request-btn"]').trigger('click');
    await flushPromises();

    expect(router.post).toHaveBeenCalledTimes(1);
    const [url, payload] = (router.post as any).mock.calls[0];
    expect(url).toBe('/smart/requests/manual');
    expect(payload.request_type).toBe('rent');
    expect(payload.start_date).toBe('2026-11-01');
    expect(payload.end_date).toBe('2026-11-15');
    expect(payload.items).toHaveLength(1);
    expect(payload.items[0].asset_id).toBe(201);
  });
});
