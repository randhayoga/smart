<script setup lang="ts">
/**
 * Searchable Combobox component built with Popover and Command primitives.
 * Highly optimized for large option lists (2,000+ items) via windowed rendering.
 */
import { ref, computed, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { Check, ChevronsUpDown } from 'lucide-vue-next';
import { Button } from "@/Components/ui/button";
import { Popover, PopoverContent, PopoverTrigger } from '@/Components/ui/popover';
import {
  Command,
  CommandEmpty,
  CommandGroup,
  CommandInput,
  CommandItem,
  CommandList,
} from '@/Components/ui/command';

interface OptionObject {
  id: string | number;
  name: string;
}

type Option = string | OptionObject;

const props = withDefaults(defineProps<{
  modelValue: string | number | null;
  options: Option[];
  placeholder?: string;
  searchPlaceholder?: string;
  emptyText?: string;
  defaultLabel?: string;
  widthClass?: string;
  disabled?: boolean;
  error?: boolean;
  limit?: number;
}>(), {
  widthClass: 'w-[200px]',
  disabled: false,
  error: false,
  limit: 20,
});

const emit = defineEmits<{
  (e: 'update:modelValue', value: string | number | null): void;
}>();

const { t } = useI18n();
const open = ref(false);
const searchQuery = ref('');

// Reset search query whenever dropdown is opened/closed
watch(open, (isOpen) => {
  if (!isOpen) {
    searchQuery.value = '';
  }
});

const effectivePlaceholder = computed(() => props.placeholder ?? `${t('common.select')}...`);
const effectiveSearchPlaceholder = computed(() => props.searchPlaceholder ?? `${t('common.search')}...`);
const effectiveEmptyText = computed(() => props.emptyText ?? t('common.noData'));
const effectiveDefaultLabel = computed(() => props.defaultLabel ?? t('common.all'));

// Normalize options to object format internally
const normalizedOptions = computed(() => {
  return (props.options || []).map(opt => {
    if (typeof opt === 'string') {
      return { id: opt, name: opt };
    }
    return opt;
  });
});

// Find label for currently selected value
const selectedLabel = computed(() => {
  if (props.modelValue === null || props.modelValue === '' || props.modelValue === undefined) {
    return effectiveDefaultLabel.value;
  }
  const found = normalizedOptions.value.find(opt => String(opt.id) === String(props.modelValue));
  return found ? found.name : effectiveDefaultLabel.value;
});

// Filter and slice options to avoid rendering thousands of DOM nodes at once
const displayedOptions = computed(() => {
  const query = searchQuery.value.trim().toLowerCase();
  let list = normalizedOptions.value;

  if (query) {
    list = list.filter(opt => String(opt.name).toLowerCase().includes(query));
  }

  // Ensure currently selected item is included in list if it matches or when no search
  const maxLimit = props.limit > 0 ? props.limit : 20;
  const sliced = list.slice(0, maxLimit);
  if (props.modelValue !== null && props.modelValue !== '' && props.modelValue !== undefined) {
    const isSelectedInSlice = sliced.some(opt => String(opt.id) === String(props.modelValue));
    if (!isSelectedInSlice) {
      const selectedItem = list.find(opt => String(opt.id) === String(props.modelValue));
      if (selectedItem) {
        sliced.unshift(selectedItem);
      }
    }
  }

  return sliced;
});

const handleSelect = (val: string | number | null) => {
  emit('update:modelValue', val);
  open.value = false;
};
</script>

<template>
  <Popover v-model:open="open">
    <PopoverTrigger asChild>
      <Button 
        variant="outline" 
        role="combobox" 
        :aria-expanded="open" 
        :disabled="disabled"
        :class="[
          widthClass,
          'justify-between rounded-[14px] font-normal',
          !modelValue ? 'text-muted-foreground' : 'text-foreground',
          error ? '!border-destructive focus:!ring-destructive/20 focus:!border-destructive' : ''
        ]"
      >
        <span class="truncate">{{ selectedLabel }}</span>
        <ChevronsUpDown class="ml-2 h-4 w-4 shrink-0 opacity-50" />
      </Button>
    </PopoverTrigger>
    <PopoverContent class="w-(--reka-popover-trigger-width) min-w-(--reka-popover-trigger-width) p-0 rounded-[14px] overflow-hidden z-[10000]" align="start">
      <Command :highlight-on-hover="true">
        <CommandInput 
          v-model="searchQuery"
          :placeholder="effectiveSearchPlaceholder" 
        />
        <CommandEmpty>{{ effectiveEmptyText }}</CommandEmpty>
        <CommandList>
          <CommandGroup>
            <!-- Default Option -->
            <CommandItem :value="`default-${effectiveDefaultLabel}`" @select="handleSelect('')">
              <Check :class="['mr-2 h-4 w-4', !modelValue ? 'opacity-100' : 'opacity-0']" />
              {{ effectiveDefaultLabel }}
            </CommandItem>
            
            <!-- Dynamic Options (Windowed to max 100 items) -->
            <CommandItem
              v-for="opt in displayedOptions"
              :key="opt.id"
              :value="String(opt.name)"
              @select="handleSelect(opt.id)"
            >
              <Check :class="['mr-2 h-4 w-4', String(modelValue) === String(opt.id) ? 'opacity-100' : 'opacity-0']" />
              {{ opt.name }}
            </CommandItem>
          </CommandGroup>
        </CommandList>
      </Command>
    </PopoverContent>
  </Popover>
</template>
