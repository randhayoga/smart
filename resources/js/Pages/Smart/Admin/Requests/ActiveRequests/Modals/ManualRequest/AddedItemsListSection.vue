<script setup lang="ts">
/**
 * Section 3 of InputManualRequestModal: Added Items / Assets List.
 * Renders CartItemCard-styled horizontal cards for added consumable models or ST/LT physical assets.
 */
import { useI18n } from 'vue-i18n';
import { Trash2, MapPin, FileText } from 'lucide-vue-next';
import { Button } from '@/Components/ui/button';
import { formatImageUrl } from '@/lib/utils';
import type { AddedManualItem } from './ItemSelectionSection.vue';

defineProps<{
  items: AddedManualItem[];
  disabled?: boolean;
}>();

const emit = defineEmits<{
  (e: 'update-quantity', payload: { key: string; quantity: number }): void;
  (e: 'remove-item', key: string): void;
}>();

const { t } = useI18n();

const formatItemTitle = (item: AddedManualItem) => {
  const parts: string[] = [];
  if (item.brand_name && item.brand_name !== '-') {
    parts.push(item.brand_name);
  }
  if (item.name) {
    parts.push(item.name);
  }
  if (item.specification && item.specification !== '-') {
    parts.push(item.specification);
  }
  return parts.join(' ');
};

const handleQuantityInput = (item: AddedManualItem, event: Event) => {
  const input = event.target as HTMLInputElement;
  const num = parseInt(input.value, 10);
  const clamped = Math.max(1, Math.min(isNaN(num) ? 1 : num, item.available_stock));
  input.value = String(clamped);
  emit('update-quantity', { key: item.key, quantity: clamped });
};
</script>

<template>
  <div class="space-y-1.5">
    <!-- Header with text-xs muted-foreground List:/Daftar: label & Item Count Badge -->
    <div class="flex items-center justify-between px-1">
      <span class="text-xs text-muted-foreground font-medium">
        {{ t('fulfillment.manualRequest.listLabel') }}
      </span>
      <span
        v-if="items.length > 0"
        class="px-2.5 py-0.5 text-xs font-semibold rounded-full bg-primary/10 text-primary"
      >
        {{ items.length }} {{ items.length === 1 ? 'item' : 'items' }}
      </span>
    </div>

    <!-- Enclosing Outer Card Container -->
    <div class="p-4 rounded-[14px] bg-muted/20 border border-border/80 space-y-3">
      <!-- Empty State -->
      <div
        v-if="items.length === 0"
        class="p-8 rounded-[14px] border border-dashed border-border bg-background/50 text-center text-sm text-muted-foreground"
      >
        {{ t('fulfillment.manualRequest.emptyAddedItems') }}
      </div>

      <!-- Added Items Cards List -->
      <div v-else class="space-y-3">
        <div
          v-for="item in items"
          :key="item.key"
          data-testid="added-manual-item-card"
          class="bg-card border border-border rounded-[0.875rem] p-3 sm:p-4 flex flex-col sm:flex-row items-stretch sm:items-center gap-2 sm:gap-3 transition-all duration-300 shadow-card hover:shadow-card-hover"
        >
          <!-- Thumbnail & Details -->
          <div class="flex items-center gap-3 flex-1 min-w-0">
            <!-- Thumbnail -->
            <div class="w-16 h-16 sm:w-20 sm:h-20 shrink-0 bg-muted rounded-[0.875rem] overflow-hidden flex items-center justify-center border border-border relative">
              <div class="absolute inset-0 bg-gradient-to-tr from-transparent via-white/10 to-white/40"></div>
              <img
                :src="formatImageUrl(item.image_url ?? undefined)"
                :alt="item.name"
                class="w-full h-full object-cover relative z-10"
              />
            </div>

            <!-- Item Information -->
            <div class="flex-1 min-w-0 flex flex-col justify-center space-y-0.5">
              <div class="flex items-center gap-2 flex-wrap">
                <span class="px-2 py-0.5 text-[11px] font-mono font-semibold rounded-md bg-muted text-foreground border border-border">
                  {{ item.number }}
                </span>
              </div>

              <h3 class="text-sm sm:text-base font-bold text-foreground leading-snug truncate">
                {{ formatItemTitle(item) }}
              </h3>

              <p class="text-xs text-muted-foreground leading-normal truncate">
                {{ item.category_name }} ({{ item.type_name }})
              </p>

              <!-- Location for ST/LT Physical Assets -->
              <p v-if="item.location_name" class="text-xs text-muted-foreground flex items-center gap-1 truncate">
                <MapPin class="w-3 h-3 shrink-0" />
                <span>{{ item.location_name }}</span>
              </p>

              <!-- Per-Item Reasoning / Note -->
              <p v-if="item.reasoning" class="text-xs text-foreground/80 italic flex items-center gap-1 truncate pt-0.5">
                <FileText class="w-3 h-3 shrink-0 text-muted-foreground" />
                <span>"{{ item.reasoning }}"</span>
              </p>
            </div>
          </div>

          <!-- Right Controls: Quantity Box & Remove Button -->
          <div class="flex items-center justify-between sm:justify-end gap-3 border-t sm:border-t-0 border-border pt-3 sm:pt-0">
            <!-- Consumable Quantity Input Box matching Non-Consumable Badge (Enlarged text-sm) -->
            <div
              v-if="item.request_type === 'consumable'"
              class="flex items-center rounded-[10px] bg-muted/40 border border-border overflow-hidden focus-within:ring-2 focus-within:ring-primary/20 focus-within:border-primary shrink-0 transition-colors"
            >
              <input
                type="number"
                min="1"
                :max="item.available_stock"
                :value="item.quantity"
                :disabled="disabled"
                class="w-14 px-2 py-1.5 text-sm font-semibold text-foreground bg-transparent text-center border-0 focus:outline-none focus:ring-0 disabled:opacity-50"
                @change="handleQuantityInput(item, $event)"
              />
              <span class="pr-3 text-sm font-semibold text-foreground select-none">
                {{ item.uom_name || 'Unit' }}
              </span>
            </div>

            <!-- Asset 1 Unit Badge (Enlarged text-sm) -->
            <div v-else class="px-3.5 py-1.5 rounded-[10px] bg-muted/40 border border-border text-sm font-semibold text-foreground shrink-0">
              1 {{ item.uom_name || 'Unit' }}
            </div>

            <!-- Destructive Remove Button -->
            <Button
              type="button"
              variant="ghost"
              size="icon"
              data-testid="remove-manual-item-btn"
              class="text-destructive hover:bg-destructive/10 hover:text-destructive shrink-0 rounded-full"
              :disabled="disabled"
              :title="t('common.delete')"
              @click="emit('remove-item', item.key)"
            >
              <Trash2 class="w-4 h-4" />
            </Button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
