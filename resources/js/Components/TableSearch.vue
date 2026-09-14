<script setup lang="ts">
/**
 * Table Search Input component with search icon, two-way binding,
 * debounced dispatching to prevent main-thread freeze on large tables,
 * and immediate table skeleton triggering when typing.
 */
import { ref, watch } from 'vue';
import { Search, X, Loader2 } from 'lucide-vue-next';
import { useTableSearch } from '@/composables/useTableSearch';

const props = withDefaults(defineProps<{
  modelValue: string;
  placeholder?: string;
  id?: string;
  name?: string;
  bgClass?: string;
  debounce?: number;
}>(), {
  debounce: 200,
});

const emit = defineEmits<{
  (e: 'update:modelValue', value: string): void;
}>();

const { isTableSearching, setTableSearching } = useTableSearch();
const localValue = ref(props.modelValue || '');
let debounceTimer: ReturnType<typeof setTimeout> | null = null;

// Synchronize localValue when external modelValue changes (e.g. filter reset)
watch(() => props.modelValue, (newVal) => {
  if (newVal !== localValue.value) {
    localValue.value = newVal || '';
  }
});

const handleInput = (e: Event) => {
  const val = (e.target as HTMLInputElement).value;
  localValue.value = val;

  if (debounceTimer) clearTimeout(debounceTimer);

  // If search was completely cleared, emit immediately
  if (val === '') {
    setTableSearching(false);
    emit('update:modelValue', '');
    return;
  }

  // Signal that search typing is active immediately so tables > 50 rows show skeleton
  setTableSearching(true);

  debounceTimer = setTimeout(() => {
    emit('update:modelValue', val);
    setTimeout(() => {
      setTableSearching(false);
    }, 60);
  }, props.debounce);
};

const handleKeyDown = (e: KeyboardEvent) => {
  if (e.key === 'Enter') {
    if (debounceTimer) clearTimeout(debounceTimer);
    emit('update:modelValue', localValue.value);
    setTimeout(() => {
      setTableSearching(false);
    }, 60);
  }
};

const clearSearch = () => {
  if (debounceTimer) clearTimeout(debounceTimer);
  localValue.value = '';
  setTableSearching(false);
  emit('update:modelValue', '');
};
</script>

<template>
  <div class="relative flex items-center">
    <!-- Search Icon or Loading Spinner -->
    <div class="absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none text-muted-foreground">
      <Loader2 v-if="isTableSearching && localValue" class="w-4 h-4 animate-spin text-primary" />
      <Search v-else class="w-4 h-4" />
    </div>

    <!-- Text Input -->
    <input 
      type="text" 
      :id="id"
      :name="name"
      :value="localValue"
      @input="handleInput"
      @keydown="handleKeyDown"
      :placeholder="placeholder || ($t('common.search') + '...')"
      :class="[
        'w-full pl-9 py-1.5 text-sm border border-input rounded-[14px] focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors',
        localValue ? 'pr-8' : 'pr-3',
        bgClass || 'bg-background'
      ]"
    />

    <!-- Quick Clear Button -->
    <button
      v-if="localValue"
      type="button"
      @click="clearSearch"
      class="absolute right-2.5 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground p-0.5 rounded-full transition-colors focus:outline-none"
      :aria-label="$t('common.clear') || 'Clear'"
    >
      <X class="w-3.5 h-3.5" />
    </button>
  </div>
</template>
