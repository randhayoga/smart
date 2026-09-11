import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount } from '@vue/test-utils';
import LanguageSelector from '../LanguageSelector.vue';
import { i18n, setI18nLanguage } from '@/locales';
import { router } from '@inertiajs/vue3';

// Mock ziggy route helper
(global as any).route = vi.fn((name: string) => `/${name}`);

// Mock router.post
vi.mock('@inertiajs/vue3', () => ({
  router: {
    post: vi.fn(),
  },
  usePage: vi.fn(() => ({
    props: {
      locale: 'id',
    },
  })),
}));

describe('LanguageSelector.vue', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    setI18nLanguage('id');
  });

  it('renders trigger button displaying ID when locale is id', () => {
    const wrapper = mount(LanguageSelector, {
      global: {
        plugins: [i18n],
      },
    });

    expect(wrapper.text()).toContain('ID');
    const button = wrapper.find('button');
    expect(button.exists()).toBe(true);
    expect(button.attributes('aria-label')).toBe('Pilih Bahasa / Select Language');
  });

  it('renders EN when locale is updated to en', async () => {
    setI18nLanguage('en');
    const wrapper = mount(LanguageSelector, {
      global: {
        plugins: [i18n],
      },
    });

    expect(wrapper.text()).toContain('EN');
  });
});
