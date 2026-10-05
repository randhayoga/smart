import { describe, it, expect, beforeEach, vi } from 'vitest';
import { reactive } from 'vue';
import { mount } from '@vue/test-utils';
import CreateTipeModal from '../CreateTipeModal.vue';
import { i18n, setI18nLanguage } from '@/locales';

vi.mock('@inertiajs/vue3', async (importOriginal) => {
  const actual: any = await importOriginal();
  return {
    ...actual,
    useForm: (initialData: any) => reactive({
      ...initialData,
      _method: 'POST',
      errors: {},
      post: vi.fn(),
      reset: vi.fn(),
      clearErrors: vi.fn(),
    }),
  };
});

describe('CreateTipeModal.vue', () => {
  beforeEach(() => {
    setI18nLanguage('id');
    vi.clearAllMocks();
  });

  const baseProps = {
    open: true,
    categories: [{ id: 1, code: 'EL', name: 'Elektronik' }],
    subcategories: [{ id: 1, code: 'LP', name: 'Laptop', category_id: 1, is_consumable: false }],
    uoms: [{ id: 1, name: 'Unit' }],
    brands: [{ id: 1, name: 'Lenovo' }],
    barangs: [],
    tipeList: [],
  };

  const mountModal = (props: any = {}) => {
    return mount(CreateTipeModal, {
      props: {
        ...baseProps,
        ...props,
      },
      global: {
        plugins: [i18n],
        stubs: {
          Teleport: true,
          Transition: false,
          Combobox: { template: '<div></div>' },
          DropdownMenu: { template: '<div><slot /></div>' },
          DropdownMenuTrigger: { template: '<div><slot /></div>' },
          DropdownMenuContent: { template: '<div><slot /></div>' },
          DropdownMenuItem: { template: '<div><slot /></div>' },
        },
      },
    });
  };

  it('renders remove photo button and clicking it clears uploaded photo', async () => {
    const wrapper = mountModal();
    const vm = wrapper.vm as any;

    // Delete photo button exists
    const deleteBtn = wrapper.find('button[title="Hapus Foto"]');
    expect(deleteBtn.exists()).toBe(true);
    // Initially disabled because no photo selected
    expect(deleteBtn.attributes('disabled')).toBeDefined();

    // Simulate uploading a file
    const file = new File(['dummy content'], 'tipe-sample.png', { type: 'image/png' });
    const event = { target: { files: [file] } };
    vm.handleFileUpload(event);
    await wrapper.vm.$nextTick();

    // Delete button should now be enabled and filename shown
    expect(wrapper.find('button[title="Hapus Foto"]').attributes('disabled')).toBeUndefined();
    expect(wrapper.text()).toContain('tipe-sample.png');

    // Click delete photo
    await wrapper.find('button[title="Hapus Foto"]').trigger('click');

    // Photo should be cleared and delete button disabled
    expect(wrapper.text()).toContain('Belum ada foto yang dipilih');
    expect(wrapper.find('button[title="Hapus Foto"]').attributes('disabled')).toBeDefined();
    expect(vm.newItem.photo).toBeNull();
  });
});
