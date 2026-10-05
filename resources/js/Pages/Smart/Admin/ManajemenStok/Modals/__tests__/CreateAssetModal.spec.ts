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

    // Only initial valid condition options should be present: Bagus, Rusak, QC Passed
    expect(wrapper.text()).toContain('Bagus');
    expect(wrapper.text()).toContain('Rusak');
    expect(wrapper.text()).toContain('QC Passed');

    // Lelang/Hibah, Rusak Total, Hilang must NOT be present in creation modal
    expect(wrapper.text()).not.toContain('Lelang/Hibah');
    expect(wrapper.text()).not.toContain('Rusak Total');
    expect(wrapper.text()).not.toContain('Hilang');

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

  it('renders type and classification as optional without required asterisk', () => {
    setI18nLanguage('id');
    const wrapper = mountModal();

    const labels = wrapper.findAll('label');
    const typeLabel = labels.find(l => l.text().includes('Tipe'));
    const classificationLabel = labels.find(l => l.text().includes('Klasifikasi'));

    expect(typeLabel?.text()).not.toContain('*');
    expect(classificationLabel?.text()).not.toContain('*');
  });

  it('matches LOT default location when clicking Samakan button for location', async () => {
    setI18nLanguage('id');
    const wrapper = mountModal({
      lot: {
        id: 1,
        location_id: 1,
      },
    });

    const vm = wrapper.vm as any;
    expect(vm.form.location_id).toBe('');

    const sameAsParentButtons = wrapper.findAll('button').filter(b => b.text().trim() === 'Samakan');
    const locationSameBtn = sameAsParentButtons[0];
    await locationSameBtn.trigger('click');

    expect(vm.form.location_id).toBe(1);
  });

  it('renders remove photo button and clicking it clears selected or inherited photo', async () => {
    setI18nLanguage('id');
    const wrapper = mountModal({
      lot: {
        id: 1,
        imageUrl: 'lots/sample-lot.jpg',
      },
    });

    // Delete photo button exists
    expect(wrapper.find('button[title="Hapus Foto"]').attributes('disabled')).toBeDefined();

    // Click "Samakan" for photo (third Samakan button: location, price, photo)
    const sameAsParentButtons = wrapper.findAll('button').filter(b => b.text().trim() === 'Samakan');
    const photoSameAsParentBtn = sameAsParentButtons[2] || sameAsParentButtons[sameAsParentButtons.length - 1];
    await photoSameAsParentBtn.trigger('click');

    // Delete button should now be enabled
    const enabledDeleteBtn = wrapper.find('button[title="Hapus Foto"]');
    expect(enabledDeleteBtn.attributes('disabled')).toBeUndefined();
    expect(wrapper.text()).toContain('sample-lot.jpg');

    // Click delete photo
    await enabledDeleteBtn.trigger('click');

    // Photo should be cleared and delete button disabled again
    expect(wrapper.text()).toContain('Belum ada foto yang dipilih');
    expect(wrapper.find('button[title="Hapus Foto"]').attributes('disabled')).toBeDefined();
  });
});
