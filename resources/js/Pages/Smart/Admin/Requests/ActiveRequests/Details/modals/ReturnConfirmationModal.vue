<script setup lang="ts">
/**
 * ReturnConfirmationModal.vue
 *
 * Confirmation dialog for checking in returned assets back into storage,
 * setting asset status to 'Tersedia' and advancing the request status to 'success'.
 * Matches standard modal architecture and styling (InputNewStockModal).
 */
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { router } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
import { useModalLock } from '@/composables/useModalLock';
import { Button } from '@/Components/ui/button';
import { Loader2, X } from 'lucide-vue-next';

interface Props {
  open: boolean;
  request: {
    id: number | string;
    number: string;
    requester?: string;
    items?: Array<{
      id: number;
      brand?: string;
      name?: string;
      spec?: string;
      assets?: string[];
      quantity?: number;
    }>;
  };
}

const props = defineProps<Props>();
const emit = defineEmits<{
  (e: 'update:open', val: boolean): void;
  (e: 'confirmed'): void;
}>();

const { t } = useI18n();
const isSubmitting = ref(false);

useModalLock(computed(() => props.open));

const closeModal = () => {
  if (!isSubmitting.value) {
    emit('update:open', false);
  }
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

const handleConfirm = () => {
  isSubmitting.value = true;
  router.post(
    route('smart.returns.confirm', props.request.id),
    {},
    {
      onSuccess: () => {
        isSubmitting.value = false;
        closeModal();
        emit('confirmed');
        toast.success(t('fulfillment.returnConfirmSuccess', 'Pengembalian aset berhasil dikonfirmasi.'));
      },
      onError: (errs) => {
        isSubmitting.value = false;
        const msg = Object.values(errs).join(', ') || 'Gagal mengonfirmasi pengembalian.';
        toast.error(msg);
      },
    }
  );
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
      <div
        v-if="open"
        class="fixed inset-0 z-[100] flex items-center justify-center bg-black/40 backdrop-blur-sm p-4 overscroll-contain"
        @click="closeModal"
      >
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
            class="bg-card w-full max-w-lg rounded-[14px] shadow-2xl overflow-hidden flex flex-col"
            @click.stop
          >
            <!-- Modal Header -->
            <div class="flex items-center justify-between pt-3 pb-2 px-4 border-b border-border shrink-0">
              <h3 class="text-lg font-bold text-foreground">
                {{ t('fulfillment.confirmReturn') }}
              </h3>
              <button
                @click="closeModal"
                :disabled="isSubmitting"
                class="p-2 hover:bg-muted rounded-full transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
              >
                <X class="w-5 h-5 text-muted-foreground cursor-pointer" />
              </button>
            </div>

            <!-- Modal Body -->
            <div class="p-6 overflow-y-auto flex-1 space-y-4">
              <p class="text-sm text-muted-foreground leading-relaxed">
                {{ t('fulfillment.confirmReturnDesc', 'Apakah Anda yakin ingin mengonfirmasi pengembalian seluruh unit aset untuk permintaan ini? Seluruh aset akan diubah statusnya menjadi Tersedia.') }}
              </p>

              <!-- Request summary box -->
              <div class="bg-muted/40 border border-border rounded-xl p-3.5 space-y-2 text-sm">
                <div class="flex justify-between items-center">
                  <span class="text-muted-foreground">{{ t('fulfillment.number') }}:</span>
                  <span class="font-medium text-foreground font-mono">{{ request.number }}</span>
                </div>
                <div class="flex justify-between items-center">
                  <span class="text-muted-foreground">{{ t('fulfillment.requester') }}:</span>
                  <span class="font-medium text-foreground">{{ request.requester || '-' }}</span>
                </div>
                <div class="flex justify-between items-center">
                  <span class="text-muted-foreground">{{ t('fulfillment.itemsListPrefix') }}</span>
                  <span class="font-medium text-foreground">
                    {{ request.items?.reduce((acc, it) => acc + (Number(it.quantity) || 0), 0) || 0 }} unit
                  </span>
                </div>
              </div>
            </div>

            <!-- Modal Footer -->
            <div class="py-3 px-4 border-t border-border flex items-center justify-end gap-3 shrink-0">
              <Button
                @click="closeModal"
                :disabled="isSubmitting"
                variant="white"
                size="xl"
              >
                {{ t('common.cancel', 'Batal') }}
              </Button>
              <Button
                @click="handleConfirm"
                :disabled="isSubmitting"
                variant="primary"
                size="xl"
                class="relative"
              >
                <Loader2 v-if="isSubmitting" class="absolute inset-0 m-auto h-5 w-5 animate-spin" />
                <span :class="{ 'opacity-0': isSubmitting }">
                  {{ t('fulfillment.confirmReturn') }}
                </span>
              </Button>
            </div>
          </div>
        </Transition>
      </div>
    </Transition>
  </Teleport>
</template>
