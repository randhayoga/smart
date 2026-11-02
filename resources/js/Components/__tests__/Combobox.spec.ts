import { describe, it, expect, beforeEach } from 'vitest';
import { mount } from '@vue/test-utils';
import Combobox from '../Combobox.vue';
import { i18n, setI18nLanguage } from '@/locales';

describe('Combobox.vue', () => {
  const options = [
    { id: 1, name: 'Option 1' },
    { id: 2, name: 'Option 2' },
    { id: 3, name: 'Option 3' },
  ];

  beforeEach(() => {
    setI18nLanguage('en');
  });

  const mountCombobox = (props = {}) => {
    return mount(Combobox, {
      props: {
        modelValue: null,
        options,
        ...props,
      },
      global: {
        plugins: [i18n],
      },
    });
  };

  it('renders default placeholder "Choose" in English when unselected and no placeholder or defaultLabel is given', () => {
    setI18nLanguage('en');
    const wrapper = mountCombobox();

    expect(wrapper.find('button[role="combobox"]').text()).toContain('Choose');
  });

  it('renders default placeholder "Pilih" in Indonesian when unselected and no placeholder or defaultLabel is given', () => {
    setI18nLanguage('id');
    const wrapper = mountCombobox();

    expect(wrapper.find('button[role="combobox"]').text()).toContain('Pilih');
  });

  it('renders custom placeholder when provided and unselected', () => {
    const wrapper = mountCombobox({
      placeholder: 'Select Category',
    });

    expect(wrapper.find('button[role="combobox"]').text()).toContain('Select Category');
  });

  it('renders custom defaultLabel when provided (filter mode)', () => {
    const wrapper = mountCombobox({
      defaultLabel: 'All Brands',
    });

    expect(wrapper.find('button[role="combobox"]').text()).toContain('All Brands');
  });

  it('renders selected option name when modelValue is provided', () => {
    const wrapper = mountCombobox({
      modelValue: 2,
    });

    expect(wrapper.find('button[role="combobox"]').text()).toContain('Option 2');
  });

  it('renders string options properly when selected', () => {
    const wrapper = mountCombobox({
      options: ['Red', 'Green', 'Blue'],
      modelValue: 'Green',
    });

    expect(wrapper.find('button[role="combobox"]').text()).toContain('Green');
  });
});
