<script setup lang="ts">
/**
 * Section 4 of InputManualRequestModal: Handover Information.
 * Handles Handover Method (Pickup / Delivery), Active Handover Location Combobox,
 * and Optional Proof of Handover File Upload (matching FinishManualBorrowModal).
 */
import { ref, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';
import { ChevronDown, Camera, Trash2 } from 'lucide-vue-next';
import { Button } from '@/Components/ui/button';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';
import { Field, FieldLabel, FieldContent, FieldError } from '@/Components/ui/field';
import LocationCombobox, { type LocationItem } from '@/Components/LocationCombobox.vue';
import { compressImageIfNeeded } from '@/utils/imageCompressor';
import type { ManualRequestFormState } from './RequestInfoSection.vue';

const props = defineProps<{
  form: ManualRequestFormState;
  errors: Record<string, string>;
  locations: LocationItem[];
}>();

const emit = defineEmits<{
  (e: 'change-handover-method', val: 'pickup' | 'delivery'): void;
  (e: 'change-handover-location', val: number | string | null): void;
  (e: 'change-handover-proof', file: File | null): void;
}>();

const { t } = useI18n();
const photoInput = ref<HTMLInputElement | null>(null);
const cameraInput = ref<HTMLInputElement | null>(null);

const handoverMethodLabel = computed(() => {
  return props.form.handover_method === 'delivery'
    ? t('fulfillment.manualRequest.methodDelivery')
    : t('fulfillment.manualRequest.methodPickup');
});

const triggerFileInput = () => {
  photoInput.value?.click();
};

const triggerCameraInput = () => {
  cameraInput.value?.click();
};

const clearFile = () => {
  emit('change-handover-proof', null);
  if (photoInput.value) photoInput.value.value = '';
  if (cameraInput.value) cameraInput.value.value = '';
};

const viewProofInNewTab = () => {
  if (props.form.handover_proof) {
    window.open(URL.createObjectURL(props.form.handover_proof), '_blank');
  }
};

const handleFileUpload = async (e: Event) => {
  const target = e.target as HTMLInputElement;
  const rawFile = target.files?.[0];
  if (!rawFile) return;

  const allowedMimes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp', 'application/pdf'];
  const isAllowed =
    allowedMimes.includes(rawFile.type) ||
    rawFile.type.startsWith('image/') ||
    rawFile.name.toLowerCase().endsWith('.pdf');
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
    emit('change-handover-proof', finalFile);
  } catch (err) {
    console.error('Failed to compress image:', err);
    toast.error(t('inventory.failedProcessImage', 'Gagal memproses gambar.'));
  } finally {
    target.value = '';
  }
};
</script>

<template>
  <div class="space-y-1.5">
    <div class="flex items-center justify-between px-1">
      <span class="text-xs text-muted-foreground font-medium">
        {{ t('fulfillment.manualRequest.handoverSectionLabel') }}
      </span>
    </div>

    <div class="p-4 rounded-[14px] bg-muted/20 border border-border/80">
      <div class="grid grid-cols-1 md:grid-cols-2 gap-x-12 gap-y-5">
        <!-- 1. Handover Method (Row 1 Left) -->
        <Field :data-invalid="!!errors.handover_method || undefined">
          <FieldLabel>
            <span>{{ t('fulfillment.manualRequest.handoverMethod') }}<span class="text-rose-500">*</span></span>
          </FieldLabel>
          <FieldContent>
            <DropdownMenu>
              <DropdownMenuTrigger asChild>
                <Button
                  variant="outline"
                  data-testid="handover-method-trigger"
                  class="w-full justify-between rounded-[14px] font-normal h-10 px-4 bg-background"
                  :class="[errors.handover_method ? 'border-destructive' : '']"
                >
                  {{ handoverMethodLabel }}
                  <ChevronDown class="w-4 h-4 opacity-50" />
                </Button>
              </DropdownMenuTrigger>
              <DropdownMenuContent
                align="start"
                class="w-(--reka-dropdown-menu-trigger-width) min-w-(--reka-dropdown-menu-trigger-width) rounded-[14px] z-[1001]"
              >
                <DropdownMenuItem @select="emit('change-handover-method', 'pickup')">
                  {{ t('fulfillment.manualRequest.methodPickup') }}
                </DropdownMenuItem>
                <DropdownMenuItem @select="emit('change-handover-method', 'delivery')">
                  {{ t('fulfillment.manualRequest.methodDelivery') }}
                </DropdownMenuItem>
              </DropdownMenuContent>
            </DropdownMenu>
          </FieldContent>
          <FieldError v-if="errors.handover_method">{{ errors.handover_method }}</FieldError>
        </Field>

        <!-- 2. Handover Location (Row 1 Right, same x-axis as Method) -->
        <Field :data-invalid="!!errors.handover_location_id || undefined">
          <FieldLabel for="manual-request-handover-location">
            <span>{{ t('fulfillment.manualRequest.handoverLocation') }}<span class="text-rose-500">*</span></span>
          </FieldLabel>
          <FieldContent>
            <LocationCombobox
              id="manual-request-handover-location"
              :model-value="form.handover_location_id"
              :locations="locations"
              :placeholder="t('fulfillment.manualRequest.selectHandoverLocation')"
              :search-placeholder="t('admin.searchLocationPlaceholder', 'Cari lokasi...')"
              :error="Boolean(errors.handover_location_id)"
              :active-only="true"
              @update:model-value="(val) => emit('change-handover-location', val)"
            />
          </FieldContent>
          <FieldError v-if="errors.handover_location_id">{{ errors.handover_location_id }}</FieldError>
        </Field>

        <!-- 3. Proof of Handover (Row 2 Left - confined to left column) -->
        <Field :data-invalid="!!errors.handover_proof || undefined">
          <FieldLabel for="manual-request-handover-proof">
            <span>{{ t('fulfillment.manualRequest.handoverProof') }}</span>
          </FieldLabel>
          <FieldContent>
            <div class="space-y-1">
              <div class="flex items-center gap-2">
                <div
                  class="flex-1 px-4 py-2 text-sm border rounded-[14px] bg-background truncate h-10 flex items-center"
                  :class="[
                    form.handover_proof
                      ? 'cursor-pointer hover:bg-muted/20 hover:text-primary transition-colors text-foreground font-medium underline decoration-dotted'
                      : 'text-muted-foreground cursor-default',
                    errors.handover_proof ? 'border-destructive' : 'border-input',
                  ]"
                  @click="form.handover_proof && viewProofInNewTab()"
                >
                  {{ form.handover_proof_name || t('fulfillment.manualRequest.noFileSelected') }}
                </div>

                <input
                  ref="photoInput"
                  data-testid="manual-request-handover-proof-input"
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

                <Button
                  type="button"
                  size="lg"
                  class="shrink-0"
                  @click="triggerFileInput"
                >
                  {{ t('fulfillment.manualRequest.chooseFile') }}
                </Button>

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

                <Button
                  type="button"
                  variant="destructive"
                  size="icon"
                  class="w-9 shrink-0"
                  :disabled="!form.handover_proof"
                  @click="clearFile"
                >
                  <Trash2 class="w-4 h-4" />
                </Button>
              </div>
              <p class="text-[10px] text-muted-foreground ml-1">
                {{ t('inventory.maxFileSize2Mb', 'Ukuran maksimal file 2MB') }}
              </p>
            </div>
          </FieldContent>
          <FieldError v-if="errors.handover_proof">{{ errors.handover_proof }}</FieldError>
        </Field>
      </div>
    </div>
  </div>
</template>
