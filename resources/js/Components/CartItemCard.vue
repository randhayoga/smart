<script setup lang="ts">
import { Button } from '@/Components/ui/button';
import { Trash2 } from 'lucide-vue-next';
import Checkbox from '@/Components/ui/checkbox/Checkbox.vue';
import {
  NumberField,
  NumberFieldContent,
  NumberFieldDecrement,
  NumberFieldIncrement,
  NumberFieldInput,
} from "@/Components/ui/number-field";

interface CartItem {
  id: number;
  barang_id: number | null;
  brand: string;
  name: string;
  spec: string;
  category: string;
  category_name: string;
  subcategory_name: string;
  code: string;
  stock: number;
  quantity: number;
  imageUrl?: string;
}

interface Props {
  item: CartItem;
  selected: boolean;
  disabled?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
  disabled: false
});

const emit = defineEmits<{
  (e: 'update:selected', val: boolean): void;
  (e: 'update:quantity', val: number): void;
  (e: 'remove'): void;
}>();
</script>

<template>
  <div
    class="bg-card border rounded-[0.875rem] p-3 sm:p-4 flex flex-col sm:flex-row items-stretch sm:items-center gap-3 sm:gap-4 transition-all duration-300 shadow-card"
    :class="[
      selected ? 'border-primary/50 bg-primary/5' : 'border-border',
      disabled ? 'opacity-60' : 'hover:shadow-card-hover hover:-translate-y-0.5'
    ]"
  >
    <!-- Top/Main Content Area for Checkbox, Image & Info -->
    <div class="flex items-center gap-3 sm:gap-4 flex-1 min-w-0">
      <!-- Selection Checkbox -->
      <Checkbox 
        :model-value="selected"
        @update:model-value="(val) => emit('update:selected', !!val)"
        class="cursor-pointer shrink-0"
        :disabled="disabled"
      />

      <!-- Image -->
      <div class="w-16 h-16 sm:w-24 sm:h-24 shrink-0 bg-muted rounded-[0.875rem] overflow-hidden flex items-center justify-center border border-border relative">
        <div class="absolute inset-0 bg-gradient-to-tr from-transparent via-white/10 to-white/40"></div>
        <img v-if="item.imageUrl" :src="item.imageUrl.startsWith('http') || item.imageUrl.startsWith('/') ? item.imageUrl : '/storage/' + item.imageUrl" alt="Product" class="w-full h-full object-cover relative z-10" />
        <img v-else src="https://placehold.co/400x400?text=Barang" alt="Product" class="w-full h-full object-cover opacity-50" />
      </div>

      <!-- Info -->
      <div class="flex-1 min-w-0 flex flex-col justify-center">
        <template v-if="!item.barang_id">
          <h3 class="text-sm sm:text-lg font-bold text-foreground leading-snug truncate">{{ item.subcategory_name }}</h3>
          <p class="text-xs sm:text-sm text-muted-foreground leading-normal truncate">{{ item.category_name }}</p>
          <p class="text-[10px] sm:text-xs text-muted-foreground italic hidden sm:block">*foto hanya ilustrasi</p>
        </template>
        <template v-else>
          <span v-if="item.brand && item.brand !== '-'" class="text-sm sm:text-lg font-bold text-foreground leading-snug truncate">
            {{ item.brand }}
          </span>
          <h3 class="text-sm sm:text-lg font-bold text-foreground leading-snug truncate">
            {{ item.name }}{{ item.spec && item.spec !== '-' ? ' ' + item.spec : '' }}
          </h3>
          <p class="text-xs sm:text-sm text-muted-foreground leading-normal truncate">
            {{ item.category_name }} ({{ item.subcategory_name }})
          </p>
        </template>
      </div>
    </div>

    <!-- Bottom/Controls Area for Mobile (re-aligns to side on Desktop) -->
    <div class="flex items-center justify-between sm:justify-end gap-3 border-t sm:border-t-0 border-border pt-3 sm:pt-0">
      <!-- Delete Button -->
      <Button
        variant="ghost"
        size="icon"
        class="text-destructive hover:bg-destructive/10 hover:text-destructive flex-shrink-0 rounded-full"
        @click="emit('remove')"
        title="Remove from cart"
      >
        <Trash2 class="w-4 h-4" />
      </Button>

      <!-- Quantity Control -->
      <div class="flex-shrink-0">
        <NumberField 
          :model-value="item.quantity" 
          @update:model-value="(val: number) => emit('update:quantity', val)"
          :min="1" 
          :max="999999" 
          locale="id-ID" 
          class="w-28 sm:w-32"
          :disabled="disabled"
        >
          <NumberFieldContent>
            <NumberFieldDecrement />
            <NumberFieldInput class="h-9 text-xs sm:text-sm" />
            <NumberFieldIncrement />
          </NumberFieldContent>
        </NumberField>
      </div>
    </div>
  </div>
</template>
