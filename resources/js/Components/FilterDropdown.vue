<script setup lang="ts">
import { ChevronDown } from 'lucide-vue-next';
import { Button } from "@/Components/ui/button";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "@/Components/ui/dropdown-menu";

interface Option {
  label: string;
  value: any;
}

interface Props {
  modelValue: any;
  options: (string | Option)[];
  placeholder?: string;
  allLabel?: string;
  class?: string;
}

const props = withDefaults(defineProps<Props>(), {
  placeholder: 'Pilih...',
  allLabel: 'Semua',
  class: 'w-[200px]'
});

const emit = defineEmits(['update:modelValue']);

const handleSelect = (value: any) => {
  emit('update:modelValue', value);
};

// Helper to get label from option
const getLabel = (opt: string | Option) => {
  return typeof opt === 'string' ? opt : opt.label;
};

// Helper to get value from option
const getValue = (opt: string | Option) => {
  return typeof opt === 'string' ? opt : opt.value;
};

// Get current label for the trigger
const currentLabel = () => {
  if (!props.modelValue) return props.placeholder;
  const found = props.options.find(opt => getValue(opt) === props.modelValue);
  return found ? getLabel(found) : props.placeholder;
};
</script>

<template>
  <DropdownMenu>
    <DropdownMenuTrigger asChild>
      <Button 
        variant="outline" 
        :class="['justify-between rounded-[14px] font-normal text-muted-foreground', props.class]"
      >
        <span class="truncate">{{ currentLabel() }}</span>
        <ChevronDown class="w-4 h-4 opacity-50 shrink-0" />
      </Button>
    </DropdownMenuTrigger>
    <DropdownMenuContent 
      :class="['rounded-[14px]', props.class]"
      style="min-width: var(--radix-dropdown-menu-trigger-width);"
      align="start"
      :side-offset="4"
    >
        <DropdownMenuItem @select="handleSelect('')">
          {{ allLabel }}
        </DropdownMenuItem>
        <DropdownMenuItem 
          v-for="opt in options" 
          :key="getValue(opt)" 
          @select="handleSelect(getValue(opt))"
        >
          {{ getLabel(opt) }}
        </DropdownMenuItem>
      </DropdownMenuContent>
  </DropdownMenu>
</template>
