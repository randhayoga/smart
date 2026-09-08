<script setup lang="ts">
/**
 * Modal Konfirmasi Alokasi Pemenuhan
 * Allows admin to review full/partial allocation readiness and submit confirmation,
 * advancing request to Handover (100%) or Partial status.
 */
import { ref, watch, onMounted, onUnmounted } from 'vue';
import { router } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
import { Button } from '@/Components/ui/button';
import { CheckCircle2, AlertTriangle, AlertCircle, X, Loader2 } from 'lucide-vue-next';
import { Field, FieldLabel, FieldContent } from '@/Components/ui/field';

interface Props {
  open: boolean;
  request: any;
}

const props = defineProps<Props>();
const emit = defineEmits<{
  (e: 'update:open', val: boolean): void;
  (e: 'success'): void;
}>();

const confirmationNote = ref('');
const isSubmittingConfirmation = ref(false);

watch(() => props.open, (isOpen) => {
  if (isOpen) {
    confirmationNote.value = '';
  }
});

const closeModal = () => {
  emit('update:open', false);
};

const closeOnEscape = (e: KeyboardEvent) => {
  if (e.key === 'Escape' && props.open) {
    closeModal();
  }
};

onMounted(() => {
  document.addEventListener('keydown', closeOnEscape);
});

onUnmounted(() => {
  document.removeEventListener('keydown', closeOnEscape);
});

const submitConfirmation = () => {
  const summary = props.request?.fulfillment_summary;
  if (!summary) return;

  const isFull = summary.can_confirm_full;
  const allowPartial = !isFull && summary.can_confirm_partial;

  if (!isFull && !allowPartial) {
    toast.error('Belum ada barang yang dialokasikan. Alokasikan setidaknya satu barang.');
    return;
  }

  isSubmittingConfirmation.value = true;
  router.post(route('smart.fulfillment.confirm', props.request.uuid), {
    allow_partial: allowPartial,
    note: confirmationNote.value,
  }, {
    onSuccess: () => {
      isSubmittingConfirmation.value = false;
      closeModal();
      emit('success');
      toast.success(isFull ? 'Konfirmasi pemenuhan berhasil diselesaikan.' : 'Konfirmasi parsial berhasil diselesaikan.');
    },
    onError: (errs) => {
      isSubmittingConfirmation.value = false;
      toast.error(Object.values(errs).join(', '));
    }
  });
};
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
      <div v-if="open" @click="closeModal" class="fixed inset-0 z-[100] flex items-center justify-center bg-black/40 backdrop-blur-sm p-4">
        <Transition
          enter-active-class="ease-out duration-200"
          enter-from-class="opacity-0 scale-95"
          enter-to-class="opacity-100 scale-100"
          leave-active-class="ease-in duration-150"
          leave-from-class="opacity-100 scale-100"
          leave-to-class="opacity-0 scale-95"
        >
          <div 
            v-if="open" 
            class="bg-card w-full max-w-2xl rounded-[14px] shadow-2xl overflow-hidden flex flex-col" 
            @click.stop
          >
            <!-- Modal Header -->
            <div class="flex items-center justify-between pt-3 pb-2 px-4 border-b border-border">
              <h3 class="text-lg font-bold text-foreground">
                Konfirmasi Alokasi Permintaan
              </h3>
              <button @click="closeModal" class="p-2 hover:bg-muted rounded-full transition-colors">
                <X class="w-5 h-5 text-muted-foreground cursor-pointer" />
              </button>
            </div>

            <!-- Modal Body -->
            <div class="p-6 overflow-y-auto max-h-[70vh] space-y-6 text-sm">
              <!-- Status Alert -->
              <div 
                v-if="request?.fulfillment_summary?.can_confirm_full"
                class="p-4 rounded-xl border border-emerald-200 bg-emerald-50 dark:bg-emerald-950/20 dark:border-emerald-800 text-emerald-800 dark:text-emerald-300 flex items-start gap-3"
              >
                <CheckCircle2 class="w-5 h-5 shrink-0 mt-0.5" />
                <div>
                  <p class="font-bold text-sm">Pemenuhan Lengkap (100%)</p>
                  <p class="mt-1 text-sm opacity-90 leading-relaxed">
                    Seluruh {{ request.fulfillment_summary.total_quantity_requested }} barang telah teralokasi. Konfirmasi ini akan melanjutkan status permintaan ke <strong>Serah Terima</strong>.
                  </p>
                </div>
              </div>

              <div 
                v-else-if="request?.fulfillment_summary?.can_confirm_partial"
                class="p-4 rounded-xl border border-amber-200 bg-amber-50 dark:bg-amber-950/20 dark:border-amber-800 text-amber-800 dark:text-amber-300 flex items-start gap-3"
              >
                <AlertTriangle class="w-5 h-5 shrink-0 mt-0.5" />
                <div>
                  <p class="font-bold text-sm">Pemenuhan Sebagian (Parsial)</p>
                  <p class="mt-1 text-sm opacity-90 leading-relaxed">
                    Teralokasi {{ request.fulfillment_summary.total_quantity_assigned }} dari {{ request.fulfillment_summary.total_quantity_requested }} barang. Konfirmasi ini akan mengubah status permintaan menjadi <strong>Serah Terima: Parsial</strong> sehingga pemohon dapat menerima barang yang siap terlebih dahulu.
                  </p>
                </div>
              </div>

              <div 
                v-else
                class="p-4 rounded-xl border border-red-200 bg-red-50 dark:bg-red-950/20 dark:border-red-800 text-red-800 dark:text-red-300 flex items-start gap-3"
              >
                <AlertCircle class="w-5 h-5 shrink-0 mt-0.5" />
                <div>
                  <p class="font-bold text-sm">Belum Ada Barang Teralokasi</p>
                  <p class="mt-1 text-sm opacity-90 leading-relaxed">
                    Anda belum mengalokasikan barang apa pun. Silakan alokasikan unit aset terlebih dahulu sebelum konfirmasi.
                  </p>
                </div>
              </div>

              <!-- Catatan Optional -->
              <Field>
                <FieldLabel>
                  <span>Catatan Admin (Opsional)</span>
                </FieldLabel>
                <FieldContent>
                  <textarea 
                    v-model="confirmationNote" 
                    rows="4" 
                    placeholder="Tuliskan catatan, keperluan, atau detail alokasi ini jika diperlukan..."
                    class="w-full px-4 py-3 text-sm border border-input rounded-[14px] bg-background focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors resize-none text-foreground placeholder:text-muted-foreground"
                  ></textarea>
                </FieldContent>
              </Field>
            </div>

            <!-- Modal Footer -->
            <div class="py-3 px-4 border-t border-border flex items-center justify-end gap-3 bg-muted/10">
              <Button 
                @click="closeModal" 
                variant="white" 
                size="lg"
              >
                Batal
              </Button>
              <Button 
                variant="primary" 
                size="lg" 
                :disabled="isSubmittingConfirmation || (!request?.fulfillment_summary?.can_confirm_full && !request?.fulfillment_summary?.can_confirm_partial)"
                @click="submitConfirmation" 
                class="relative"
              >
                <Loader2 v-if="isSubmittingConfirmation" class="absolute inset-0 m-auto h-5 w-5 animate-spin" />
                <span :class="{ 'opacity-0': isSubmittingConfirmation }">
                  {{ request?.fulfillment_summary?.can_confirm_full ? 'Konfirmasi Alokasi Penuh' : 'Konfirmasi Parsial' }}
                </span>
              </Button>
            </div>
          </div>
        </Transition>
      </div>
    </Transition>
  </Teleport>
</template>
