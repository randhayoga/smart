<script setup lang="ts">
/**
 * Manager Approval Modal Component
 * Handles single or bulk review, item breakdown inspection, and approval/rejection confirmation with optional notes.
 */
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { X, Loader2 } from 'lucide-vue-next';
import { Button } from "@/Components/ui/button";
import { ScrollArea } from "@/Components/ui/scroll-area";
import AssetItemCard from '@/Components/AssetItemCard.vue';

// --- Data Types & Props ---
interface RequestItem {
  id: number;
  barang_id?: number;
  subcategory: string;
  brand: string;
  name?: string;
  spec: string;
  quantity: number;
  stockQuantity?: number;
  imageUrl?: string;
  category: string;
  assets?: string[];
  is_consumable?: boolean;
  uom?: string;
  status?: string;
}

interface RequestData {
  id: number;
  number: string;
  type: 'permintaan' | 'peminjaman' | string;
  requester?: string;
  pemanfaatan?: string;
  pemanfaatanDetail?: string;
  durationStart?: string;
  durationEnd?: string;
  durationDays?: number;
  durationHours?: number;
  status?: string;
  raw_status?: string;
  created_at?: string;
  createdAt?: string;
  items: RequestItem[];
}

interface Props {
  isOpen: boolean;
  actionType: 'approve' | 'reject' | 'approved' | 'rejected' | string;
  requests: RequestData[];
  processing?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
  actionType: 'approve',
  requests: () => [],
  processing: false,
});

const emit = defineEmits<{
  (e: 'close'): void;
  (e: 'confirm', note: string): void;
}>();

// --- State & Form Handlers ---
const note = ref('');

watch(() => props.isOpen, (newVal) => {
  if (newVal) {
    note.value = '';
  }
});

const isApprove = computed(() => {
  return props.actionType === 'approve' || props.actionType === 'approved';
});

const modalTitle = computed(() => {
  return isApprove.value ? 'Konfirmasi Approval' : 'Konfirmasi Penolakan';
});

const handleConfirm = () => {
  emit('confirm', note.value);
};

/** Formats request summary fields for display in modal */
const getRequestFields = (req: RequestData) => {
  const fields: { label: string; value: string }[] = [
    { label: 'Nomor', value: req.number },
    { label: 'Pemohon', value: req.requester || '-' },
    { label: 'Pemanfaatan', value: req.pemanfaatan === 'corporate' ? `Corporate (${req.pemanfaatanDetail || '-'})` : `Project ${req.pemanfaatanDetail || '-'}` },
  ];

  if (req.type === 'peminjaman' && req.durationStart) {
    const durStr = req.durationEnd 
      ? `${req.durationStart} s.d. ${req.durationEnd} (${req.durationDays || 0} hari, ${req.durationHours || 0} jam)`
      : `${req.durationStart} s.d. - (Tanpa Tenggat Waktu)`;
    fields.push({ label: 'Durasi', value: durStr });
  }

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
              <h3 class="text-lg font-bold text-foreground p-2">{{ modalTitle }}</h3>
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
                  <!-- Single Item Info Details (matching DeleteConfirmationModal) -->
                  <div class="p-3 rounded-[14px] bg-muted/40 border border-border text-left space-y-2.5 w-full">
                    <div 
                      v-for="field in getRequestFields(requests[0])" 
                      :key="field.label" 
                      class="grid grid-cols-12 gap-2 text-sm border-b border-border/50 last:border-0 pb-2 last:pb-0"
                    >
                      <span class="col-span-4 text-muted-foreground font-medium">{{ field.label }}</span>
                      <span class="col-span-8 text-foreground font-semibold text-right break-words">
                        {{ field.value }}
                      </span>
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
                          class="grid grid-cols-12 gap-2 text-sm border-b border-border/50 last:border-0 pb-2 last:pb-0"
                        >
                          <span class="col-span-4 text-muted-foreground font-medium">{{ field.label }}</span>
                          <span class="col-span-8 text-foreground font-semibold text-right break-words">
                            {{ field.value }}
                          </span>
                        </div>
                      </div>
                    </div>
                  </ScrollArea>
                </div>
              </div>

              <!-- Input Catatan/Alasan -->
              <div class="space-y-1.5 text-left w-full pt-1">
                <label class="text-xs text-muted-foreground font-medium block">Catatan / Alasan (Opsional)</label>
                <textarea
                  v-model="note"
                  placeholder="Masukkan catatan persetujuan atau alasan penolakan..."
                  class="w-full h-16 text-sm border border-input rounded-[14px] bg-background text-foreground p-3 focus:outline-none focus:ring-1 focus:ring-primary focus:border-primary shadow-sm resize-none"
                ></textarea>
              </div>
            </div>

            <!-- Modal Footer -->
            <div class="py-3 px-4 bg-muted/30 border-t border-border shrink-0">
              <div class="flex items-center justify-end gap-3">
                <Button 
                  @click="emit('close')"
                  variant="white"
                  class="px-5"
                >
                  Batal
                </Button>
                <Button 
                  @click="handleConfirm"
                  :variant="isApprove ? 'success' : 'destructive'"
                  :disabled="processing"
                  class="px-5 active:scale-[0.98] relative"
                >
                  <Loader2 v-if="processing" class="absolute inset-0 m-auto h-5 w-5 animate-spin" />
                  <span :class="{ 'opacity-0': processing }">
                    {{ isApprove ? 'Konfirmasi Approval' : 'Konfirmasi Penolakan' }}
                  </span>
                </Button>
              </div>
            </div>
          </div>
        </Transition>
      </div>
    </Transition>
  </Teleport>
</template>
