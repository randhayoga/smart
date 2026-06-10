<script setup lang="ts">
import { ref, computed } from 'vue';
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
}>(), {
  placeholder: 'Pilih...',
  searchPlaceholder: 'Cari...',
  emptyText: 'Tidak ditemukan.',
  defaultLabel: 'Semua',
  widthClass: 'w-[200px]',
  disabled: false
});

const emit = defineEmits<{
  (e: 'update:modelValue', value: string | number | null): void;
}>();

const open = ref(false);

// Normalize options to object format internally
const normalizedOptions = computed(() => {
  return props.options.map(opt => {
    if (typeof opt === 'string') {
      return { id: opt, name: opt };
    }
    return opt;
  });
});

// Find label for currently selected value
const selectedLabel = computed(() => {
  if (!props.modelValue) return props.defaultLabel;
  const found = normalizedOptions.value.find(opt => opt.id == props.modelValue);
  return found ? found.name : props.defaultLabel;
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
          !modelValue ? 'text-muted-foreground' : 'text-foreground'
        ]"
      >
        <span class="truncate">{{ selectedLabel }}</span>
        <ChevronsUpDown class="ml-2 h-4 w-4 shrink-0 opacity-50" />
      </Button>
    </PopoverTrigger>
    <PopoverContent class="w-(--reka-popover-trigger-width) min-w-(--reka-popover-trigger-width) p-0 rounded-[14px] overflow-hidden z-[10000]" align="start">
      <Command :highlight-on-hover="true">
        <CommandInput :placeholder="searchPlaceholder" />
        <CommandEmpty>{{ emptyText }}</CommandEmpty>
        <CommandList>
          <CommandGroup>
            <!-- Default Option -->
            <CommandItem :value="`default-${defaultLabel}`" @select="handleSelect('')">
              <Check :class="['mr-2 h-4 w-4', !modelValue ? 'opacity-100' : 'opacity-0']" />
              {{ defaultLabel }}
            </CommandItem>
            
            <!-- Dynamic Options -->
            <CommandItem
              v-for="opt in normalizedOptions"
              :key="opt.id"
              :value="String(opt.name)"
              @select="handleSelect(opt.id)"
            >
              <Check :class="['mr-2 h-4 w-4', modelValue == opt.id ? 'opacity-100' : 'opacity-0']" />
              {{ opt.name }}
            </CommandItem>
          </CommandGroup>
        </CommandList>
      </Command>
    </PopoverContent>
  </Popover>
</template>
