import { describe, it, expect, beforeEach, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import MasterDataModal from '../Modals/MasterDataModal.vue';
import { i18n, setI18nLanguage } from '@/locales';

const mockPost = vi.fn();
const mockPut = vi.fn();

vi.mock('@inertiajs/vue3', async (importOriginal) => {
  const actual = await importOriginal<any>();
  return {
    ...actual,
    useForm: (initialData: any) => {
      const form = actual.useForm(initialData);
      form.post = mockPost;
      form.put = mockPut;
      return form;
    },
  };
});

describe('MasterDataModal.vue', () => {
  beforeEach(() => {
    setI18nLanguage('id');
    (globalThis as any).route = vi.fn((name: string) => `/mock-${name}`);
  });

  const mountModal = (props: any) => {
    return mount(MasterDataModal, {
      props: {
        open: true,
        ...props,
      },
      global: {
        plugins: [i18n],
        stubs: {
          Teleport: true,
          Transition: false,
        },
        mocks: {
          route: vi.fn(() => '/mock-route'),
        },
      },
    });
  };

  it('renders Category create modal with shortened width max-w-xl', () => {
    const wrapper = mountModal({
      mode: 'create',
      activeTab: 'categories',
    });

    const modalBox = wrapper.find('.bg-card');
    expect(modalBox.exists()).toBe(true);
    expect(modalBox.classes()).toContain('max-w-xl');
    expect(modalBox.classes()).not.toContain('max-w-[1200px]');
    expect(wrapper.text()).toContain('Pembuatan Kategori Baru');
  });

  it('renders Subcategory create modal with shortened width max-w-2xl', () => {
    const wrapper = mountModal({
      mode: 'create',
      activeTab: 'subcategories',
    });

    const modalBox = wrapper.find('.bg-card');
    expect(modalBox.exists()).toBe(true);
    expect(modalBox.classes()).toContain('max-w-2xl');
    expect(modalBox.classes()).not.toContain('max-w-[1200px]');
    expect(wrapper.text()).toContain('Pembuatan Subkategori Baru');
  });

  it('renders Location create modal with shortened width max-w-xl', () => {
    const wrapper = mountModal({
      mode: 'create',
      activeTab: 'locations',
    });

    const modalBox = wrapper.find('.bg-card');
    expect(modalBox.exists()).toBe(true);
    expect(modalBox.classes()).toContain('max-w-xl');
    expect(modalBox.classes()).not.toContain('max-w-[1200px]');
    expect(wrapper.text()).toContain('Pembuatan Lokasi Baru');
  });

  it('renders Vendor modal with wide layout max-w-[1000px]', () => {
    const wrapper = mountModal({
      mode: 'create',
      activeTab: 'vendors',
    });

    const modalBox = wrapper.find('.bg-card');
    expect(modalBox.exists()).toBe(true);
    expect(modalBox.classes()).toContain('max-w-[1000px]');
    expect(wrapper.text()).toContain('Pembuatan Vendor Baru');
  });

  it('populates fields in edit mode and disables read-only code for Category', async () => {
    const wrapper = mountModal({
      mode: 'edit',
      activeTab: 'categories',
      item: {
        id: 1,
        code: 'ATKS',
        name: 'Alat Tulis Kantor',
      },
    });

    expect(wrapper.text()).toContain('Edit Kategori');

    const inputs = wrapper.findAll('input');
    const codeInput = inputs.find(i => i.attributes('disabled') !== undefined);
    expect(codeInput).toBeDefined();
    expect((codeInput?.element as HTMLInputElement).value).toBe('ATKS');

    const nameInput = inputs.find(i => i.attributes('disabled') === undefined);
    expect(nameInput).toBeDefined();
    expect((nameInput?.element as HTMLInputElement).value).toBe('Alat Tulis Kantor');
  });

  it('emits update:open and close events when cancel or close button is clicked', async () => {
    const wrapper = mountModal({
      mode: 'create',
      activeTab: 'categories',
    });

    const closeButton = wrapper.find('button[class*="rounded-full"]');
    expect(closeButton.exists()).toBe(true);
    await closeButton.trigger('click');

    expect(wrapper.emitted('update:open')).toBeTruthy();
    expect(wrapper.emitted('update:open')![0]).toEqual([false]);
    expect(wrapper.emitted('close')).toBeTruthy();
  });

  it('renders department combobox in Location modal', () => {
    const wrapper = mountModal({
      mode: 'create',
      activeTab: 'locations',
      departments: [
        { id: 1, name: 'IT - Information Technology' },
        { id: 2, name: 'HR - Human Resources' },
      ],
    });

    expect(wrapper.text()).toContain('Departemen Terkait');
    expect(wrapper.text()).toContain('Tanpa Departemen');
  });

  it('populates related_departement in edit mode for Location', async () => {
    const wrapper = mountModal({
      mode: 'edit',
      activeTab: 'locations',
      item: {
        id: 10,
        name: 'Ruang Server',
        parent_id: null,
        related_departement: 1,
        is_active: true,
      },
      departments: [
        { id: 1, name: 'IT - Information Technology' },
      ],
    });

    expect(wrapper.text()).toContain('Edit Lokasi');
    expect(wrapper.text()).toContain('IT - Information Technology');
  });

  it('submits form with preserveScroll: true in create mode and closes modal on success', async () => {
    mockPost.mockClear();
    const wrapper = mountModal({
      mode: 'create',
      activeTab: 'categories',
    });

    const inputs = wrapper.findAll('input');
    await inputs[0].setValue('TEST');
    await inputs[1].setValue('Testing Category');

    const submitBtn = wrapper.findAll('button').find(b => b.text().includes('Buat Kategori'));
    expect(submitBtn?.exists()).toBe(true);

    await submitBtn?.trigger('click');

    expect(mockPost).toHaveBeenCalledTimes(1);
    const [url, options] = mockPost.mock.calls[0];
    expect(url).toBe('/mock-smart.master.categories.store');
    expect(options.preserveScroll).toBe(true);

    // Call onSuccess
    options.onSuccess();
    expect(wrapper.emitted('update:open')).toBeTruthy();
    expect(wrapper.emitted('update:open')![0]).toEqual([false]);
    expect(wrapper.emitted('close')).toBeTruthy();
  });

  it('submits form with preserveScroll: true in edit mode and closes modal on success', async () => {
    mockPut.mockClear();
    const wrapper = mountModal({
      mode: 'edit',
      activeTab: 'categories',
      item: { id: 1, code: 'TEST', name: 'Existing Category' },
    });

    const submitBtn = wrapper.findAll('button').find(b => b.text().includes('Simpan Perubahan'));
    expect(submitBtn?.exists()).toBe(true);

    await submitBtn?.trigger('click');

    expect(mockPut).toHaveBeenCalledTimes(1);
    const [url, options] = mockPut.mock.calls[0];
    expect(url).toBe('/mock-smart.master.categories.update');
    expect(options.preserveScroll).toBe(true);

    // Call onSuccess
    options.onSuccess();
    expect(wrapper.emitted('update:open')).toBeTruthy();
    expect(wrapper.emitted('update:open')![0]).toEqual([false]);
    expect(wrapper.emitted('close')).toBeTruthy();
  });
});

