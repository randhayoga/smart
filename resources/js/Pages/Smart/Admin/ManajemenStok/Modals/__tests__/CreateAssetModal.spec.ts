import { describe, it, expect, beforeEach, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import CreateAssetModal from '../CreateAssetModal.vue';
import { i18n, setI18nLanguage } from '@/locales';

describe('CreateAssetModal.vue', () => {
  beforeEach(() => {
    setI18nLanguage('id');
  });

  const sampleProps = {
    open: true,
    lot: {
      id: 1,
      organizer: 'PTRE',
      date_of_receipt: '2026-09-01',
      next_asset_code: '00001-LP-PTRE-26',
      imageUrl: null,
      price: 15000000,
    },
    units: [],
    barang: {
      id: 1,
      category: 'Elektronik',
      subcategory_code: 'LP',
      name: 'Laptop ThinkPad',
    },
    locations: [
      { id: 1, name: 'Gd. Menara' },
    ],
    floors: [],
    rooms: [],
  };

  const mountModal = (props: any = {}) => {
    return mount(CreateAssetModal, {
      props: {
        ...sampleProps,
        ...props,
      },
      global: {
        plugins: [i18n],
        stubs: {
          Teleport: true,
          Transition: false,
          LocationCombobox: {
            template: '<div data-testid="location-combobox"></div>',
          },
          Combobox: {
            template: '<div data-testid="combobox"></div>',
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

  it('renders with status forced to Belum Diverifikasi and input disabled', () => {
    const wrapper = mountModal();

    // Find the status input
    const statusInputs = wrapper.findAll('input[disabled]');
    const statusInput = statusInputs.find(input => 
      (input.element as HTMLInputElement).value === 'Belum Diverifikasi'
    );

    expect(statusInput).toBeDefined();
    expect(statusInput?.attributes('disabled')).toBeDefined();
  });

  it('renders localized status Unverified when language is en', async () => {
    setI18nLanguage('en');
    const wrapper = mountModal();

    const statusInputs = wrapper.findAll('input[disabled]');
    const statusInput = statusInputs.find(input => 
      (input.element as HTMLInputElement).value === 'Unverified'
    );

    expect(statusInput).toBeDefined();
    expect(statusInput?.attributes('disabled')).toBeDefined();
  });

  it('initializes condition as empty and displays select placeholder', () => {
    setI18nLanguage('id');
    const wrapper = mountModal();

    expect(wrapper.text()).toContain('Pilih kondisi');
  });

  it('contains available physical condition options in template and selecting one keeps status unchanged', async () => {
    setI18nLanguage('id');
    const wrapper = mountModal();

    // Condition options should include Bagus, Rusak, QC Passed, Lelang/Hibah, Rusak Total, Hilang
    expect(wrapper.text()).toContain('Bagus');
    expect(wrapper.text()).toContain('Rusak');
    expect(wrapper.text()).toContain('QC Passed');
    expect(wrapper.text()).toContain('Lelang/Hibah');
    expect(wrapper.text()).toContain('Rusak Total');
    expect(wrapper.text()).toContain('Hilang');

    // Find condition menu items
    const menuItems = wrapper.findAll('[role="menuitem"]');
    const goodItem = menuItems.find(item => item.text().trim() === 'Bagus');
    expect(goodItem).toBeDefined();

    // Click to select 'Bagus'
    await goodItem?.trigger('click');

    // Status input must still be 'Belum Diverifikasi'
    const statusInputs = wrapper.findAll('input[disabled]');
    const statusInput = statusInputs.find(input => 
      (input.element as HTMLInputElement).value === 'Belum Diverifikasi'
    );
    expect(statusInput).toBeDefined();
  });
});
