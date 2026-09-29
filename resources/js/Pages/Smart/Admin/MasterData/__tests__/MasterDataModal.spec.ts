import { describe, it, expect, beforeEach, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import MasterDataModal from '../Modals/MasterDataModal.vue';
import { i18n, setI18nLanguage } from '@/locales';

describe('MasterDataModal.vue', () => {
  beforeEach(() => {
    setI18nLanguage('id');
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
});
