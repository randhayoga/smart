import { describe, it, expect, beforeEach, vi } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';
import { router } from '@inertiajs/vue3';
import InputManualRequestModal from '../InputManualRequestModal.vue';
import RequestInfoSection from '../RequestInfoSection.vue';
import ItemSelectionSection from '../ItemSelectionSection.vue';
import AddedItemsListSection from '../AddedItemsListSection.vue';
import HandoverInfoSection from '../HandoverInfoSection.vue';
import { i18n, setI18nLanguage } from '@/locales';

import { toast } from 'vue-sonner';

vi.mock('vue-sonner', () => ({
  toast: {
    success: vi.fn(),
    error: vi.fn(),
  },
}));

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
              locations: [
                { id: 50, name: 'Ruang IFS', parent_id: null, is_active: true },
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
    const listIdx = html.indexOf('Daftar:');
    const handoverIdx = html.indexOf('Metode Serah Terima');

    expect(uploadIdx).toBeGreaterThan(-1);
    expect(notesIdx).toBeGreaterThan(-1);
    expect(uploadIdx).toBeLessThan(notesIdx);
    expect(listIdx).toBeGreaterThan(-1);
    expect(handoverIdx).toBeGreaterThan(listIdx);
  });

  it('shows validation errors including required form_file, items, and handover_location_id when submitting empty', async () => {
    const wrapper = mountModal();
    await flushPromises();

    await wrapper.find('[data-testid="submit-manual-request-btn"]').trigger('click');

    expect(wrapper.text()).toContain('File form wajib diunggah.');
    expect(wrapper.text()).toContain('Tambahkan minimal 1 barang atau aset ke daftar.');
    expect(wrapper.text()).toContain('Lokasi serah terima wajib dipilih.');
    expect(router.post).not.toHaveBeenCalled();
  });

  it('auto-hydrates department and DM on user selection, adds consumable item, sets handover fields, and submits via router.post', async () => {
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

    const handoverSection = wrapper.findComponent(HandoverInfoSection);
    const proofFile = new File(['dummy proof'], 'bukti-serah-terima.jpg', { type: 'image/jpeg' });
    handoverSection.vm.$emit('change-handover-location', 50);
    handoverSection.vm.$emit('change-handover-proof', proofFile);
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
        handover_method: 'pickup',
        handover_location_id: 50,
        handover_proof: proofFile,
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

    // Set handover method and location
    const handoverSection = wrapper.findComponent(HandoverInfoSection);
    handoverSection.vm.$emit('change-handover-method', 'delivery');
    handoverSection.vm.$emit('change-handover-location', 50);
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
    expect(payload.handover_method).toBe('delivery');
    expect(payload.handover_location_id).toBe(50);
    expect(payload.items).toHaveLength(1);
    expect(payload.items[0].asset_id).toBe(201);
  });

  it('allows inline note editing inside AddedItemsListSection cards', async () => {
    const wrapper = mountModal();
    await flushPromises();

    const itemSection = wrapper.findComponent(ItemSelectionSection);
    // 1. Add item with no initial reasoning
    itemSection.vm.$emit('add-item', {
      key: 'consumable-201',
      request_type: 'consumable',
      model_id: 201,
      asset_id: null,
      number: 'MDL-201',
      brand_name: 'Belden',
      name: 'Kabel LAN Cat6',
      specification: '',
      category_name: 'Consumable',
      type_name: 'Network Cable',
      uom_name: 'Meter',
      image_url: null,
      available_stock: 50,
      quantity: 5,
      reasoning: '',
    });
    await flushPromises();

    const addedSection = wrapper.findComponent(AddedItemsListSection);
    expect(addedSection.find('[data-testid="add-note-btn"]').exists()).toBe(true);

    // 2. Click Add Note button
    await addedSection.find('[data-testid="add-note-btn"]').trigger('click');
    await flushPromises();

    expect(addedSection.find('[data-testid="edit-note-container"]').exists()).toBe(true);

    // 3. Type new note and save
    const noteInput = addedSection.find('[data-testid="edit-note-container"] input');
    await noteInput.setValue('Catatan tambahan setelah add');
    await addedSection.find('[data-testid="save-note-btn"]').trigger('click');
    await flushPromises();

    // Verify note is saved and displayed
    expect(addedSection.find('[data-testid="edit-note-container"]').exists()).toBe(false);
    expect(addedSection.text()).toContain('Catatan tambahan setelah add');
    expect(addedSection.find('[data-testid="edit-note-btn"]').exists()).toBe(true);

    // 4. Edit existing note
    await addedSection.find('[data-testid="edit-note-btn"]').trigger('click');
    await flushPromises();

    const editInput = addedSection.find('[data-testid="edit-note-container"] input');
    expect((editInput.element as HTMLInputElement).value).toBe('Catatan tambahan setelah add');

    await editInput.setValue('Catatan revisi');
    await addedSection.find('[data-testid="save-note-btn"]').trigger('click');
    await flushPromises();

    expect(addedSection.text()).toContain('Catatan revisi');

    // 5. Test cancel editing
    await addedSection.find('[data-testid="edit-note-btn"]').trigger('click');
    await flushPromises();
    await addedSection.find('[data-testid="edit-note-container"] input').setValue('Will cancel this');
    await addedSection.find('[data-testid="cancel-note-btn"]').trigger('click');
    await flushPromises();

    expect(addedSection.find('[data-testid="edit-note-container"]').exists()).toBe(false);
    expect(addedSection.text()).toContain('Catatan revisi');
  });

  it('triggers seeHere toast link on successful submission with created_request flash data', async () => {
    const wrapper = mountModal();
    await flushPromises();

    // Fill valid form
    const reqSection = wrapper.findComponent(RequestInfoSection);
    const formState = reqSection.props('form');
    formState.request_type = 'consumable';
    formState.user_id = 101;
    formState.approver_id = 102;
    formState.utilization = 'corporate';
    formState.org_id = 10;
    formState.form_file = new File(['dummy'], 'form.pdf', { type: 'application/pdf' });
    formState.date = '2026-11-05';

    const itemSection = wrapper.findComponent(ItemSelectionSection);
    itemSection.vm.$emit('add-item', {
      model_id: 1,
      type_id: 2,
      model_name: 'Pulpen Gel',
      brand_name: 'Pilot',
      category_name: 'ATK',
      type_name: 'Alat Tulis',
      is_consumable: true,
      quantity: 2,
      reasoning: 'Keperluan kantor',
    });

    const handoverSection = wrapper.findComponent(HandoverInfoSection);
    const handoverForm = handoverSection.props('form');
    handoverForm.handover_method = 'pickup';
    handoverForm.handover_location_id = 50;
    await flushPromises();

    // Mock router.post to execute onSuccess with flash created_request
    (router.post as any).mockImplementation((_url: string, _data: any, options: any) => {
      options.onSuccess({
        props: {
          flash: {
            created_request: {
              id: 99,
              number: 'REQ-MAN-99',
              target_url: '/smart/requests/99',
            },
          },
        },
      });
      options.onFinish();
    });

    await wrapper.find('[data-testid="submit-manual-request-btn"]').trigger('click');
    await flushPromises();

    expect(toast.success).toHaveBeenCalled();
  });
});
