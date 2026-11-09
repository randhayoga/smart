<script lang="ts">
/**
 * Module-level in-memory cache for active users selection list.
 * Persists across modal open/close cycles and page navigations in SPA mode,
 * achieving 0ms latency and 0 redundant HTTP requests.
 */
export interface UserOption {
  id: number | string;
  name: string;
}

let cachedUsers: UserOption[] | null = null;
let activeFetchUsersPromise: Promise<UserOption[]> | null = null;
</script>

<script setup lang="ts">
/**
 * MaintenanceTab Component.
 * Provides a structured 2-column input form for recording preventive or corrective
 * maintenance, matching the exact styling, margins, typography, and controls of
 * inventory edit/create modals.
 */
import { ref, computed, watch, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { router } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
import { ChevronDown, Trash2 } from 'lucide-vue-next';
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

const props = defineProps<{
  asset: any;
  users?: UserOption[];
}>();

const emit = defineEmits<{
  (e: 'saved', payload: { maintenance: any; assetCondition: string }): void;
  (e: 'submitting', isSubmitting: boolean): void;
}>();

const { t } = useI18n();

const isSubmitting = ref(false);
const fetchedUsers = ref<UserOption[]>(cachedUsers || []);

// Aggressively populate cachedUsers if props.users are supplied
watch(
  () => props.users,
  (newUsers) => {
    if (Array.isArray(newUsers) && newUsers.length > 0) {
      cachedUsers = newUsers;
      fetchedUsers.value = newUsers;
    }
  },
  { immediate: true }
);

// If users not supplied by props and not in cache, fetch lazily once
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
const proofFileInputRef = ref<HTMLInputElement | null>(null);

// Reset form
const resetForm = () => {
  const activeUserId = props.asset?.active_borrowing?.user_id;
  const defaultUserId = activeUserId !== undefined && activeUserId !== null && activeUserId !== ''
    ? Number(activeUserId)
    : '';

  form.value = {
    source: 'SMART',
    reference: '',
    type: 'Preventive',
    user_id: defaultUserId,
    date: new Date().toISOString().split('T')[0],
    cost: 0,
    problem: '',
    solution: '',
    condition: 'Tidak Berubah',
    proof_file: null,
    proof_file_name: '',
  };
  errors.value = {};
  if (proofFileInputRef.value) {
    proofFileInputRef.value.value = '';
  }
};

watch(
  () => props.asset?.id,
  (newId) => {
    if (newId) {
      resetForm();
    }
  },
  { immediate: true }
);

// Reactively set user_id if active_borrowing arrives asynchronously
watch(
  () => props.asset?.active_borrowing,
  (borrowing) => {
    if (borrowing?.user_id !== undefined && borrowing?.user_id !== null && borrowing?.user_id !== '') {
      if (!form.value.user_id) {
        form.value.user_id = Number(borrowing.user_id);
      }
    }
  },
  { immediate: true, deep: true }
);

// File upload handlers
const triggerProofFileInput = () => {
  proofFileInputRef.value?.click();
};

const handleProofFileUpload = (e: Event) => {
  const target = e.target as HTMLInputElement;
  const file = target.files?.[0];
  if (!file) return;

  const allowedTypes = ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
  if (!allowedTypes.includes(file.type)) {
    toast.error(t('inventory.invalidDocFormat'));
    target.value = '';
    return;
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
};

const handleDeleteProofFile = () => {
  form.value.proof_file = null;
  form.value.proof_file_name = '';
  if (proofFileInputRef.value) {
    proofFileInputRef.value.value = '';
  }
};

const viewProofFile = () => {
  if (form.value.proof_file) {
    window.open(URL.createObjectURL(form.value.proof_file), '_blank');
  }
};

// Form submission handler exposed to parent
const submitForm = async () => {
  errors.value = {};

  if (!form.value.source) {
    errors.value.source = t('common.required');
  }
  if (form.value.source === 'TiSu' && !form.value.reference.trim()) {
    errors.value.reference = t('inventory.referenceRequired');
  }
  if (!form.value.type) {
    errors.value.type = t('common.required');
  }
  if (!form.value.user_id) {
    errors.value.user_id = t('common.required');
  }
  if (!form.value.date) {
    errors.value.date = t('common.required');
  }
  if (form.value.cost === '' || form.value.cost === null || Number(form.value.cost) < 0) {
    errors.value.cost = t('common.required');
  }
  if (!form.value.problem.trim()) {
    errors.value.problem = t('common.required');
  }
  if (!form.value.solution.trim()) {
    errors.value.solution = t('common.required');
  }
  if (!form.value.condition) {
    errors.value.condition = t('common.required');
  }

  if (Object.keys(errors.value).length > 0) {
    toast.error(t('inventory.fixErrors'));
    return false;
  }

  isSubmitting.value = true;
  emit('submitting', true);

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
    onSuccess: (page) => {
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
      emit('submitting', false);
    },
  });

  return true;
};

