<script setup lang="ts">
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { Button } from '@/Components/ui/button';
import { ScrollArea } from "@/Components/ui/scroll-area";
import { formatDate } from '@/lib/utils';
import AssetItemCard from '@/Components/AssetItemCard.vue';
import {
  Dialog,
  DialogContent,
  DialogTitle,
  DialogDescription,
} from "@/Components/ui/dialog";
import { X } from 'lucide-vue-next';

interface RequestItem {
  id: number;
  subcategory: string;
  brand: string;
  name?: string;
  spec: string;
  quantity: number;
  stockQuantity?: number;
  imageUrl?: string;
  category: string;
  uom?: string;
  assets?: string[];
  status?: string;
  is_consumable?: boolean;
}

interface RequestHistory {
  id: number;
  number: string;
  type: 'permintaan' | 'peminjaman';
  pemanfaatan: 'corporate' | 'project';
  pemanfaatanDetail: string;
  durationStart?: string;
  durationEnd?: string;
  durationDays?: number;
  durationHours?: number;
  status: string;
  raw_status: string;
  created_at: string;
  approver_name?: string | null;
  items: RequestItem[];
}

interface Props {
  open: boolean;
  request: RequestHistory | null;
}

const props = defineProps<Props>();

const isPeminjaman = computed(() => props.request?.type === 'peminjaman');
const typeLabel = computed(() => isPeminjaman.value ? 'peminjaman' : 'permintaan');

const emit = defineEmits<{
  (e: 'update:open', value: boolean): void;
  (e: 'close'): void;
  (e: 'success'): void;
}>();

const cancelNote = ref('');
const isSubmitting = ref(false);

const handleClose = () => {
  if (isSubmitting.value) return;
  emit('update:open', false);
  emit('close');
  setTimeout(() => {
    cancelNote.value = '';
  }, 200);
};

const handleConfirmCancel = () => {
  if (!props.request || isSubmitting.value) return;

  router.post(route('smart.history.cancel', props.request.id), {
    note: cancelNote.value
  }, {
    onBefore: () => {
      isSubmitting.value = true;
    },
    onSuccess: () => {
      handleClose();
      emit('success');
    },
    onFinish: () => {
      isSubmitting.value = false;
    }
  });
};
</script>

<template>
  <Dialog :open="open" @update:open="val => { if (!isSubmitting) emit('update:open', val) }">
    <DialogContent class="sm:max-w-[50rem] rounded-[0.875rem] bg-card shadow-2xl p-0 gap-0 border border-border overflow-hidden" :show-close-button="false">
      <!-- Modal Header -->
      <div class="flex items-center justify-between pt-3 pb-2 px-4 sm:px-6 border-b border-border">
        <div>
          <DialogTitle class="text-lg font-bold text-foreground">Pembatalan {{ typeLabel }}</DialogTitle>
          <DialogDescription class="sr-only">
            Konfirmasi untuk membatalkan {{ typeLabel}} barang.
          </DialogDescription>
        </div>
        <button :disabled="isSubmitting" @click="handleClose" class="p-2 hover:bg-muted rounded-full transition-colors disabled:opacity-50">
          <X class="w-5 h-5 text-muted-foreground cursor-pointer" />
        </button>
      </div>
      
      <div v-if="request">
        <!-- Modal Body -->
        <div class="px-4 sm:px-6 py-4 overflow-y-auto max-h-[70vh] space-y-5">
          <!-- Alert & Detail Summary -->
          <div class="p-4 rounded-[0.875rem] bg-destructive/5 border border-destructive/20 space-y-2">
            <p class="font-bold text-destructive text-sm sm:text-base">
              Apakah Anda yakin untuk membatalkan {{ typeLabel}} ini?
            </p>
            <div class="space-y-1 text-sm text-foreground">
              <p class="text-base font-bold text-foreground">
                <span class="font-normal text-muted-foreground">Nomor: </span>{{ request.number }}
              </p>

              <p class="text-sm text-foreground">
                <span class="text-muted-foreground">PIC Approval:</span> 
                <span class="font-semibold ml-1">
                  {{ request.approver_name || '-' }}
                </span>
              </p>
              
              <p class="text-sm text-foreground">
                <span class="text-muted-foreground">Pemanfaatan:</span> 
                <span class="font-semibold ml-1">
                  {{ request.pemanfaatan === 'corporate' ? `Corporate (${request.pemanfaatanDetail})` : `Project ${request.pemanfaatanDetail}` }}
                </span>
              </p>

              <p v-if="request.type === 'peminjaman' && request.durationStart" class="text-sm text-foreground">
                <span class="text-muted-foreground">Durasi:</span>
                <span class="font-medium ml-1">
                  <template v-if="request.durationEnd">
                    {{ request.durationStart }} s.d. {{ request.durationEnd }} ({{ request.durationDays }} hari, {{ request.durationHours || 0 }} jam)
                  </template>
                  <template v-else>
                    {{ request.durationStart }} s.d. - (Tanpa Tenggat Waktu)
                  </template>
                </span>
              </p>

              <p class="text-xs text-muted-foreground pt-1">
                <span>Permintaan dibuat pada:</span>
                <span class="font-medium text-foreground/80 ml-1">{{ formatDate(request.created_at) }}</span>
              </p>
            </div>
          </div>

          <!-- Item List -->
          <div>
            <p class="text-xs text-muted-foreground font-medium mb-3">Daftar Barang:</p>
            
            <ScrollArea class="h-[16rem] sm:h-[18rem] border border-border rounded-[0.875rem] bg-card">
              <div class="p-2.5 sm:p-4">
                <div class="space-y-3">
                  <AssetItemCard 
                    v-for="item in request.items" 
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
              </div>
            </ScrollArea>
          </div>
        </div>

        <!-- Modal Footer -->
        <div class="py-4 px-4 sm:px-6 border-t border-border flex items-center justify-end gap-3">
          <Button 
            @click="handleClose"
            variant="white"
            size="lg"
            :disabled="isSubmitting"
          >
            Tidak
          </Button>
          <Button 
            @click="handleConfirmCancel"
            variant="destructive"
            size="lg"
            :disabled="isSubmitting"
            class="flex items-center gap-2"
          >
            <span v-if="isSubmitting">Memproses...</span>
            <span v-else>Iya, Batalkan</span>
          </Button>
        </div>
      </div>
    </DialogContent>
  </Dialog>
</template>
