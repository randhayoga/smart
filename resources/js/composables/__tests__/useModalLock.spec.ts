import { describe, it, expect, beforeEach, afterEach } from 'vitest';
import { ref, defineComponent, h, nextTick } from 'vue';
import { mount as testMount } from '@vue/test-utils';
import { useModalLock, getActiveModalCount, resetModalLockState } from '../useModalLock';

describe('useModalLock composable', () => {
  let appEl: HTMLDivElement;

  beforeEach(() => {
    resetModalLockState();
    document.body.innerHTML = '<div id="app"><h1>Main Content</h1><button id="btn">Click</button></div>';
    appEl = document.getElementById('app') as HTMLDivElement;
    document.body.style.overflow = '';
    document.body.style.paddingRight = '';
  });

  afterEach(() => {
    resetModalLockState();
    document.body.innerHTML = '';
  });

  it('should lock body scroll and set inert attribute on #app when modal is open', async () => {
    const isOpen = ref(false);

    const TestComponent = defineComponent({
      setup() {
        useModalLock(isOpen);
        return () => h('div', 'Modal');
      },
    });

    const wrapper = testMount(TestComponent);

    expect(getActiveModalCount()).toBe(0);
    expect(document.body.style.overflow).toBe('');
    expect(appEl.hasAttribute('inert')).toBe(false);

    isOpen.value = true;
    await nextTick();

    expect(getActiveModalCount()).toBe(1);
    expect(document.body.style.overflow).toBe('hidden');
    expect(appEl.hasAttribute('inert')).toBe(true);

    isOpen.value = false;
    await nextTick();

    expect(getActiveModalCount()).toBe(0);
    expect(document.body.style.overflow).toBe('');
    expect(appEl.hasAttribute('inert')).toBe(false);

    wrapper.unmount();
  });

  it('should handle nested/stacked modals correctly', async () => {
    const modal1Open = ref(false);
    const modal2Open = ref(false);

    const Modal1Component = defineComponent({
      setup() {
        useModalLock(modal1Open);
        return () => h('div', 'Modal 1');
      },
    });

    const Modal2Component = defineComponent({
      setup() {
        useModalLock(modal2Open);
        return () => h('div', 'Modal 2');
      },
    });

    const wrapper1 = testMount(Modal1Component);
    const wrapper2 = testMount(Modal2Component);

    // Open Modal 1
    modal1Open.value = true;
    await nextTick();

    expect(getActiveModalCount()).toBe(1);
    expect(document.body.style.overflow).toBe('hidden');
    expect(appEl.hasAttribute('inert')).toBe(true);

    // Open Modal 2 (nested)
    modal2Open.value = true;
    await nextTick();

    expect(getActiveModalCount()).toBe(2);
    expect(document.body.style.overflow).toBe('hidden');
    expect(appEl.hasAttribute('inert')).toBe(true);

    // Close Modal 2 first
    modal2Open.value = false;
    await nextTick();

    // Modal 1 is still open -> still locked!
    expect(getActiveModalCount()).toBe(1);
    expect(document.body.style.overflow).toBe('hidden');
    expect(appEl.hasAttribute('inert')).toBe(true);

    // Close Modal 1
    modal1Open.value = false;
    await nextTick();

    // All closed -> unlocked
    expect(getActiveModalCount()).toBe(0);
    expect(document.body.style.overflow).toBe('');
    expect(appEl.hasAttribute('inert')).toBe(false);

    wrapper1.unmount();
    wrapper2.unmount();
  });

  it('should automatically release lock when component unmounts while open', async () => {
    const isOpen = ref(true);

    const TestComponent = defineComponent({
      setup() {
        useModalLock(isOpen);
        return () => h('div', 'Modal');
      },
    });

    const wrapper = testMount(TestComponent);
    await nextTick();

    expect(getActiveModalCount()).toBe(1);
    expect(document.body.style.overflow).toBe('hidden');
    expect(appEl.hasAttribute('inert')).toBe(true);

    // Component is unmounted abruptly (e.g. route transition or v-if)
    wrapper.unmount();
    await nextTick();

    expect(getActiveModalCount()).toBe(0);
    expect(document.body.style.overflow).toBe('');
    expect(appEl.hasAttribute('inert')).toBe(false);
  });

  it('should support default active when called without arguments (e.g. inside unconditional modal component)', async () => {
    const DialogComponent = defineComponent({
      setup() {
        useModalLock();
        return () => h('div', 'DialogContent');
      },
    });

    const wrapper = testMount(DialogComponent);
    await nextTick();

    expect(getActiveModalCount()).toBe(1);
    expect(document.body.style.overflow).toBe('hidden');
    expect(appEl.hasAttribute('inert')).toBe(true);

    wrapper.unmount();
    await nextTick();

    expect(getActiveModalCount()).toBe(0);
    expect(document.body.style.overflow).toBe('');
    expect(appEl.hasAttribute('inert')).toBe(false);
  });

  it('should NOT lock background when DialogContent is mounted with Dialog open=false, but lock when open=true', async () => {
    const { Dialog, DialogContent } = await import('@/Components/ui/dialog');

    const isOpen = ref(false);
    const ParentComponent = defineComponent({
      setup() {
        return () =>
          h(Dialog, { open: isOpen.value }, {
            default: () => h(DialogContent, null, { default: () => 'Modal Body' }),
          });
      },
    });

    const wrapper = testMount(ParentComponent);
    await nextTick();

    // Dialog is mounted but open=false: MUST NOT be locked!
    expect(getActiveModalCount()).toBe(0);
    expect(document.body.style.overflow).toBe('');
    expect(appEl.hasAttribute('inert')).toBe(false);

    // Now open the dialog
    isOpen.value = true;
    await nextTick();

    expect(getActiveModalCount()).toBe(1);
    expect(document.body.style.overflow).toBe('hidden');
    expect(appEl.hasAttribute('inert')).toBe(true);

    // Close the dialog
    isOpen.value = false;
    await nextTick();

    expect(getActiveModalCount()).toBe(0);
    expect(document.body.style.overflow).toBe('');
    expect(appEl.hasAttribute('inert')).toBe(false);

    wrapper.unmount();
  });

  it('should NOT lock background when DialogScrollContent is mounted with Dialog open=false, but lock when open=true', async () => {
    const { Dialog, DialogScrollContent } = await import('@/Components/ui/dialog');

    const isOpen = ref(false);
    const ParentComponent = defineComponent({
      setup() {
        return () =>
          h(Dialog, { open: isOpen.value }, {
            default: () => h(DialogScrollContent, null, { default: () => 'Modal Scroll Body' }),
          });
      },
    });

    const wrapper = testMount(ParentComponent);
    await nextTick();

    expect(getActiveModalCount()).toBe(0);
    expect(document.body.style.overflow).toBe('');
    expect(appEl.hasAttribute('inert')).toBe(false);

    isOpen.value = true;
    await nextTick();

    expect(getActiveModalCount()).toBe(1);
    expect(document.body.style.overflow).toBe('hidden');
    expect(appEl.hasAttribute('inert')).toBe(true);

    isOpen.value = false;
    await nextTick();

    expect(getActiveModalCount()).toBe(0);
    expect(document.body.style.overflow).toBe('');
    expect(appEl.hasAttribute('inert')).toBe(false);

    wrapper.unmount();
  });
});
