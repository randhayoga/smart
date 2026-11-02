import { describe, it, expect, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import CurrencyInput from '../CurrencyInput.vue';

describe('CurrencyInput.vue', () => {
  it('renders with default prefix Rp and empty value', () => {
    const wrapper = mount(CurrencyInput, {
      props: {
        id: 'price-input',
        modelValue: '',
      },
    });

    expect(wrapper.find('span').text()).toBe('Rp');
    const input = wrapper.find('input');
    expect(input.attributes('id')).toBe('price-input');
    expect((input.element as HTMLInputElement).value).toBe('');
  });

  it('formats initial numeric modelValue with thousand separators', () => {
    const wrapper = mount(CurrencyInput, {
      props: {
        modelValue: 15000000,
      },
    });

    const input = wrapper.find('input').element as HTMLInputElement;
    // Should contain thousand separators (either . or , depending on environment locale)
    expect(input.value).toMatch(/^15[.,]000[.,]000$/);
  });

  it('prevents keydown event when typing dots or commas', async () => {
    const wrapper = mount(CurrencyInput, {
      props: {
        modelValue: 1000,
      },
    });

    const input = wrapper.find('input');

    // Test dot '.'
    const dotEvent = new KeyboardEvent('keydown', { key: '.', cancelable: true });
    input.element.dispatchEvent(dotEvent);
    expect(dotEvent.defaultPrevented).toBe(true);

    // Test comma ','
    const commaEvent = new KeyboardEvent('keydown', { key: ',', cancelable: true });
    input.element.dispatchEvent(commaEvent);
    expect(commaEvent.defaultPrevented).toBe(true);

    // Test letter 'a'
    const letterEvent = new KeyboardEvent('keydown', { key: 'a', cancelable: true });
    input.element.dispatchEvent(letterEvent);
    expect(letterEvent.defaultPrevented).toBe(true);

    // Test digit '5' (should not be prevented)
    const digitEvent = new KeyboardEvent('keydown', { key: '5', cancelable: true });
    input.element.dispatchEvent(digitEvent);
    expect(digitEvent.defaultPrevented).toBe(false);
  });

  it('formats on input and emits numeric modelValue', async () => {
    const wrapper = mount(CurrencyInput, {
      props: {
        modelValue: '',
      },
    });

    const input = wrapper.find('input');
    (input.element as HTMLInputElement).value = '2500000';
    await input.trigger('input');

    expect(wrapper.emitted('update:modelValue')).toBeTruthy();
    expect(wrapper.emitted('update:modelValue')![0]).toEqual([2500000]);
    expect((input.element as HTMLInputElement).value).toMatch(/^2[.,]500[.,]000$/);
  });

  it('clears display and emits empty string when cleared', async () => {
    const wrapper = mount(CurrencyInput, {
      props: {
        modelValue: 5000,
      },
    });

    const input = wrapper.find('input');
    (input.element as HTMLInputElement).value = '';
    await input.trigger('input');

    expect(wrapper.emitted('update:modelValue')).toBeTruthy();
    expect(wrapper.emitted('update:modelValue')![0]).toEqual(['']);
    expect((input.element as HTMLInputElement).value).toBe('');
  });

  it('handles paste by stripping non-digit characters and formatting', async () => {
    const wrapper = mount(CurrencyInput, {
      props: {
        modelValue: '',
      },
    });

    const input = wrapper.find('input');
    const clipboardData = {
      getData: vi.fn().mockReturnValue('Rp 1.250.000'),
    };

    const pasteEvent = new Event('paste', { cancelable: true });
    Object.defineProperty(pasteEvent, 'clipboardData', { value: clipboardData });
    input.element.dispatchEvent(pasteEvent);
    await wrapper.vm.$nextTick();

    expect(wrapper.emitted('update:modelValue')).toBeTruthy();
    expect(wrapper.emitted('update:modelValue')![0]).toEqual([1250000]);
    expect((input.element as HTMLInputElement).value).toMatch(/^1[.,]250[.,]000$/);
  });

  it('applies error styling when error prop is true', () => {
    const wrapper = mount(CurrencyInput, {
      props: {
        error: true,
      },
    });

    expect(wrapper.classes()).toContain('border-destructive');
  });

  it('disables input when disabled prop is true', () => {
    const wrapper = mount(CurrencyInput, {
      props: {
        disabled: true,
      },
    });

    const input = wrapper.find('input');
    expect(input.attributes('disabled')).toBeDefined();
    expect(wrapper.classes()).toContain('opacity-50');
  });
});
