import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import PageSkeleton from '../PageSkeleton.vue';
import TablePageSkeleton from '../TablePageSkeleton.vue';

describe('PageSkeleton Component', () => {
  it('dispatches to cardless TablePageSkeleton for /smart/employee-list', () => {
    const wrapper = mount(PageSkeleton, {
      props: { path: '/smart/employee-list' },
    });

    const tableSkeleton = wrapper.findComponent(TablePageSkeleton);
    expect(tableSkeleton.exists()).toBe(true);
    expect(tableSkeleton.props('cardless')).toBe(true);
    expect(tableSkeleton.props('hasTitle')).toBe(true);
    expect(tableSkeleton.props('hasBreadcrumbs')).toBe(false);
  });

  it('dispatches to cardless TablePageSkeleton for /smart/stock-management/pending-activation', () => {
    const wrapper = mount(PageSkeleton, {
      props: { path: '/smart/stock-management/pending-activation' },
    });

    const tableSkeleton = wrapper.findComponent(TablePageSkeleton);
    expect(tableSkeleton.exists()).toBe(true);
    expect(tableSkeleton.props('cardless')).toBe(true);
    expect(tableSkeleton.props('hasTitle')).toBe(true);
    expect(tableSkeleton.props('hasBreadcrumbs')).toBe(false);
    expect(tableSkeleton.props('filterCount')).toBe(3);
  });

  it('dispatches to carded TablePageSkeleton with 2 tabs for /smart/stock-management/pending-deletion', () => {
    const wrapper = mount(PageSkeleton, {
      props: { path: '/smart/stock-management/pending-deletion' },
    });

    const tableSkeleton = wrapper.findComponent(TablePageSkeleton);
    expect(tableSkeleton.exists()).toBe(true);
    expect(tableSkeleton.props('cardless')).toBe(false);
    expect(tableSkeleton.props('hasTabs')).toBe(true);
    expect(tableSkeleton.props('tabCount')).toBe(2);
    expect(tableSkeleton.props('hasBreadcrumbs')).toBe(false);
  });

  it('dispatches to cardless TablePageSkeleton for /smart/approve-activation', () => {
    const wrapper = mount(PageSkeleton, {
      props: { path: '/smart/approve-activation' },
    });

    const tableSkeleton = wrapper.findComponent(TablePageSkeleton);
    expect(tableSkeleton.exists()).toBe(true);
    expect(tableSkeleton.props('cardless')).toBe(true);
    expect(tableSkeleton.props('hasTitle')).toBe(true);
    expect(tableSkeleton.props('hasBreadcrumbs')).toBe(false);
  });
});

describe('TablePageSkeleton Component', () => {
  it('renders card enclosure when cardless is false', () => {
    const wrapper = mount(TablePageSkeleton, {
      props: { cardless: false },
    });

    expect(wrapper.find('.p-4.bg-card').exists()).toBe(true);
  });

  it('omits card enclosure and renders white inputs when cardless is true', () => {
    const wrapper = mount(TablePageSkeleton, {
      props: { cardless: true },
    });

    expect(wrapper.find('.p-4.bg-card').exists()).toBe(false);
    expect(wrapper.find('.bg-white').exists()).toBe(true);
  });
});
