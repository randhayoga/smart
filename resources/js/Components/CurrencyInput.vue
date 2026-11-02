<script setup lang="ts">
/**
 * Smart Currency Input component for formatted currency (Rupiah) values.
 * Automatically inserts thousand separators according to locale while strictly
 * disallowing manual input of separators or non-digit characters.
 */
import { ref, computed, watch, nextTick } from 'vue';
import { useI18n } from 'vue-i18n';

interface Props {
  modelValue?: string | number | null;
  id?: string;
  placeholder?: string;
  disabled?: boolean;
  error?: boolean;
  prefix?: string;
  class?: string;
}

const props = withDefaults(defineProps<Props>(), {
  modelValue: '',
  id: undefined,
  placeholder: '0',
  disabled: false,
  error: false,
  prefix: 'Rp',
  class: '',
});

const emit = defineEmits<{
  (e: 'update:modelValue', value: number | ''): void;
  (e: 'blur', event: FocusEvent): void;
  (e: 'focus', event: FocusEvent): void;
}>();

let i18nLocale: { value: string } | undefined;
try {
  const i18n = useI18n();
  i18nLocale = i18n.locale;
} catch {
  // Graceful fallback for non-i18n test environments
}

const getLocale = (): string => {
  return i18nLocale?.value === 'en' ? 'en-US' : 'id-ID';
};

const formatDigits = (digits: string): string => {
  if (!digits) return '';
  const num = parseInt(digits, 10);
  if (isNaN(num)) return '';
  return num.toLocaleString(getLocale());
};

const inputRef = ref<HTMLInputElement | null>(null);

const rawDigitsFromValue = (val: string | number | null | undefined): string => {
  if (val === null || val === undefined || val === '') return '';
  return String(val).replace(/\D/g, '');
};

const displayValue = ref<string>(formatDigits(rawDigitsFromValue(props.modelValue)));

watch(
  () => props.modelValue,
  (newVal) => {
    const digits = rawDigitsFromValue(newVal);
    const formatted = formatDigits(digits);
    if (formatted !== displayValue.value) {
      displayValue.value = formatted;
    }
  }
);

watch(
  () => i18nLocale?.value,
  () => {
    const digits = displayValue.value.replace(/\D/g, '');
    displayValue.value = formatDigits(digits);
  }
);

const restoreCursor = (targetDigitCount: number) => {
  const input = inputRef.value;
  if (!input) return;
  if (targetDigitCount <= 0) {
    input.setSelectionRange(0, 0);
    return;
  }
  let count = 0;
  let pos = displayValue.value.length;
  for (let i = 0; i < displayValue.value.length; i++) {
    if (/\d/.test(displayValue.value[i])) {
      count++;
    }
    if (count === targetDigitCount) {
      pos = i + 1;
      break;
    }
  }
  input.setSelectionRange(pos, pos);
};

const updateFromDigits = (digits: string, targetDigitCount: number) => {
  if (!digits) {
    displayValue.value = '';
    emit('update:modelValue', '');
    nextTick(() => restoreCursor(0));
    return;
  }

  const num = parseInt(digits, 10);
  if (isNaN(num)) {
    displayValue.value = '';
    emit('update:modelValue', '');
    nextTick(() => restoreCursor(0));
    return;
  }

  displayValue.value = num.toLocaleString(getLocale());
  emit('update:modelValue', num);
  nextTick(() => restoreCursor(targetDigitCount));
};

