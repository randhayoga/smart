<script setup lang="ts">
/**
 * FinishManualBorrowModal.vue
 *
 * Confirmation dialog for finishing manual borrowings/assignments (either all assets or an individual asset),
 * creating a request_returns entry with mandatory photo/document proof and storage location,
 * marking asset(s) as 'Tersedia' with the chosen location, and logging the admin action.
 * Matches standard modal architecture and styling (InputNewStockModal).
 */
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { router } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
import { useModalLock } from '@/composables/useModalLock';
import { Button } from '@/Components/ui/button';
import { Field, FieldLabel, FieldContent, FieldError } from '@/Components/ui/field';
import LocationCombobox, { type LocationItem } from '@/Components/LocationCombobox.vue';
import { Loader2, Camera, Trash2, X } from 'lucide-vue-next';
import { getTypeActionLabel } from '@/lib/requestTypeBadge';
import { compressImageIfNeeded } from '@/utils/imageCompressor';

export interface TargetAsset {
  fulfillment_id: number;
  asset_id: number;
  number?: string;
  asset_number?: string;
}

interface Props {
  open: boolean;
  mode: 'all' | 'asset';
  request: {
    id: number | string;
    number: string;
    requester?: string;
    specific_type?: string;
    type?: string;
  };
  locations?: LocationItem[];
  targetAsset?: TargetAsset | null;
}

const props = withDefaults(defineProps<Props>(), {
  locations: () => [],
  targetAsset: null,
});

const emit = defineEmits<{
  (e: 'update:open', val: boolean): void;
  (e: 'confirmed'): void;
}>();

const { t } = useI18n();
const isSubmitting = ref(false);

useModalLock(computed(() => props.open));

const photoInput = ref<HTMLInputElement | null>(null);
const cameraInput = ref<HTMLInputElement | null>(null);

const proofFile = ref<File | null>(null);
const proofFileName = ref('');
const locationId = ref<number | string | null>(null);
const errors = ref<{ location_id?: string; proof?: string; [key: string]: any }>({});

const actionLabel = computed(() => {
  return getTypeActionLabel(props.request.specific_type || props.request.type, t, false);
});

const actionLabelLower = computed(() => {
  return getTypeActionLabel(props.request.specific_type || props.request.type, t, true);
});

const targetAssetNumber = computed(() => {
  return props.targetAsset?.number || props.targetAsset?.asset_number || '-';
});

const resetForm = () => {
  proofFile.value = null;
  proofFileName.value = '';
  locationId.value = null;
  errors.value = {};
  if (photoInput.value) photoInput.value.value = '';
  if (cameraInput.value) cameraInput.value.value = '';
};

watch(() => props.open, (val) => {
  if (val) {
    resetForm();
  }
});

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

const triggerFileInput = () => {
  photoInput.value?.click();
};

const triggerCameraInput = () => {
  cameraInput.value?.click();
};

const clearFile = () => {
  proofFile.value = null;
  proofFileName.value = '';
  if (photoInput.value) photoInput.value.value = '';
  if (cameraInput.value) cameraInput.value.value = '';
};

const viewProofInNewTab = () => {
  if (proofFile.value) {
    window.open(URL.createObjectURL(proofFile.value), '_blank');
  }
};

const handleFileUpload = async (e: Event) => {
  const target = e.target as HTMLInputElement;
  const rawFile = target.files?.[0];
  if (!rawFile) return;

  const allowedMimes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp', 'application/pdf'];
  const isAllowed = allowedMimes.includes(rawFile.type) || rawFile.type.startsWith('image/') || rawFile.name.toLowerCase().endsWith('.pdf');
  if (!isAllowed) {
    toast.error(t('fulfillment.invalidProofFormat'));
    target.value = '';
    return;
  }

  if (rawFile.size > 5 * 1024 * 1024) {
    toast.error(t('fulfillment.proofTooLarge'));
    target.value = '';
    return;
  }

  try {
    let finalFile = rawFile;
    if (rawFile.type.startsWith('image/')) {
      finalFile = await compressImageIfNeeded(rawFile, { maxSizeBytes: 2 * 1024 * 1024 });
    }
    proofFile.value = finalFile;
    proofFileName.value = finalFile.name;
    delete errors.value.proof;
  } catch (err) {
    console.error('Failed to compress image:', err);
    toast.error(t('inventory.failedProcessImage', 'Gagal memproses gambar.'));
  } finally {
    target.value = '';
  }
};

