import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount } from '@vue/test-utils';
import VendorContactPersonsSection, { type VendorCpItem } from '../VendorContactPersonsSection.vue';
import { i18n, setI18nLanguage } from '@/locales';

describe('VendorContactPersonsSection.vue', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    setI18nLanguage('en');
  });

  it('renders contact persons list and displays items properly', () => {
    const modelValue: VendorCpItem[] = [
      { contact_person: 'John Doe', cp_email: 'john@example.com', cp_phone: '081234', is_collapsed: false },
    ];

    const wrapper = mount(VendorContactPersonsSection, {
      props: {
        modelValue,
      },
      global: {
        plugins: [i18n],
      },
    });

    expect(wrapper.text()).toContain('Contact Person 1');
    const inputs = wrapper.findAll('input');
    expect(inputs.length).toBe(3);
    expect((inputs[0].element as HTMLInputElement).value).toBe('John Doe');
  });

  it('emits update:modelValue with added contact person when add button clicked', async () => {
    const modelValue: VendorCpItem[] = [];

    const wrapper = mount(VendorContactPersonsSection, {
      props: {
        modelValue,
      },
      global: {
        plugins: [i18n],
      },
    });

    const addButton = wrapper.find('button');
    await addButton.trigger('click');

    expect(wrapper.emitted('update:modelValue')).toBeTruthy();
    const emitted = wrapper.emitted('update:modelValue')![0][0] as VendorCpItem[];
    expect(emitted.length).toBe(1);
    expect(emitted[0].contact_person).toBe('');
  });

  it('emits update:modelValue without deleted item when remove button clicked', async () => {
    const modelValue: VendorCpItem[] = [
      { contact_person: 'First CP', cp_email: '', cp_phone: '', is_collapsed: false },
      { contact_person: 'Second CP', cp_email: '', cp_phone: '', is_collapsed: false },
    ];

    const wrapper = mount(VendorContactPersonsSection, {
      props: {
        modelValue,
      },
      global: {
        plugins: [i18n],
      },
    });

    const trashButtons = wrapper.findAll('button').filter(b => b.attributes('title')?.includes('Remove') || b.html().includes('Trash2') || b.classes().includes('hover:text-destructive'));
    expect(trashButtons.length).toBeGreaterThan(0);
    await trashButtons[0].trigger('click');

    expect(wrapper.emitted('update:modelValue')).toBeTruthy();
    const emitted = wrapper.emitted('update:modelValue')![0][0] as VendorCpItem[];
    expect(emitted.length).toBe(1);
    expect(emitted[0].contact_person).toBe('Second CP');
  });

  it('toggles collapse state on header click', async () => {
    const modelValue: VendorCpItem[] = [
      { contact_person: 'Jane Doe', cp_email: '', cp_phone: '', is_collapsed: false },
    ];

    const wrapper = mount(VendorContactPersonsSection, {
      props: {
        modelValue,
      },
      global: {
        plugins: [i18n],
      },
    });

    const header = wrapper.find('[data-testid="cp-accordion-header"]');
    await header.trigger('click');

    const emittedCalls = wrapper.emitted('update:modelValue');
    expect(emittedCalls).toBeTruthy();
    const emitted = emittedCalls![0][0] as VendorCpItem[];
    expect(emitted[0].is_collapsed).toBe(true);
  });

  it('filters non-numeric characters except +, -, and space on phone input', async () => {
    const modelValue: VendorCpItem[] = [
      { contact_person: 'Jane Doe', cp_email: '', cp_phone: '', is_collapsed: false },
    ];

    const wrapper = mount(VendorContactPersonsSection, {
      props: {
        modelValue,
      },
      global: {
        plugins: [i18n],
      },
    });

    const phoneInput = wrapper.findAll('input')[2];
    phoneInput.element.value = '+62 (812) 345-abc';
    await phoneInput.trigger('input');

    const emittedCalls = wrapper.emitted('update:modelValue');
    expect(emittedCalls).toBeTruthy();
    const emitted = emittedCalls![0][0] as VendorCpItem[];
    expect(emitted[0].cp_phone).toBe('+62 812 345-');
    expect(phoneInput.element.value).toBe('+62 812 345-');
  });
});
