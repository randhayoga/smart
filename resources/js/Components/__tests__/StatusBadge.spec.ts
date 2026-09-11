import { describe, it, expect, beforeEach } from 'vitest';
import { mount } from '@vue/test-utils';
import StatusBadge from '../StatusBadge.vue';
import { i18n, setI18nLanguage } from '@/locales';
import { getLocalizedRequestStatusLabel } from '@/lib/requestStatus';

describe('StatusBadge.vue localization', () => {
  beforeEach(() => {
    setI18nLanguage('id');
  });

  it('renders Indonesian status by default', () => {
    const wrapper = mount(StatusBadge, {
      props: {
        status: 'Tersedia',
      },
      global: {
        plugins: [i18n],
      },
    });

    expect(wrapper.text()).toBe('Tersedia');
  });

  it('renders English status when locale is en', () => {
    setI18nLanguage('en');
    const wrapper = mount(StatusBadge, {
      props: {
        status: 'Tersedia',
      },
      global: {
        plugins: [i18n],
      },
    });

    expect(wrapper.text()).toBe('Available');
  });

  it('translates damaged status correctly in English', () => {
    setI18nLanguage('en');
    const wrapper = mount(StatusBadge, {
      props: {
        status: 'Rusak Total',
      },
      global: {
        plugins: [i18n],
      },
    });

    expect(wrapper.text()).toBe('Beyond Repair');
  });

  it('localizes request status via requestStatus utility', () => {
    setI18nLanguage('id');
    expect(getLocalizedRequestStatusLabel('Menunggu approval')).toBe('Menunggu approval');

    setI18nLanguage('en');
    expect(getLocalizedRequestStatusLabel('Menunggu approval')).toBe('Pending Approval');
    expect(getLocalizedRequestStatusLabel('Serah Terima')).toBe('Handover');
  });
});