defineExpose({
  submitForm,
  resetForm,
  isSubmitting,
});
</script>

<template>
  <div class="grid grid-cols-1 md:grid-cols-2 gap-x-12 gap-y-6">
    <!-- Left Column -->
    <div class="space-y-6">
      <!-- Sumber / Source -->
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
                class="w-full justify-between rounded-[14px] font-normal h-10 px-4"
                :class="[errors.source ? 'border-destructive' : '']"
              >
                {{ form.source === 'SMART' ? t('inventory.sourceSmart') : t('inventory.sourceTisu') }}
                <ChevronDown class="w-4 h-4 opacity-50" />
              </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="start" class="w-(--reka-dropdown-menu-trigger-width) min-w-(--reka-dropdown-menu-trigger-width) rounded-[14px] z-[1001]">
              <DropdownMenuItem @select="form.source = 'SMART'">{{ t('inventory.sourceSmart') }}</DropdownMenuItem>
              <DropdownMenuItem @select="form.source = 'TiSu'">{{ t('inventory.sourceTisu') }}</DropdownMenuItem>
            </DropdownMenuContent>
          </DropdownMenu>
        </FieldContent>
        <FieldError v-if="errors.source">{{ errors.source }}</FieldError>
      </Field>

      <!-- Referensi / Reference -->
      <Field :data-invalid="!!errors.reference || undefined">
        <FieldLabel>
          <span>{{ t('inventory.reference') }}<span v-if="form.source === 'TiSu'" class="text-rose-500">*</span></span>
        </FieldLabel>
        <FieldContent>
          <input
            v-model="form.reference"
            type="text"
            maxlength="255"
            :placeholder="t('inventory.referencePlaceholder')"
            class="w-full px-4 py-2 border rounded-[14px] text-sm h-10 transition-colors bg-background focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary"
            :class="[
              errors.reference ? 'border-destructive focus:ring-destructive/20 focus:border-destructive' : 'border-input'
            ]"
          />
        </FieldContent>
        <FieldError v-if="errors.reference">{{ errors.reference }}</FieldError>
      </Field>

      <!-- Tipe Perawatan / Maintenance Type -->
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
                class="w-full justify-between rounded-[14px] font-normal h-10 px-4"
                :class="[errors.type ? 'border-destructive' : '']"
              >
                {{ form.type === 'Preventive' ? t('inventory.typePreventive') : t('inventory.typeCorrective') }}
                <ChevronDown class="w-4 h-4 opacity-50" />
              </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="start" class="w-(--reka-dropdown-menu-trigger-width) min-w-(--reka-dropdown-menu-trigger-width) rounded-[14px] z-[1001]">
              <DropdownMenuItem @select="form.type = 'Preventive'">{{ t('inventory.typePreventive') }}</DropdownMenuItem>
              <DropdownMenuItem @select="form.type = 'Corrective'">{{ t('inventory.typeCorrective') }}</DropdownMenuItem>
            </DropdownMenuContent>
          </DropdownMenu>
        </FieldContent>
        <FieldError v-if="errors.type">{{ errors.type }}</FieldError>
      </Field>

      <!-- User (Combobox defaulting to borrower) -->
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
            widthClass="w-full"
          />
        </FieldContent>
        <FieldError v-if="errors.user_id">{{ errors.user_id }}</FieldError>
      </Field>

      <!-- Kondisi Setelah Perawatan -->
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
                class="w-full justify-between rounded-[14px] font-normal h-10 px-4"
                :class="[errors.condition ? 'border-destructive' : '']"
              >
                {{ form.condition === 'Tidak Berubah' ? t('inventory.conditionUnchanged') : form.condition }}
                <ChevronDown class="w-4 h-4 opacity-50" />
              </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="start" class="w-(--reka-dropdown-menu-trigger-width) min-w-(--reka-dropdown-menu-trigger-width) rounded-[14px] z-[1001]">
              <DropdownMenuItem @select="form.condition = 'Tidak Berubah'">{{ t('inventory.conditionUnchanged') }}</DropdownMenuItem>
              <DropdownMenuItem @select="form.condition = 'Bagus'">{{ t('inventory.conditionGood') }}</DropdownMenuItem>
              <DropdownMenuItem @select="form.condition = 'Rusak'">{{ t('inventory.conditionDamaged') }}</DropdownMenuItem>
            </DropdownMenuContent>
          </DropdownMenu>
        </FieldContent>
        <FieldError v-if="errors.condition">{{ errors.condition }}</FieldError>
      </Field>
    </div>

    <!-- Right Column -->
    <div class="space-y-6">
      <!-- Tanggal / Date -->
      <Field :data-invalid="!!errors.date || undefined">
        <FieldLabel>
          <span>{{ t('inventory.date') }}<span class="text-rose-500">*</span></span>
        </FieldLabel>
        <FieldContent>
          <input
            v-model="form.date"
            type="date"
            class="w-full px-4 py-2 border rounded-[14px] bg-background focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors text-sm h-10"
            :class="[errors.date ? 'border-destructive focus:ring-destructive/20 focus:border-destructive' : 'border-input']"
          />
        </FieldContent>
        <FieldError v-if="errors.date">{{ errors.date }}</FieldError>
      </Field>

      <!-- Biaya / Cost -->
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
          />
        </FieldContent>
        <FieldError v-if="errors.cost">{{ errors.cost }}</FieldError>
      </Field>

      <!-- Bukti Perawatan / Proof of Maintenance -->
      <Field :data-invalid="!!errors.proof_file || undefined">
        <FieldLabel>
          <span>{{ t('inventory.proofOfMaintenance') }}</span>
        </FieldLabel>
        <FieldContent>
          <div class="flex items-center gap-2">
            <div
              class="flex-1 px-3 py-2 text-sm border rounded-[10px] bg-muted/40 truncate h-10 flex items-center"
              :class="[
                form.proof_file_name
                  ? 'cursor-pointer hover:bg-muted/20 hover:text-primary transition-colors text-foreground font-medium underline decoration-dotted'
                  : 'text-muted-foreground cursor-default',
                errors.proof_file ? 'border-destructive' : 'border-input',
              ]"
              @click="form.proof_file_name && viewProofFile()"
            >
              {{ form.proof_file_name || t('inventory.noProofSelected') }}
            </div>
            <input
              ref="proofFileInputRef"
              type="file"
              class="hidden"
              accept=".pdf,.jpg,.jpeg,.png,.webp"
              @change="handleProofFileUpload"
            />
            <Button
              type="button"
              size="lg"
              class="shrink-0"
              @click="triggerProofFileInput"
            >
              {{ t('inventory.chooseFile') }}
            </Button>
            <Button
              type="button"
              variant="destructive"
              size="icon"
              class="w-9 shrink-0"
              :disabled="!form.proof_file_name"
              @click="handleDeleteProofFile"
            >
              <Trash2 class="w-4 h-4" />
            </Button>
          </div>
        </FieldContent>
        <FieldError v-if="errors.proof_file">{{ errors.proof_file }}</FieldError>
      </Field>

      <!-- Permasalahan / Problem -->
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
            class="w-full px-4 py-2 border rounded-[14px] bg-background focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors text-sm resize-none"
            :class="[errors.problem ? 'border-destructive focus:ring-destructive/20 focus:border-destructive' : 'border-input']"
          />
        </FieldContent>
        <FieldError v-if="errors.problem">{{ errors.problem }}</FieldError>
      </Field>

      <!-- Solusi / Solution -->
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
            class="w-full px-4 py-2 border rounded-[14px] bg-background focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors text-sm resize-none"
            :class="[errors.solution ? 'border-destructive focus:ring-destructive/20 focus:border-destructive' : 'border-input']"
          />
        </FieldContent>
        <FieldError v-if="errors.solution">{{ errors.solution }}</FieldError>
      </Field>
    </div>
  </div>
</template>