const onKeyDown = (e: KeyboardEvent) => {
  // Allow navigation, deletion, clipboard shortcuts
  const isControlKey = [
    'Backspace',
    'Delete',
    'Tab',
    'Escape',
    'Enter',
    'ArrowLeft',
    'ArrowRight',
    'ArrowUp',
    'ArrowDown',
    'Home',
    'End',
  ].includes(e.key);

  const isShortcut = (e.ctrlKey || e.metaKey) && ['a', 'c', 'v', 'x', 'z', 'r'].includes(e.key.toLowerCase());

  if (isShortcut || isControlKey) {
    const input = inputRef.value;
    if (!input) return;

    // Smart Backspace handling across thousand separators (e.g. '.' or ',')
    if (e.key === 'Backspace' && input.selectionStart === input.selectionEnd && input.selectionStart !== null) {
      const pos = input.selectionStart;
      if (pos > 0) {
        const charBefore = displayValue.value[pos - 1];
        if (!/\d/.test(charBefore)) {
          e.preventDefault();
          const digitsBefore = displayValue.value.slice(0, pos).replace(/\D/g, '');
          const allDigits = displayValue.value.replace(/\D/g, '');
          if (digitsBefore.length > 0) {
            const targetIndex = digitsBefore.length - 1;
            const newDigits = allDigits.slice(0, targetIndex) + allDigits.slice(targetIndex + 1);
            updateFromDigits(newDigits, targetIndex);
          }
        }
      }
    }

    // Smart Delete handling across thousand separators
    if (e.key === 'Delete' && input.selectionStart === input.selectionEnd && input.selectionStart !== null) {
      const pos = input.selectionStart;
      if (pos < displayValue.value.length) {
        const charAfter = displayValue.value[pos];
        if (!/\d/.test(charAfter)) {
          e.preventDefault();
          const digitsBefore = displayValue.value.slice(0, pos).replace(/\D/g, '');
          const allDigits = displayValue.value.replace(/\D/g, '');
          const targetIndex = digitsBefore.length;
          if (targetIndex < allDigits.length) {
            const newDigits = allDigits.slice(0, targetIndex) + allDigits.slice(targetIndex + 1);
            updateFromDigits(newDigits, targetIndex);
          }
        }
      }
    }

    return;
  }

  // Strictly disallow thousand / decimal separators (dots, commas) and any non-digit
  if (e.key === '.' || e.key === ',' || e.key === 'Decimal' || !/^\d$/.test(e.key)) {
    e.preventDefault();
  }
};

const onInput = (e: Event) => {
  const input = e.target as HTMLInputElement;
  const raw = input.value;
  const cursorPos = input.selectionStart ?? raw.length;
  const digitsBeforeCursor = raw.slice(0, cursorPos).replace(/\D/g, '').length;
  const digits = raw.replace(/\D/g, '');
  updateFromDigits(digits, digitsBeforeCursor);
};

const onPaste = (e: ClipboardEvent) => {
  e.preventDefault();
  const input = inputRef.value;
  if (!input) return;

  const pastedText = e.clipboardData?.getData('text') || '';
  const pastedDigits = pastedText.replace(/\D/g, '');
  if (!pastedDigits) return;

  const selStart = input.selectionStart ?? 0;
  const selEnd = input.selectionEnd ?? 0;
  const digitsBefore = displayValue.value.slice(0, selStart).replace(/\D/g, '');
  const digitsAfter = displayValue.value.slice(selEnd).replace(/\D/g, '');
  const newDigits = digitsBefore + pastedDigits + digitsAfter;

  updateFromDigits(newDigits, digitsBefore.length + pastedDigits.length);
};
</script>

<template>
  <div
    class="flex rounded-[14px] border bg-background focus-within:ring-2 transition-colors h-10 overflow-hidden"
    :class="[
      error
        ? 'border-destructive focus-within:ring-destructive/20 focus-within:border-destructive'
        : 'border-input focus-within:ring-primary/20 focus-within:border-primary',
      disabled ? 'opacity-50 cursor-not-allowed bg-muted/20' : '',
      props.class,
    ]"
  >
    <span
      v-if="prefix"
      class="inline-flex items-center px-3 bg-muted/10 text-muted-foreground text-sm border-r border-input select-none font-medium"
    >
      {{ prefix }}
    </span>
    <input
      :id="id"
      ref="inputRef"
      type="text"
      inputmode="numeric"
      :value="displayValue"
      :placeholder="placeholder"
      :disabled="disabled"
      class="flex-1 min-w-0 px-4 py-2 text-sm bg-transparent border-0 focus:outline-none focus:ring-0 transition-colors h-full"
      @input="onInput"
      @keydown="onKeyDown"
      @paste="onPaste"
      @focus="emit('focus', $event)"
      @blur="emit('blur', $event)"
    />
  </div>
</template>