const handleConfirm = () => {
  errors.value = {};
  if (!locationId.value) {
    errors.value.location_id = t('inventory.locationRequired', 'Lokasi belum dipilih');
  }
  if (!proofFile.value) {
    errors.value.proof = t('fulfillment.proofRequired');
  }

  if (Object.keys(errors.value).length > 0) {
    return;
  }

  isSubmitting.value = true;

  const url = props.mode === 'all'
    ? route('smart.borrowed.finishAll', props.request.id)
    : route('smart.borrowed.finishAsset', [props.request.id, props.targetAsset?.fulfillment_id]);

  const formData = new FormData();
  formData.append('proof', proofFile.value as File);
  formData.append('location_id', String(locationId.value));

  router.post(
    url,
    formData,
    {
      forceFormData: true,
      onSuccess: () => {
        isSubmitting.value = false;
        resetForm();
        emit('update:open', false);
        emit('confirmed');
        toast.success(t('fulfillment.finishSuccess', { action: actionLabel.value }));
      },
      onError: (errs) => {
        isSubmitting.value = false;
        errors.value = errs;
        const msg = Object.values(errs).join(', ') || 'Gagal menyelesaikan peminjaman/penugasan.';
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
            class="bg-card w-full max-w-xl rounded-[14px] shadow-2xl overflow-hidden flex flex-col max-h-[90vh]"
            @click.stop
          >
            <!-- Modal Header -->
            <div class="flex items-center justify-between pt-3 pb-2 px-4 border-b border-border shrink-0">
              <h3 class="text-lg font-bold text-foreground">
                {{ mode === 'all' ? t('fulfillment.finishAllTitle', { action: actionLabel }) : t('fulfillment.finishAssetTitle', { action: actionLabel }) }}
              </h3>
              <button
                @click="closeModal"
                :disabled="isSubmitting"
                class="p-2 hover:bg-muted rounded-full transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
              >
                <X class="w-5 h-5 text-muted-foreground cursor-pointer" />
              </button>
            </div>

            <!-- Modal Body (Scrollable) -->
            <div class="p-6 overflow-y-auto flex-1 space-y-4">
              <p class="text-sm text-muted-foreground leading-relaxed">
                <template v-if="mode === 'all'">
                  {{ t('fulfillment.finishAllDesc', { action: actionLabelLower }) }}
                </template>
                <template v-else>
                  {{ t('fulfillment.finishAssetDesc', { action: actionLabelLower, number: targetAssetNumber }) }}
                </template>
              </p>

              <!-- Summary box -->
              <div class="bg-muted/40 border border-border rounded-xl p-3.5 space-y-2 text-sm">
                <div class="flex justify-between items-center">
                  <span class="text-muted-foreground">{{ t('fulfillment.number') }}:</span>
                  <span class="font-medium text-foreground font-mono">{{ request.number }}</span>
                </div>
                <div class="flex justify-between items-center">
                  <span class="text-muted-foreground">{{ t('fulfillment.requester') }}:</span>
                  <span class="font-medium text-foreground">{{ request.requester || '-' }}</span>
                </div>
                <div v-if="mode === 'asset'" class="flex justify-between items-center">
                  <span class="text-muted-foreground">{{ t('fulfillment.assetNumber') }}:</span>
                  <span class="font-medium text-primary font-mono">{{ targetAssetNumber }}</span>
                </div>
              </div>

              <!-- 1. Mandatory Location Combobox -->
              <Field :data-invalid="!!errors.location_id || undefined">
                <FieldLabel for="finish-borrow-location">
                  <span>{{ t('inventory.location') }}<span class="text-rose-500">*</span></span>
                </FieldLabel>
                <FieldContent>
                  <LocationCombobox
                    id="finish-borrow-location"
                    v-model="locationId"
                    :locations="locations"
                    :placeholder="t('inventory.selectLocation')"
                    :search-placeholder="t('admin.searchLocationPlaceholder', 'Cari lokasi...')"
                    :error="Boolean(errors.location_id)"
                    :active-only="true"
                  />
                </FieldContent>
                <FieldError v-if="errors.location_id">{{ errors.location_id }}</FieldError>
              </Field>

              <!-- 2. Mandatory Proof File Upload with Mobile Camera -->
              <Field :data-invalid="!!errors.proof || undefined">
                <FieldLabel for="finish-borrow-proof">
                  <span>{{ t('fulfillment.returnProof') }}<span class="text-rose-500">*</span></span>
                </FieldLabel>
                <FieldContent>
                  <div class="space-y-1">
                    <div class="flex items-center gap-2">
                      <div
                        class="flex-1 px-3 py-2 text-sm border rounded-[10px] bg-muted/40 text-muted-foreground truncate h-10 flex items-center"
                        :class="[
                          proofFile ? 'cursor-pointer hover:bg-muted/60 text-primary font-medium underline decoration-dotted' : '',
                          errors.proof ? 'border-destructive' : 'border-input'
                        ]"
                        @click="proofFile && viewProofInNewTab()"
                      >
                        {{ proofFileName || t('inventory.noFileSelected') }}
                      </div>

                      <input
                        ref="photoInput"
                        type="file"
                        accept=".jpg,.jpeg,.png,.webp,.pdf"
                        class="hidden"
                        @change="handleFileUpload"
                      />
                      <input
                        ref="cameraInput"
                        type="file"
                        accept="image/*"
                        capture="environment"
                        class="hidden"
                        @change="handleFileUpload"
                      />

                      <!-- Choose File Button (100% matched with AssetSection/LotSection) -->
                      <Button
                        type="button"
                        size="lg"
                        class="shrink-0"
                        @click="triggerFileInput"
                      >
                        {{ t('inventory.chooseFile') }}
                      </Button>

                      <!-- Camera Button (Mobile View Only) -->
                      <Button
                        type="button"
                        variant="outline"
                        size="icon"
                        class="w-9 h-10 shrink-0 md:hidden border-emerald-600/30 text-emerald-600 hover:bg-emerald-50 dark:hover:bg-emerald-950/30 rounded-[10px]"
                        :title="t('fulfillment.takePhoto')"
                        @click="triggerCameraInput"
                      >
                        <Camera class="w-4 h-4" />
                      </Button>

                      <!-- Delete Icon Button to the right (100% matched) -->
                      <Button
                        type="button"
                        variant="destructive"
                        size="icon"
                        class="w-9 shrink-0"
                        :disabled="!proofFile"
                        @click="clearFile"
                      >
                        <Trash2 class="w-4 h-4" />
                      </Button>
                    </div>

                    <p class="text-[10px] text-muted-foreground ml-1">{{ t('inventory.maxFileSize2Mb', 'Ukuran maksimal file 2MB') }}</p>
                  </div>
                </FieldContent>
                <FieldError v-if="errors.proof">{{ errors.proof }}</FieldError>
              </Field>
            </div>

            <!-- Modal Footer -->
            <div class="py-3 px-4 border-t border-border flex items-center justify-between shrink-0">
              <p class="text-sm text-rose-500 italic font-medium">{{ t('inventory.requiredMarker', '* Wajib diisi') }}</p>
              <div class="flex items-center gap-3">
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
                    {{ t('fulfillment.finish') }}
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
