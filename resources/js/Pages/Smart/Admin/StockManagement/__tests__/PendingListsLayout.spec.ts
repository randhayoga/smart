import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount } from '@vue/test-utils';
import { i18n, setI18nLanguage } from '@/locales';
import PendingActivationList from '../PendingActivationList.vue';
import PendingDeletionList from '../PendingDeletionList.vue';

// Mock layout
vi.mock('@/Layouts/AppLayout.vue', () => ({
  default: {
    name: 'AppLayout',
    template: '<div><slot /></div>',
  },
}));

// Mock NonConsumableListTab to verify props passed
vi.mock('../Tabs/NonConsumableListTab.vue', () => ({
  default: {
    name: 'NonConsumableListTab',
    props: ['units', 'locations', 'organizers', 'vendors', 'statusScope', 'hideStatusFilter', 'hideConditionFilter', 'hideActions', 'hideExport', 'cardless', 'hideHeader', 'title'],
    template: '<div data-testid="non-consumable-list-tab" :data-cardless="cardless" :data-hide-header="hideHeader" :data-title="title"></div>',
  },
}));

vi.mock('@/Components/Tabs.vue', () => ({
  default: {
    name: 'Tabs',
    props: ['modelValue', 'tabs'],
    template: '<div data-testid="tabs"><button v-for="tab in tabs" :key="tab.id" :data-tab="tab.id" @click="$emit(\'update:modelValue\', tab.id)">{{ tab.label }}</button></div>',
  },
}));

describe('Pending Lists Layout & Title Tests', () => {
  beforeEach(() => {
    setI18nLanguage('id');
    vi.clearAllMocks();
  });

  describe('PendingActivationList.vue', () => {
    it('does not contain any Breadcrumb component or markup', () => {
      const wrapper = mount(PendingActivationList, {
        props: {
          units: [],
          locations: [],
        },
        global: {
          plugins: [i18n],
        },
      });

      expect(wrapper.find('nav[aria-label="breadcrumb"]').exists()).toBe(false);
      expect(wrapper.find('.breadcrumb').exists()).toBe(false);
    });

    it('renders page title as "Daftar Pending Aktivasi" in Indonesian and "Activation Pending List" in English', async () => {
      setI18nLanguage('id');
      const wrapper = mount(PendingActivationList, {
        props: { units: [], locations: [] },
        global: { plugins: [i18n] },
      });

      const h1 = wrapper.find('h1');
      expect(h1.exists()).toBe(true);
      expect(h1.text()).toBe('Daftar Pending Aktivasi');

      setI18nLanguage('en');
      await wrapper.vm.$nextTick();
      expect(wrapper.find('h1').text()).toBe('Activation Pending List');
    });

    it('passes cardless=true and hideHeader=true to NonConsumableListTab', () => {
      const wrapper = mount(PendingActivationList, {
        props: { units: [], locations: [] },
        global: { plugins: [i18n] },
      });

      const tab = wrapper.findComponent({ name: 'NonConsumableListTab' });
      expect(tab.exists()).toBe(true);
      expect(tab.props('cardless')).toBe(true);
      expect(tab.props('hideHeader')).toBe(true);
    });
  });

  describe('PendingDeletionList.vue', () => {
    it('does not contain any Breadcrumb component or markup', () => {
      const wrapper = mount(PendingDeletionList, {
        props: { units: [], locations: [] },
        global: { plugins: [i18n] },
      });

      expect(wrapper.find('nav[aria-label="breadcrumb"]').exists()).toBe(false);
      expect(wrapper.find('.breadcrumb').exists()).toBe(false);
    });

    it('retains sub-tabs Pending:DM and Pending:BoD/BoC', () => {
      const wrapper = mount(PendingDeletionList, {
        props: { units: [], locations: [] },
        global: { plugins: [i18n] },
      });

      const tabs = wrapper.find('[data-testid="tabs"]');
      expect(tabs.exists()).toBe(true);
      expect(tabs.text()).toContain('Pending:DM');
      expect(tabs.text()).toContain('Pending:BoD/BoC');
    });

    it('passes title="Daftar Pending Penghapusan" (ID) and leaves cardless falsy to retain card design', async () => {
      setI18nLanguage('id');
      const wrapper = mount(PendingDeletionList, {
        props: { units: [], locations: [] },
        global: { plugins: [i18n] },
      });

      const tab = wrapper.findComponent({ name: 'NonConsumableListTab' });
      expect(tab.exists()).toBe(true);
      expect(tab.props('title')).toBe('Daftar Pending Penghapusan');
      expect(tab.props('cardless')).toBeFalsy();

      setI18nLanguage('en');
      await wrapper.vm.$nextTick();
      const tabEn = wrapper.findComponent({ name: 'NonConsumableListTab' });
      expect(tabEn.props('title')).toBe('Deletion Pending List');
    });
  });
});
