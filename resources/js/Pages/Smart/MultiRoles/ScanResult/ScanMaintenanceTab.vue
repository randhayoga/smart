<script lang="ts">
/**
 * Module-level in-memory cache for active users selection list.
 * Persists across tab switches and component re-mounts for 0ms latency.
 */
interface UserOption {
  id: number | string;
  name: string;
}

let cachedUsers: UserOption[] | null = null;
let activeFetchUsersPromise: Promise<UserOption[]> | null = null;
</script>

<script setup lang="ts">
/**
 * ScanMaintenanceTab Component.
 * Mobile-optimized asset maintenance recording matching desktop MaintenanceTab rules,
 * featuring camera capture support in the Proof of Maintenance input.
 */
import { ref, computed, watch, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { router } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
import { ChevronDown, Loader2, Camera, Trash2 } from 'lucide-vue-next';
import { Button } from '@/Components/ui/button';
import { Field, FieldLabel, FieldContent, FieldError } from '@/Components/ui/field';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';
import Combobox from '@/Components/Combobox.vue';
import CurrencyInput from '@/Components/CurrencyInput.vue';
import { compressImageIfNeeded } from '@/utils/imageCompressor';

interface Props {
  asset: any;
  users?: UserOption[];
}

const props = defineProps<Props>();
const emit = defineEmits<{
  (e: 'cancel'): void;
  (e: 'saved', payload: { maintenance: any; assetCondition: string }): void;
}>();

const { t } = useI18n();

const isSubmitting = ref(false);
const fetchedUsers = ref<UserOption[]>(cachedUsers || []);

const proofFileInput = ref<HTMLInputElement | null>(null);
const proofCameraInput = ref<HTMLInputElement | null>(null);

// Ensure users list is loaded lazily once
const ensureUsersLoaded = async () => {
  if (Array.isArray(props.users) && props.users.length > 0) {
    return;
  }
  if (cachedUsers && cachedUsers.length > 0) {
    fetchedUsers.value = cachedUsers;
    return;
  }
  if (!activeFetchUsersPromise) {
    activeFetchUsersPromise = (async () => {
      try {
        const targetAssetId = props.asset?.id || 1;
        const res = await fetch(`/smart/stock-management/assets/${targetAssetId}/maintenances`, {
          headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        if (res.ok) {
          const data = await res.json();
          if (Array.isArray(data.users)) {
            cachedUsers = data.users;
            return data.users;
          }
        }
      } catch {
        // Ignore fetch errors
      }
      return [];
    })();
  }
  const users = await activeFetchUsersPromise;
  if (users && users.length > 0) {
    fetchedUsers.value = users;
  }
};

onMounted(() => {
  void ensureUsersLoaded();
});

const availableUsers = computed(() => {
  if (Array.isArray(props.users) && props.users.length > 0) {
    return props.users;
  }
  return fetchedUsers.value;
});

// Form state
const form = ref({
  source: 'SMART',
  reference: '',
  type: 'Preventive',
  user_id: '' as string | number,
  date: new Date().toISOString().split('T')[0],
  cost: 0 as number | '',
  problem: '',
  solution: '',
  condition: 'Tidak Berubah',
  proof_file: null as File | null,
  proof_file_name: '',
});

const errors = ref<Record<string, string>>({});

// Initialize borrower default
const initUserFromAsset = () => {
  const activeUserId = props.asset?.active_borrowing?.user_id;
  if (activeUserId !== undefined && activeUserId !== null && activeUserId !== '') {
    form.value.user_id = Number(activeUserId);
  }
};

watch(
  () => props.asset,
  () => {
    initUserFromAsset();
  },
  { immediate: true, deep: true }
);

// Proof file actions
const triggerProofFileInput = () => {
  proofFileInput.value?.click();
};

const triggerProofCameraInput = () => {
  proofCameraInput.value?.click();
};

const handleProofFileUpload = async (e: Event) => {
  const target = e.target as HTMLInputElement;
  const rawFile = target.files?.[0];
  if (!rawFile) return;

  const allowedTypes = ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
  if (!allowedTypes.includes(rawFile.type) && !rawFile.type.startsWith('image/')) {
    toast.error(t('inventory.invalidDocFormat'));
    target.value = '';
    return;
  }

  try {
    let file = rawFile;
    if (rawFile.type.startsWith('image/')) {
      file = await compressImageIfNeeded(rawFile);
    }
    if (file.size > 2 * 1024 * 1024) {
      toast.error(t('inventory.fileTooLarge2Mb'));
      target.value = '';
      return;
    }
    form.value.proof_file = file;
    form.value.proof_file_name = file.name;
    if (errors.value.proof_file) {
      delete errors.value.proof_file;
    }
  } catch (err) {
    console.error('Failed to process file:', err);
    toast.error(t('scanner.processImageFailed'));
  } finally {
    target.value = '';
  }
};

const handleDeleteProofFile = () => {
  form.value.proof_file = null;
  form.value.proof_file_name = '';
  if (proofFileInput.value) proofFileInput.value.value = '';
  if (proofCameraInput.value) proofCameraInput.value.value = '';
};

const viewProofFile = () => {
  if (form.value.proof_file) {
    const objectUrl = URL.createObjectURL(form.value.proof_file);
    window.open(objectUrl, '_blank');
    setTimeout(() => URL.revokeObjectURL(objectUrl), 10000);
  }
};

const resetForm = () => {
  form.value = {
    source: 'SMART',
    reference: '',
    type: 'Preventive',
    user_id: '',
    date: new Date().toISOString().split('T')[0],
    cost: 0,
    problem: '',
    solution: '',
    condition: 'Tidak Berubah',
    proof_file: null,
    proof_file_name: '',
  };
  initUserFromAsset();
  errors.value = {};
};

const handleSubmit = () => {
  errors.value = {};

  if (!form.value.source) errors.value.source = t('common.required');
  if (form.value.source === 'TiSu' && !form.value.reference.trim()) {
    errors.value.reference = t('inventory.referenceRequired');
  }
  if (!form.value.type) errors.value.type = t('common.required');
  if (!form.value.user_id) errors.value.user_id = t('common.required');
  if (!form.value.date) errors.value.date = t('common.required');
  if (form.value.cost === '' || form.value.cost === null || Number(form.value.cost) < 0) {
    errors.value.cost = t('common.required');
  }
  if (!form.value.problem.trim()) errors.value.problem = t('common.required');
  if (!form.value.solution.trim()) errors.value.solution = t('common.required');
  if (!form.value.condition) errors.value.condition = t('common.required');

  if (Object.keys(errors.value).length > 0) {
    toast.error(t('inventory.fixErrors'));
    return;
  }

  isSubmitting.value = true;

  const payload: Record<string, any> = {
    source: form.value.source,
    type: form.value.type,
    user_id: form.value.user_id,
    date: form.value.date,
    cost: form.value.cost || 0,
    problem: form.value.problem,
    solution: form.value.solution,
    condition: form.value.condition,
  };

  if (form.value.source === 'TiSu' && form.value.reference) {
    payload.reference = form.value.reference;
  }
  if (form.value.proof_file) {
    payload.proof_file = form.value.proof_file;
  }

  router.post(`/smart/stock-management/assets/${props.asset.id}/maintenances`, payload, {
    preserveScroll: true,
    preserveState: true,
    onSuccess: () => {
      toast.success(t('inventory.maintenanceSavedSuccess'));
      const newCondition = form.value.condition !== 'Tidak Berubah' ? form.value.condition : props.asset?.condition;
      if (props.asset && newCondition) {
        props.asset.condition = newCondition;
      }
      emit('saved', {
        maintenance: payload,
        assetCondition: newCondition,
      });
      resetForm();
    },
    onError: (errs: any) => {
      errors.value = Object.keys(errs).reduce((acc, key) => {
        acc[key] = Array.isArray(errs[key]) ? errs[key][0] : String(errs[key]);
        return acc;
      }, {} as Record<string, string>);
      toast.error(t('inventory.fixErrors'));
    },
    onFinish: () => {
      isSubmitting.value = false;
    },
  });
};
</script>

<template>
  <div class="space-y-6">
    <div class="bg-card rounded-2xl p-5 border border-border shadow-sm space-y-4">
      <!-- 1. Sumber / Source -->
      <Field :data-invalid="!!errors.source || undefined">
        <FieldLabel>
          <span>{{ t('inventory.source') }}<span class="text-rose-500">*</span></span>
        </FieldLabel>
        <FieldContent>
          <DropdownMenu>
            <DropdownMenuTrigger asChild>
              <Button
                type="button"
                variant="outline"
                class="w-full justify-between rounded-xl font-normal h-10 px-4 text-foreground"
                :class="[errors.source ? 'border-destructive' : '']"
              >
                {{ form.source === 'SMART' ? t('inventory.sourceSmart') : t('inventory.sourceTisu') }}
                <ChevronDown class="w-4 h-4 opacity-50" />
              </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="start" class="w-(--reka-dropdown-menu-trigger-width) min-w-(--reka-dropdown-menu-trigger-width) rounded-xl z-[1001]">
              <DropdownMenuItem @select="form.source = 'SMART'">{{ t('inventory.sourceSmart') }}</DropdownMenuItem>
              <DropdownMenuItem @select="form.source = 'TiSu'">{{ t('inventory.sourceTisu') }}</DropdownMenuItem>
            </DropdownMenuContent>
          </DropdownMenu>
        </FieldContent>
        <FieldError v-if="errors.source">{{ errors.source }}</FieldError>
      </Field>

      <!-- 2. Referensi (if TiSu) -->
      <Field v-if="form.source === 'TiSu'" :data-invalid="!!errors.reference || undefined">
        <FieldLabel>
          <span>{{ t('inventory.reference') }}<span class="text-rose-500">*</span></span>
        </FieldLabel>
        <FieldContent>
          <input
            v-model="form.reference"
            type="text"
            maxlength="255"
            :placeholder="t('inventory.referencePlaceholder')"
            class="w-full px-4 py-2 border rounded-xl text-sm h-10 transition-colors bg-background focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary"
            :class="[errors.reference ? 'border-destructive focus:ring-destructive/20 focus:border-destructive' : 'border-input']"
          />
        </FieldContent>
        <FieldError v-if="errors.reference">{{ errors.reference }}</FieldError>
      </Field>

      <!-- 3. Tipe Perawatan -->
      <Field :data-invalid="!!errors.type || undefined">
        <FieldLabel>
          <span>{{ t('inventory.maintenanceType') }}<span class="text-rose-500">*</span></span>
        </FieldLabel>
        <FieldContent>
          <DropdownMenu>
            <DropdownMenuTrigger asChild>
              <Button
                type="button"
                variant="outline"
                class="w-full justify-between rounded-xl font-normal h-10 px-4 text-foreground"
                :class="[errors.type ? 'border-destructive' : '']"
              >
                {{ form.type === 'Preventive' ? t('inventory.typePreventive') : t('inventory.typeCorrective') }}
                <ChevronDown class="w-4 h-4 opacity-50" />
              </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="start" class="w-(--reka-dropdown-menu-trigger-width) min-w-(--reka-dropdown-menu-trigger-width) rounded-xl z-[1001]">
              <DropdownMenuItem @select="form.type = 'Preventive'">{{ t('inventory.typePreventive') }}</DropdownMenuItem>
              <DropdownMenuItem @select="form.type = 'Corrective'">{{ t('inventory.typeCorrective') }}</DropdownMenuItem>
            </DropdownMenuContent>
          </DropdownMenu>
        </FieldContent>
        <FieldError v-if="errors.type">{{ errors.type }}</FieldError>
      </Field>

      <!-- 4. Pengguna / User -->
      <Field :data-invalid="!!errors.user_id || undefined">
        <FieldLabel>
          <span>{{ t('inventory.maintenanceUser') }}<span class="text-rose-500">*</span></span>
        </FieldLabel>
        <FieldContent>
          <Combobox
            v-model="form.user_id"
            :options="availableUsers"
            :placeholder="t('inventory.userSelectPlaceholder')"
            :error="!!errors.user_id"
            widthClass="w-full h-10 px-4 rounded-xl"
          />
        </FieldContent>
        <FieldError v-if="errors.user_id">{{ errors.user_id }}</FieldError>
      </Field>

      <!-- 5. Kondisi Setelah Perawatan -->
      <Field :data-invalid="!!errors.condition || undefined">
        <FieldLabel>
          <span>{{ t('inventory.conditionAfterMaintenance') }}<span class="text-rose-500">*</span></span>
        </FieldLabel>
        <FieldContent>
          <DropdownMenu>
            <DropdownMenuTrigger asChild>
              <Button
                type="button"
                variant="outline"
                class="w-full justify-between rounded-xl font-normal h-10 px-4 text-foreground"
                :class="[errors.condition ? 'border-destructive' : '']"
              >
                {{ form.condition === 'Tidak Berubah' ? t('inventory.conditionUnchanged') : form.condition }}
                <ChevronDown class="w-4 h-4 opacity-50" />
              </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="start" class="w-(--reka-dropdown-menu-trigger-width) min-w-(--reka-dropdown-menu-trigger-width) rounded-xl z-[1001]">
              <DropdownMenuItem @select="form.condition = 'Tidak Berubah'">{{ t('inventory.conditionUnchanged') }}</DropdownMenuItem>
              <DropdownMenuItem @select="form.condition = 'Bagus'">{{ t('inventory.conditionGood') }}</DropdownMenuItem>
              <DropdownMenuItem @select="form.condition = 'Rusak'">{{ t('inventory.conditionDamaged') }}</DropdownMenuItem>
            </DropdownMenuContent>
          </DropdownMenu>
        </FieldContent>
        <FieldError v-if="errors.condition">{{ errors.condition }}</FieldError>
      </Field>

      <!-- 6. Tanggal / Date -->
      <Field :data-invalid="!!errors.date || undefined">
        <FieldLabel>
          <span>{{ t('inventory.date') }}<span class="text-rose-500">*</span></span>
        </FieldLabel>
        <FieldContent>
          <input
            v-model="form.date"
            type="date"
            class="w-full px-4 py-2 border rounded-xl bg-background focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors text-sm h-10"
            :class="[errors.date ? 'border-destructive focus:ring-destructive/20 focus:border-destructive' : 'border-input']"
          />
        </FieldContent>
        <FieldError v-if="errors.date">{{ errors.date }}</FieldError>
      </Field>

      <!-- 7. Biaya / Cost -->
      <Field :data-invalid="!!errors.cost || undefined">
        <FieldLabel>
          <span>{{ t('inventory.cost') }}<span class="text-rose-500">*</span></span>
        </FieldLabel>
        <FieldContent>
          <CurrencyInput
            v-model="form.cost"
            prefix="Rp"
            placeholder="0"
            :error="!!errors.cost"
            class="rounded-xl"
          />
        </FieldContent>
        <FieldError v-if="errors.cost">{{ errors.cost }}</FieldError>
      </Field>

      <!-- 8. Bukti Perawatan (Proof of Maintenance with Camera Selector) -->
      <Field :data-invalid="!!errors.proof_file || undefined">
        <FieldLabel>
          <span>{{ t('inventory.proofOfMaintenance') }}</span>
        </FieldLabel>
        <FieldContent>
          <div class="space-y-2">
            <!-- File Name Preview + Delete Button -->
            <div class="flex items-center gap-2">
              <div
                class="flex-1 min-w-0 px-4 py-2 text-sm border rounded-xl truncate flex items-center h-10"
                :class="[
                  form.proof_file_name
                    ? 'cursor-pointer hover:bg-muted/20 hover:text-primary transition-colors text-foreground font-medium underline decoration-dotted bg-muted/10'
                    : 'text-muted-foreground cursor-default bg-muted/10',
                  errors.proof_file ? 'border-destructive' : 'border-input'
                ]"
                @click="form.proof_file_name && viewProofFile()"
              >
                {{ form.proof_file_name || t('inventory.noProofSelected') }}
              </div>
              <Button
                type="button"
                variant="destructive"
                size="icon"
                class="w-10 h-10 rounded-xl shrink-0 cursor-pointer"
                :disabled="!form.proof_file_name"
                @click="handleDeleteProofFile"
                :title="t('common.delete')"
              >
                <Trash2 class="w-4 h-4" />
              </Button>
            </div>

            <!-- Action Buttons Row: Upload (left half) & Green Camera (right half) -->
            <div class="flex gap-2 w-full">
              <input ref="proofFileInput" type="file" class="hidden" accept=".pdf,.jpg,.jpeg,.png,.webp" @change="handleProofFileUpload" />
              <input ref="proofCameraInput" type="file" class="hidden" accept="image/*" capture="environment" @change="handleProofFileUpload" />

              <Button
                type="button"
                @click="triggerProofFileInput"
                size="lg"
                class="flex-1 rounded-xl h-10 cursor-pointer font-semibold"
              >
                {{ t('inventory.chooseFile') }}
              </Button>

              <Button
                type="button"
                @click="triggerProofCameraInput"
                variant="success"
                size="lg"
                class="flex-1 rounded-xl h-10 bg-emerald-600 hover:bg-emerald-700 text-white cursor-pointer font-semibold flex items-center justify-center gap-2"
                :title="t('scanner.takePhoto')"
              >
                <Camera class="w-4 h-4" />
                <span>{{ t('scanner.takePhoto') }}</span>
              </Button>
            </div>

            <p class="text-[10px] text-muted-foreground ml-1">{{ t('scanner.maxFileSize') }}</p>
          </div>
        </FieldContent>
        <FieldError v-if="errors.proof_file">{{ errors.proof_file }}</FieldError>
      </Field>

      <!-- 9. Permasalahan / Problem -->
      <Field :data-invalid="!!errors.problem || undefined">
        <FieldLabel>
          <span>{{ t('inventory.problem') }}<span class="text-rose-500">*</span></span>
        </FieldLabel>
        <FieldContent>
          <textarea
            v-model="form.problem"
            rows="3"
            maxlength="2000"
            :placeholder="t('inventory.problemPlaceholder')"
            class="w-full px-4 py-2 border rounded-xl bg-background focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors text-sm resize-none"
            :class="[errors.problem ? 'border-destructive focus:ring-destructive/20 focus:border-destructive' : 'border-input']"
          />
        </FieldContent>
        <FieldError v-if="errors.problem">{{ errors.problem }}</FieldError>
      </Field>

      <!-- 10. Solusi / Solution -->
      <Field :data-invalid="!!errors.solution || undefined">
        <FieldLabel>
          <span>{{ t('inventory.solution') }}<span class="text-rose-500">*</span></span>
        </FieldLabel>
        <FieldContent>
          <textarea
            v-model="form.solution"
            rows="3"
            maxlength="2000"
            :placeholder="t('inventory.solutionPlaceholder')"
            class="w-full px-4 py-2 border rounded-xl bg-background focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors text-sm resize-none"
            :class="[errors.solution ? 'border-destructive focus:ring-destructive/20 focus:border-destructive' : 'border-input']"
          />
        </FieldContent>
        <FieldError v-if="errors.solution">{{ errors.solution }}</FieldError>
      </Field>

      <!-- Action Buttons -->
      <div class="border-t border-border pt-4 flex gap-3">
        <Button
          @click="emit('cancel')"
          variant="white"
          size="lg"
          class="flex-1 rounded-xl h-11 text-sm font-semibold cursor-pointer"
        >
          {{ t('common.cancel') }}
        </Button>
        <Button
          @click="handleSubmit"
          :disabled="isSubmitting"
          variant="primary"
          size="lg"
          class="flex-1 rounded-xl h-11 text-sm font-semibold relative cursor-pointer"
        >
          <Loader2 v-if="isSubmitting" class="absolute inset-0 m-auto h-5 w-5 animate-spin" />
          <span :class="{ 'opacity-0': isSubmitting }" class="flex items-center justify-center">
            {{ t('inventory.saveMaintenance') || t('common.save') }}
          </span>
        </Button>
      </div>
    </div>
  </div>
</template>
