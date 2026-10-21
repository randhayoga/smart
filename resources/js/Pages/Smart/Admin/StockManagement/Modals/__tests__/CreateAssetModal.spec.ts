import { describe, it, expect, beforeEach } from 'vitest';
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
    projects: [
      { id: 10, no_project: 'PRJ-01', project_name: 'Alpha Project', client_id: 'CL-1' },
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
            template: '<div data-testid="project-combobox"></div>',
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

  it('renders localized title Input Aset Baru (ID) and Input New Asset (EN)', () => {
    setI18nLanguage('id');
    const wrapperId = mountModal();
    expect(wrapperId.find('h3').text()).toBe('Input Aset Baru');

    setI18nLanguage('en');
    const wrapperEn = mountModal();
    expect(wrapperEn.find('h3').text()).toBe('Input New Asset');
  });

  it('removes Asset Number input, Status input, and all Match Parent (Samakan) buttons', () => {
    setI18nLanguage('id');
    const wrapper = mountModal();

    expect(wrapper.text()).not.toContain('Nomor Aset');
    expect(wrapper.text()).not.toContain('Status');
    const samakanBtns = wrapper.findAll('button').filter(b => b.text().trim() === 'Samakan');
    expect(samakanBtns.length).toBe(0);
  });

  it('uses Price (Harga) label instead of Unit Price (Harga Satuan)', () => {
    setI18nLanguage('id');
    const wrapperId = mountModal();
    expect(wrapperId.text()).toContain('Harga');
    expect(wrapperId.text()).not.toContain('Harga Satuan');

    setI18nLanguage('en');
    const wrapperEn = mountModal();
    expect(wrapperEn.text()).toContain('Price');
    expect(wrapperEn.text()).not.toContain('Unit Price');
  });

  it('only displays Bagus and Rusak options for Condition matching AssetSection.vue', async () => {
    setI18nLanguage('id');
    const wrapper = mountModal();

    expect(wrapper.text()).toContain('Pilih kondisi');
    expect(wrapper.text()).toContain('Bagus');
    expect(wrapper.text()).toContain('Rusak');
    expect(wrapper.text()).not.toContain('QC Passed');
    expect(wrapper.text()).not.toContain('Lelang/Hibah');
    expect(wrapper.text()).not.toContain('Rusak Total');
    expect(wrapper.text()).not.toContain('Hilang');

    const menuItems = wrapper.findAll('[role="menuitem"]');
    const goodItem = menuItems.find(item => item.text().trim() === 'Bagus');
    expect(goodItem).toBeDefined();
    await goodItem?.trigger('click');

    const vm = wrapper.vm as any;
    expect(vm.form.condition).toBe('Bagus');
    expect(vm.form.status).toBe('Belum Diverifikasi');
  });

  it('renders Borrowing Type, Classification, Condition, Location, and Burden with required asterisk', () => {
    setI18nLanguage('id');
    const wrapper = mountModal();

    const labels = wrapper.findAll('label');
    const typeLabel = labels.find(l => l.text().includes('Tipe Peminjaman'));
    const conditionLabel = labels.find(l => l.text().includes('Kondisi'));
    const locationLabel = labels.find(l => l.text().includes('Lokasi'));
    const classificationLabel = labels.find(l => l.text().includes('Klasifikasi'));
    const burdenLabel = labels.find(l => l.text().includes('Pembebanan'));

    expect(typeLabel?.text()).toContain('*');
    expect(conditionLabel?.text()).toContain('*');
    expect(locationLabel?.text()).toContain('*');
    expect(classificationLabel?.text()).toContain('*');
    expect(burdenLabel?.text()).toContain('*');
  });

  it('initializes Burden as Corporate, shows Project combobox when Project is selected, and validates Project', async () => {
    const wrapper = mountModal();
    const vm = wrapper.vm as any;

    expect(vm.form.burden).toBe('Corporate');
    expect(wrapper.find('[data-testid="project-combobox"]').exists()).toBe(false);

    const menuItems = wrapper.findAll('[role="menuitem"]');
    const projectItem = menuItems.find(item => item.text().trim() === 'Project');
    await projectItem?.trigger('click');

    expect(vm.form.burden).toBe('Project');
    expect(wrapper.find('[data-testid="project-combobox"]').exists()).toBe(true);

    vm.form.location_id = 1;
    vm.form.condition = 'Bagus';
    vm.form.type = 'LT';
    vm.form.classification = 'Aset';
    vm.form.project_id = '';

    vm.handleSubmit();
    expect(vm.errors.project_id).toBeTruthy();
  });

  it('shows Specification textarea and validates it as required when category is COMP', () => {
    const wrapper = mountModal({
      barang: { id: 1, category: 'Computer', category_code: 'COMP', subcategory_code: 'LP' },
    });
    const vm = wrapper.vm as any;

    expect(wrapper.find('textarea#create-unit-specification').exists()).toBe(true);

    vm.form.location_id = 1;
    vm.form.condition = 'Bagus';
    vm.form.type = 'LT';
    vm.form.classification = 'Aset';
    vm.form.specification = '';

    vm.handleSubmit();
    expect(vm.errors.specification).toBeTruthy();
  });

  it('renders remove photo button and clicking it clears selected photo', async () => {
    setI18nLanguage('id');
    const wrapper = mountModal();
    const vm = wrapper.vm as any;

    expect(wrapper.find('button[title="Hapus Foto"]').attributes('disabled')).toBeDefined();

    vm.form.image_url = new File(['dummy'], 'photo.jpg', { type: 'image/jpeg' });
    vm.form.image_url_name = 'photo.jpg';
    await wrapper.vm.$nextTick();

    const enabledDeleteBtn = wrapper.find('button[title="Hapus Foto"]');
    expect(enabledDeleteBtn.attributes('disabled')).toBeUndefined();
    expect(wrapper.text()).toContain('photo.jpg');

    await enabledDeleteBtn.trigger('click');

    expect(wrapper.text()).toContain('Belum ada foto yang dipilih');
    expect(wrapper.find('button[title="Hapus Foto"]').attributes('disabled')).toBeDefined();
  });
});
