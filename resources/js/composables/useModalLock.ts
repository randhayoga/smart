/**
 * Composable to manage modal scroll locking and inert state on background content.
 * 
 * Features:
 * - Renders `<div id="app">` completely inert (pointer-events, tab navigation, screen readers disabled).
 * - Completely locks body scrolling (`overflow: hidden`).
 * - Compensates for scrollbar layout shift via body padding-right.
 * - Manages modal stacks (multiple/nested modals) via reference counting so background remains locked
 *   until all modals are closed.
 * - Safely cleans up on component unmount.
 */
import { watch, onUnmounted, toValue, type MaybeRefOrGetter } from 'vue';

let instanceSeq = 0;
const activeModalIds = new Set<string>();
let originalOverflow = '';
let originalPaddingRight = '';
let hasStoredOriginalStyles = false;

function applyLock(): void {
  if (typeof document === 'undefined' || typeof window === 'undefined') return;

  const appEl = document.getElementById('app');
  if (appEl) {
    appEl.setAttribute('inert', '');
  }

  if (!hasStoredOriginalStyles) {
    originalOverflow = document.body.style.overflow;
    originalPaddingRight = document.body.style.paddingRight;
    hasStoredOriginalStyles = true;
  }

  // Calculate vertical scrollbar width to prevent layout shift
  const scrollbarWidth = window.innerWidth - document.documentElement.clientWidth;
  if (scrollbarWidth > 0) {
    document.body.style.paddingRight = `${scrollbarWidth}px`;
  }

  document.body.style.overflow = 'hidden';
}

function removeLock(): void {
  if (typeof document === 'undefined') return;

  const appEl = document.getElementById('app');
  if (appEl) {
    appEl.removeAttribute('inert');
  }

  if (hasStoredOriginalStyles) {
    document.body.style.overflow = originalOverflow;
    document.body.style.paddingRight = originalPaddingRight;
    hasStoredOriginalStyles = false;
  } else {
    document.body.style.overflow = '';
    document.body.style.paddingRight = '';
  }
}

/**
 * Register an active modal instance by its unique ID.
 */
export function registerModal(id: string): void {
  const previousCount = activeModalIds.size;
  activeModalIds.add(id);

  if (previousCount === 0 && activeModalIds.size === 1) {
    applyLock();
  }
}

/**
 * Unregister an active modal instance by its unique ID.
 */
export function unregisterModal(id: string): void {
  const previousCount = activeModalIds.size;
  activeModalIds.delete(id);

  if (previousCount > 0 && activeModalIds.size === 0) {
    removeLock();
  }
}

/**
 * Get current count of active open modals.
 */
export function getActiveModalCount(): number {
  return activeModalIds.size;
}

/**
 * Reset modal lock state (primarily for test cleanup).
 */
export function resetModalLockState(): void {
  activeModalIds.clear();
  removeLock();
  hasStoredOriginalStyles = false;
  originalOverflow = '';
  originalPaddingRight = '';
}

/**
 * Composable hook to link a modal's open state to background inertness and scroll lock.
 *
 * @param isOpen Optional reactive boolean ref/computed/getter indicating whether the modal is open.
 *               If omitted, defaults to active on mount and inactive on unmount.
 */
export function useModalLock(isOpen?: MaybeRefOrGetter<boolean>) {
  const modalId = `modal-lock-${++instanceSeq}`;
  let isCurrentlyRegistered = false;

  const setOpen = (open: boolean) => {
    if (open && !isCurrentlyRegistered) {
      registerModal(modalId);
      isCurrentlyRegistered = true;
    } else if (!open && isCurrentlyRegistered) {
      unregisterModal(modalId);
      isCurrentlyRegistered = false;
    }
  };

  if (isOpen !== undefined) {
    watch(
      () => Boolean(toValue(isOpen)),
      (val) => {
        setOpen(val);
      },
      { immediate: true }
    );
  } else {
    // If no reactive argument is passed, assume active as long as the component is mounted
    setOpen(true);
  }

  onUnmounted(() => {
    if (isCurrentlyRegistered) {
      unregisterModal(modalId);
      isCurrentlyRegistered = false;
    }
  });

  return {
    modalId,
    getActiveModalCount,
  };
}
