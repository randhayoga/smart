<script setup lang="ts">
/**
 * Admin Request Confirmation & Rejection Modal
 * Displays request summary details, item breakdown with stock availability,
 * optional approval/rejection notes, and submission action handlers.
 */
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { X, Loader2, ThumbsUp, Ban } from 'lucide-vue-next';
import { Button } from "@/Components/ui/button";
import { ScrollArea } from "@/Components/ui/scroll-area";
import AssetItemCard from '@/Components/AssetItemCard.vue';
import { REQUEST_STATUS_PILL_BASE } from '@/lib/requestStatus';
import type { SmartRequestData, RequestModalInfoField } from '@/types/request';
import { formatRequestModalFields } from '@/types/request';

// --- Data Types & Props ---
interface Props {
  isOpen: boolean;
  requests: SmartRequestData[];
  processing?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
  requests: () => [],
  processing: false,
});

const emit = defineEmits<{
  (e: 'close'): void;
  (e: 'action', payload: { action: 'confirm' | 'reject'; note: string }): void;
}>();

// --- Form & Action State ---
const note = ref('');
const pendingAction = ref<'confirm' | 'reject' | null>(null);

watch(() => props.isOpen, (newVal) => {
  if (newVal) {
    note.value = '';
    pendingAction.value = null;
  }
});

/** Trigger approval or rejection action with current note */
const handleAction = (action: 'confirm' | 'reject') => {
  pendingAction.value = action;
  emit('action', { action, note: note.value });
};

/** Formats request summary fields for modal display */
const getRequestFields = (req: SmartRequestData): RequestModalInfoField[] => {
  const fields = formatRequestModalFields(req);

  const isSufficient = Boolean(req.is_stock_sufficient);
  fields.push({
    label: 'Kecukupan Stok',
    value: isSufficient ? 'Cukup' : 'Tidak Cukup',
    isBadge: true,
    isSufficient: isSufficient,
  });

  return fields;
};

const multipleRequestsLabel = computed(() => {
  const hasPeminjaman = props.requests.some(r => r.type === 'peminjaman');
  const hasPermintaan = props.requests.some(r => r.type === 'permintaan');
  if (hasPeminjaman && hasPermintaan) {
    return 'Daftar Permintaan / Peminjaman:';
  }
  if (hasPeminjaman) {
    return 'Daftar Peminjaman:';
  }
  return 'Daftar Permintaan:';
});

const closeOnEscape = (e: KeyboardEvent) => {
  if (e.key === 'Escape' && props.isOpen) {
    emit('close');
  }
};

onMounted(() => {
  document.addEventListener('keydown', closeOnEscape);
});

onUnmounted(() => {
  document.removeEventListener('keydown', closeOnEscape);
});
</script>

<template>
  <Teleport to="body">
    <Transition
      enter-active-class="ease-out duration-200"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="ease-in duration-150"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div v-if="isOpen" @click="emit('close')" class="fixed inset-0 z-[100] flex items-center justify-center bg-black/40 backdrop-blur-sm p-4">
        <Transition
          enter-active-class="ease-out duration-200"
          enter-from-class="opacity-0 scale-95"
          enter-to-class="opacity-100 scale-100"
          leave-active-class="ease-in duration-150"
          leave-from-class="opacity-100 scale-100"
          leave-to-class="opacity-0 scale-95"
        >
          <div 
            v-if="isOpen"
            class="bg-card w-full max-w-2xl max-h-[90vh] rounded-[14px] shadow-2xl overflow-hidden flex flex-col border border-border"
            @click.stop
          >
            <!-- Modal Header -->
            <div class="flex items-center p-1 justify-between border-b border-border">
              <h3 class="text-lg font-bold text-foreground p-2">
                {{ requests.length > 1 ? 'Konfirmasi / Penolakan Terpilih' : 'Detail Permintaan & Konfirmasi' }}
              </h3>
              <button @click="emit('close')" class="p-2 hover:bg-muted rounded-full transition-colors">
                <X class="w-5 h-5 text-muted-foreground cursor-pointer" />
              </button>
            </div>

            <!-- Modal Body -->
            <div class="p-6 flex flex-col items-center text-center space-y-2 flex-grow overflow-y-auto">
              <!-- Requests Container -->
              <div class="w-full space-y-6">
                <!-- Single Request Selection Layout -->
                <div 
                  v-if="requests.length === 1" 
                  class="space-y-4 w-full"
                >
                  <!-- Single Item Info Details -->
                  <div class="p-3 rounded-[14px] bg-muted/40 border border-border text-left space-y-2.5 w-full">
                    <div 
                      v-for="field in getRequestFields(requests[0])" 
                      :key="field.label" 
                      class="grid grid-cols-12 gap-2 text-sm border-b border-border/50 last:border-0 pb-2 last:pb-0 items-center"
                    >
                      <span class="col-span-4 text-muted-foreground font-medium">{{ field.label }}</span>
                      <div class="col-span-8 text-right break-words flex justify-end">
                        <template v-if="field.isBadge">
                          <span 
                            :class="[
                              REQUEST_STATUS_PILL_BASE,
                              field.isSufficient
                                ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300'
                                : 'bg-amber-100 text-amber-800 dark:bg-amber-950/40 dark:text-amber-300'
                            ]"
                          >
                            {{ field.value }}
                          </span>
                        </template>
                        <template v-else>
                          <span class="text-foreground font-semibold">
                            {{ field.value }}
                          </span>
                        </template>
                      </div>
                    </div>
                  </div>

                  <!-- Card Daftar Barang -->
                  <div class="text-left w-full space-y-2">
                    <p class="text-xs text-muted-foreground font-medium">Daftar Barang:</p>
                    
                    <ScrollArea class="max-h-[14rem] sm:max-h-[16rem] h-fit border border-border rounded-[0.875rem] bg-card [&>div]:max-h-[14rem] sm:[&>div]:max-h-[16rem]">
                      <div class="p-3 sm:p-4 space-y-3">
                        <AssetItemCard 
                          v-for="item in requests[0].items" 
                          :key="item.id" 
                          :brand="item.brand !== '-' ? (item.name && item.name !== 'Tidak Spesifik' ? `${item.brand} ${item.name} ${item.spec}` : `${item.brand} ${item.spec}`) : item.subcategory"
                          :category="item.category"
                          :subcategory="item.subcategory"
                          :quantity="item.quantity"
                          :uom="item.uom || 'satuan'"
                          :stock="item.stock ?? item.stockQuantity ?? 0"
                          :is-admin="true"
                          :assets="item.assets || []"
                          :imageUrl="item.imageUrl"
                          :status="item.status"
                          :is-consumable="item.is_consumable"
                        />
                      </div>
                    </ScrollArea>
                  </div>
                </div>

                <!-- Multiple Requests Selection Layout -->
                <div 
                  v-else-if="requests.length > 1" 
                  class="text-left w-full space-y-2"
                >
                  <p class="text-xs text-muted-foreground font-medium">{{ multipleRequestsLabel }}</p>
                  
                  <ScrollArea class="max-h-[16rem] sm:max-h-[18rem] h-fit border border-border rounded-[0.875rem] bg-card [&>div]:max-h-[16rem] sm:[&>div]:max-h-[18rem]">
                    <div class="p-3 sm:p-4 space-y-3">
                      <div 
                        v-for="req in requests" 
                        :key="req.id" 
                        class="p-3 rounded-[14px] bg-muted/40 border border-border text-left space-y-2.5 w-full"
                      >
                        <div 
                          v-for="field in getRequestFields(req)" 
                          :key="field.label" 
                          class="grid grid-cols-12 gap-2 text-sm border-b border-border/50 last:border-0 pb-2 last:pb-0 items-center"
                        >
                          <span class="col-span-4 text-muted-foreground font-medium">{{ field.label }}</span>
                          <div class="col-span-8 text-right break-words flex justify-end">
                            <template v-if="field.isBadge">
                              <span 
                                :class="[
                                  REQUEST_STATUS_PILL_BASE,
                                  field.isSufficient
                                    ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300'
                                    : 'bg-amber-100 text-amber-800 dark:bg-amber-950/40 dark:text-amber-300'
                                ]"
                              >
                                {{ field.value }}
                              </span>
                            </template>
                            <template v-else>
                              <span class="text-foreground font-semibold">
                                {{ field.value }}
                              </span>
                            </template>
                          </div>
                        </div>
                      </div>
                    </div>
                  </ScrollArea>
                </div>
              </div>

              <!-- Input Catatan/Alasan -->
              <div class="space-y-1.5 text-left w-full pt-1">
                <label class="text-xs text-muted-foreground font-medium block ml-0.5">Catatan / Alasan (Opsional)</label>
                <textarea
                  v-model="note"
                  placeholder="Masukkan catatan konfirmasi atau alasan penolakan..."
                  class="w-full h-16 text-sm border border-input rounded-[14px] bg-background text-foreground p-3 focus:outline-none focus:ring-1 focus:ring-primary focus:border-primary shadow-sm resize-none"
                ></textarea>
              </div>
            </div>

            <!-- Modal Footer with Confirm & Reject Buttons -->
            <div class="py-3 px-4 bg-muted/30 border-t border-border shrink-0">
              <div class="flex items-center justify-between gap-3">
                <Button 
                  @click="emit('close')"
                  variant="white"
                  class="px-5"
                >
                  Batal
                </Button>
                <div class="flex items-center gap-2">
                  <Button 
                    @click="handleAction('reject')"
                    variant="destructive"
                    :disabled="processing"
                    class="px-5 active:scale-[0.98] relative"
                  >
                    <Loader2 v-if="processing && pendingAction === 'reject'" class="absolute inset-0 m-auto h-5 w-5 animate-spin" />
                    <span :class="{ 'opacity-0': processing && pendingAction === 'reject' }" class="flex items-center gap-1.5">
                      <Ban class="w-4 h-4" />
                      <span>Tolak Permintaan</span>
                    </span>
                  </Button>
                  <Button 
                    @click="handleAction('confirm')"
                    variant="success"
                    :disabled="processing"
                    class="px-5 active:scale-[0.98] relative"
                  >
                    <Loader2 v-if="processing && pendingAction === 'confirm'" class="absolute inset-0 m-auto h-5 w-5 animate-spin" />
                    <span :class="{ 'opacity-0': processing && pendingAction === 'confirm' }" class="flex items-center gap-1.5">
                      <ThumbsUp class="w-4 h-4" />
                      <span>Konfirmasi Permintaan</span>
                    </span>
                  </Button>
                </div>
              </div>
            </div>
          </div>
        </Transition>
      </div>
    </Transition>
  </Teleport>
</template>
