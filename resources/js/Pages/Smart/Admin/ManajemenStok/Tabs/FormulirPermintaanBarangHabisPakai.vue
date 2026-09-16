<script setup lang="ts">
/**
 * Shared Request Form component for manual consumable stock deductions.
 * Supports both non-specific catalog items (FIFO allocation) and specific batch LOTs.
 */
import { ref, computed, watch, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { router } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
import { Package, AlertCircle, Loader2 } from 'lucide-vue-next';
import { Button } from '@/Components/ui/button';
import Combobox from '@/Components/Combobox.vue';
import { Field, FieldLabel, FieldContent, FieldError } from '@/Components/ui/field';

interface Props {
  mode: 'barang' | 'lot';
  barang?: any;
  lot?: any;
  availableStock: number;
}

const props = defineProps<Props>();

const emit = defineEmits<{
  (e: 'success'): void;
  (e: 'cancel'): void;
}>();

const { t } = useI18n();

const getTodayDateString = () => {
  const d = new Date();
  const year = d.getFullYear();
  const month = String(d.getMonth() + 1).padStart(2, '0');
  const day = String(d.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
};

// Form State
const userId = ref<number | string | null>(null);
const requestDate = ref<string>(getTodayDateString());
const utilization = ref<'corporate' | 'project' | null>(null);
const orgId = ref<number | string | null>(null);
const projectId = ref<number | string | null>(null);
const quantity = ref<number>(1);
const note = ref('');
const isSubmitting = ref(false);
const errors = ref<Record<string, string>>({});

// Dynamic Options State
const rawUsersList = ref<{
  id: number;
  name: string;
  employee_id?: string;
  department?: { id: number; name: string } | null;
}[]>([]);
const userDepartments = ref<{ id: number; name: string }[]>([]);
const userProjects = ref<{ id: number; name: string; no_project?: string }[]>([]);
const isLoadingOptions = ref(false);
const isLoadingUserOptions = ref(false);

const isRequesterSelected = computed(() => !!userId.value);

// Fetch user-specific department & projects when Requester is selected
const fetchUserOptions = async (targetUserId: number | string) => {
  isLoadingUserOptions.value = true;
  try {
    const res = await fetch(`/smart/inventory/consumables/request-options?user_id=${targetUserId}`);
    if (res.ok) {
      const data = await res.json();
      userDepartments.value = data.departments || [];
      userProjects.value = data.projects || [];

      // Auto-default department to the requester's department
      if (userDepartments.value.length > 0) {
        orgId.value = userDepartments.value[0].id;
      } else {
        orgId.value = null;
      }

      // Auto-default project if requester is assigned to exactly 1 project
      if (userProjects.value.length === 1) {
        projectId.value = userProjects.value[0].id;
      } else {
        projectId.value = null;
      }
    }
  } catch (err) {
    console.error('Failed to fetch user specific options:', err);
  } finally {
    isLoadingUserOptions.value = false;
  }
};

// When Requester changes or is cleared, reset dependent fields and fetch user-scoped options
watch(userId, (newVal) => {
  if (!newVal) {
    utilization.value = null;
    orgId.value = null;
    projectId.value = null;
    userDepartments.value = [];
    userProjects.value = [];
  } else {
    if (!utilization.value) {
      utilization.value = 'corporate';
    }
    // Pre-populate department immediately from rawUsersList if available for instant UX
    const selectedUser = rawUsersList.value.find(u => u.id === newVal);
    if (selectedUser?.department) {
      userDepartments.value = [selectedUser.department];
      orgId.value = selectedUser.department.id;
    }
    fetchUserOptions(newVal);
  }
});

// When Utilization changes, clear opposing target selection
watch(utilization, (newVal) => {
  if (newVal === 'corporate') {
    projectId.value = null;
    // Ensure department is defaulted if available
    if (userDepartments.value.length > 0 && !orgId.value) {
      orgId.value = userDepartments.value[0].id;
    }
  } else if (newVal === 'project') {
    orgId.value = null;
    // Default project if only 1 project
    if (userProjects.value.length === 1 && !projectId.value) {
      projectId.value = userProjects.value[0].id;
    }
  }
});

const usersOptions = computed(() => {
  return rawUsersList.value.map(user => {
    // Strip trailing parenthesized employee_id or npk if present in name
    const cleanedName = user.name ? user.name.replace(/\s*\([^)]*\)\s*$/, '').trim() : '';
    const empId = user.employee_id || '';
    const formattedName = empId && cleanedName ? `${empId} - ${cleanedName}` : (cleanedName || empId || '');

    return {
      id: user.id,
      name: formattedName,
    };
  });
});

const fetchOptions = async () => {
  isLoadingOptions.value = true;
  try {
    const res = await fetch('/smart/inventory/consumables/request-options');
    if (res.ok) {
      const data = await res.json();
      rawUsersList.value = data.users || [];
    }
  } catch (err) {
    console.error('Failed to fetch request options:', err);
  } finally {
    isLoadingOptions.value = false;
  }
};

onMounted(() => {
  fetchOptions();
});

const targetItemTitle = computed(() => {
  if (props.mode === 'lot' && props.lot) {
    const lotNumber = props.lot.number || '-';
    const barangName = props.lot.barang_nama || props.barang?.name || '';
    return `${lotNumber} ${barangName ? `(${barangName})` : ''}`;
  }
  if (props.barang) {
    return `${props.barang.code || props.barang.number || '-'} - ${props.barang.name || '-'}`;
  }
  return '-';
});

const uomLabel = computed(() => {
  if (props.mode === 'lot') {
    return props.lot?.barang_uom || props.barang?.uom || '';
  }
  return props.barang?.uom || '';
});

const isOutOfStock = computed(() => props.availableStock <= 0);

const handleQuantityInput = (event: Event) => {
  const target = event.target as HTMLInputElement;
  let val = parseInt(target.value, 10);
  if (isNaN(val) || val < 1) {
    val = 1;
  }
  if (props.availableStock > 0 && val > props.availableStock) {
    val = props.availableStock;
  }
  quantity.value = val;
};

const validateForm = (): boolean => {
  errors.value = {};

  if (!userId.value) {
    errors.value.user_id = t('inventory.requesterRequired');
  }

  if (!requestDate.value) {
    errors.value.request_date = t('inventory.requestDateRequired');
  }

  if (!utilization.value) {
    errors.value.utilization = t('inventory.utilizationRequired');
  } else if (utilization.value === 'corporate' && !orgId.value) {
    errors.value.org_id = t('inventory.departmentRequired');
  } else if (utilization.value === 'project' && !projectId.value) {
    errors.value.project_id = t('inventory.projectRequired');
  }

  if (!quantity.value || quantity.value < 1) {
    errors.value.quantity = t('inventory.requestAmountRequired');
  } else if (props.availableStock > 0 && quantity.value > props.availableStock) {
    errors.value.quantity = `${t('inventory.insufficientStock')} (Maks: ${props.availableStock})`;
  }

  return Object.keys(errors.value).length === 0;
};

const handleSubmit = () => {
  if (isOutOfStock.value) {
    toast.error(t('inventory.stockUnavailableNotice'));
    return;
  }

  if (!validateForm()) {
    return;
  }

  const submitUrl = props.mode === 'lot' && props.lot?.id
    ? `/smart/inventory/lots/${props.lot.id}/manual-request`
    : `/smart/inventory/barangs/${props.barang?.id}/manual-request`;

  isSubmitting.value = true;

  router.post(
    submitUrl,
    {
      user_id: userId.value,
      request_date: requestDate.value,
      utilization: utilization.value,
      org_id: utilization.value === 'corporate' ? orgId.value : null,
      project_id: utilization.value === 'project' ? projectId.value : null,
      quantity: quantity.value,
      note: note.value.trim() ? note.value.trim() : null,
    },
    {
      preserveScroll: true,
      onSuccess: (pageResult: any) => {
        isSubmitting.value = false;
        errors.value = {};
        if (!pageResult?.props?.flash?.success) {
          toast.success(t('inventory.manualRequestSuccess'));
        }
        emit('success');
      },
      onError: (serverErrors: any) => {
        isSubmitting.value = false;
        errors.value = serverErrors || {};
        const firstError = Object.values(serverErrors)[0] as string | undefined;
        if (firstError) {
          toast.error(firstError);
        }
      },
      onFinish: () => {
        isSubmitting.value = false;
      }
    }
  );
};
</script>

<template>
  <div class="space-y-6 text-foreground">
    <!-- Out of stock alert -->
    <div v-if="isOutOfStock" class="p-3.5 rounded-[14px] bg-destructive/10 border border-destructive/20 flex items-start gap-3">
      <AlertCircle class="w-5 h-5 text-destructive shrink-0 mt-0.5" />
      <div class="text-sm text-destructive">
        <p class="font-semibold">{{ t('inventory.stockUnavailable') }}</p>
        <p class="mt-0.5 text-xs text-destructive/90">{{ t('inventory.stockUnavailableNotice') }}</p>
      </div>
    </div>

    <!-- Form Fields: Reordered 2-column Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
      <!-- Row 1, Col 1: Pemohon (Requester) -->
      <Field :data-invalid="!!errors.user_id || undefined">
        <FieldLabel>
          <span>{{ t('inventory.requester') }}<span class="text-rose-500">*</span></span>
        </FieldLabel>
        <FieldContent>
          <Combobox
            v-model="userId"
            :options="usersOptions"
            :search-placeholder="t('inventory.searchRequesterPlaceholder')"
            :default-label="t('inventory.selectRequester')"
            width-class="w-full h-10 px-4"
            :error="!!errors.user_id"
            :disabled="isOutOfStock || isSubmitting"
          />
        </FieldContent>
        <FieldError v-if="errors.user_id">{{ errors.user_id }}</FieldError>
      </Field>

      <!-- Row 1, Col 2: Tanggal Permintaan (Request Date) -->
      <Field :data-invalid="!!errors.request_date || undefined">
        <FieldLabel>
          <span>{{ t('inventory.requestDate') }}<span class="text-rose-500">*</span></span>
        </FieldLabel>
        <FieldContent>
          <input
            type="date"
            v-model="requestDate"
            :disabled="isOutOfStock || isSubmitting"
            :class="[
              'w-full px-4 py-2 text-sm border rounded-[14px] bg-background focus:outline-none focus:ring-2 transition-colors h-10 text-foreground',
              errors.request_date 
                ? 'border-destructive focus:ring-destructive/20 focus:border-destructive' 
                : 'border-input focus:ring-primary/20 focus:border-primary'
            ]"
          />
        </FieldContent>
        <FieldError v-if="errors.request_date">{{ errors.request_date }}</FieldError>
      </Field>

      <!-- Row 2, Col 1: Utilization Purpose -->
      <Field :data-invalid="!!errors.utilization || undefined">
        <FieldLabel>
          <span>{{ t('inventory.utilization') }}<span class="text-rose-500">*</span></span>
        </FieldLabel>
        <FieldContent>
          <div class="grid grid-cols-2 gap-3">
            <button
              type="button"
              @click="utilization = 'corporate'"
              :class="[
                'h-10 px-4 rounded-[14px] border text-sm font-medium transition-colors flex items-center justify-center gap-2',
                !isRequesterSelected || isOutOfStock || isSubmitting
                  ? 'border-input bg-muted/40 text-muted-foreground/50 cursor-not-allowed'
                  : utilization === 'corporate'
                    ? 'border-primary bg-primary/10 text-primary font-semibold cursor-pointer'
                    : 'border-input hover:bg-muted/50 text-muted-foreground cursor-pointer'
              ]"
              :disabled="!isRequesterSelected || isOutOfStock || isSubmitting"
            >
              <span>{{ t('inventory.corporate') }}</span>
            </button>
            <button
              type="button"
              @click="utilization = 'project'"
              :class="[
                'h-10 px-4 rounded-[14px] border text-sm font-medium transition-colors flex items-center justify-center gap-2',
                !isRequesterSelected || isOutOfStock || isSubmitting
                  ? 'border-input bg-muted/40 text-muted-foreground/50 cursor-not-allowed'
                  : utilization === 'project'
                    ? 'border-primary bg-primary/10 text-primary font-semibold cursor-pointer'
                    : 'border-input hover:bg-muted/50 text-muted-foreground cursor-pointer'
              ]"
              :disabled="!isRequesterSelected || isOutOfStock || isSubmitting"
            >
              <span>{{ t('inventory.project') }}</span>
            </button>
          </div>
        </FieldContent>
        <FieldError v-if="errors.utilization">{{ errors.utilization }}</FieldError>
      </Field>

      <!-- Row 2, Col 2: Dept / Project Selection -->
      <div>
        <!-- Inactive when no requester or no utilization selected -->
        <Field v-if="!isRequesterSelected || !utilization">
          <FieldLabel>
            <span>{{ t('inventory.department') }} / {{ t('inventory.project') }}<span class="text-rose-500">*</span></span>
          </FieldLabel>
          <FieldContent>
            <div class="h-10 px-4 rounded-[14px] border border-input bg-muted/30 flex items-center text-sm text-muted-foreground cursor-not-allowed">
              <span>{{ t('inventory.selectRequester') }}</span>
            </div>
          </FieldContent>
        </Field>

        <!-- Departemen (if Corporate) -->
        <Field v-else-if="utilization === 'corporate'" :data-invalid="!!errors.org_id || undefined">
          <FieldLabel>
            <span>{{ t('inventory.department') }}<span class="text-rose-500">*</span></span>
          </FieldLabel>
          <FieldContent>
            <Combobox
              v-model="orgId"
              :options="userDepartments"
              :search-placeholder="t('inventory.searchDepartmentPlaceholder')"
              :default-label="t('inventory.selectDepartment')"
              width-class="w-full h-10 px-4"
              :error="!!errors.org_id"
              :disabled="!isRequesterSelected || isOutOfStock || isSubmitting || isLoadingUserOptions"
            />
          </FieldContent>
          <FieldError v-if="errors.org_id">{{ errors.org_id }}</FieldError>
        </Field>

        <!-- Project (if Project) -->
        <Field v-else-if="utilization === 'project'" :data-invalid="!!errors.project_id || undefined">
          <FieldLabel>
            <span>{{ t('inventory.project') }}<span class="text-rose-500">*</span></span>
          </FieldLabel>
          <FieldContent>
            <Combobox
              v-model="projectId"
              :options="userProjects"
              :search-placeholder="t('inventory.searchProjectPlaceholder')"
              :default-label="userProjects.length === 0 ? t('inventory.noAssignedProjects') : t('inventory.selectProject')"
              :empty-text="t('inventory.noAssignedProjects')"
              width-class="w-full h-10 px-4"
              :error="!!errors.project_id"
              :disabled="!isRequesterSelected || isOutOfStock || isSubmitting || isLoadingUserOptions || userProjects.length === 0"
            />
          </FieldContent>
          <FieldError v-if="errors.project_id">{{ errors.project_id }}</FieldError>
        </Field>
      </div>

      <!-- Row 3, Col 1: Quantity (Jumlah Permintaan) -->
      <Field :data-invalid="!!errors.quantity || undefined">
        <FieldLabel>
          <span>{{ t('inventory.requestAmount') }}<span class="text-rose-500">*</span></span>
          <span class="text-xs text-muted-foreground ml-2">
            (Maks. {{ props.availableStock }} {{ uomLabel }})
          </span>
        </FieldLabel>
        <FieldContent>
          <input
            type="number"
            min="1"
            :max="props.availableStock > 0 ? props.availableStock : 1"
            :value="quantity"
            @input="handleQuantityInput"
            :disabled="isOutOfStock || isSubmitting"
            :class="[
              'w-full px-4 py-2 text-sm border rounded-[14px] bg-background focus:outline-none focus:ring-2 transition-colors h-10 text-foreground',
              errors.quantity
                ? 'border-destructive focus:ring-destructive/20 focus:border-destructive'
                : 'border-input focus:ring-primary/20 focus:border-primary'
            ]"
          />
        </FieldContent>
        <FieldError v-if="errors.quantity">{{ errors.quantity }}</FieldError>
      </Field>

      <!-- Row 4: Note (Full width across 2 columns) -->
      <div class="md:col-span-2">
        <Field :data-invalid="!!errors.note || undefined">
          <FieldLabel>
            <span>{{ t('inventory.requestNotes') }}</span>
          </FieldLabel>
          <FieldContent>
            <textarea
              v-model="note"
              rows="3"
              :placeholder="t('inventory.requestNotesPlaceholder')"
              :disabled="isOutOfStock || isSubmitting"
              :class="[
                'w-full px-4 py-3 text-sm border rounded-[14px] bg-background focus:outline-none focus:ring-2 transition-colors resize-none text-foreground placeholder:text-muted-foreground',
                errors.note
                  ? 'border-destructive focus:ring-destructive/20 focus:border-destructive'
                  : 'border-input focus:ring-primary/20 focus:border-primary'
              ]"
            ></textarea>
          </FieldContent>
          <FieldError v-if="errors.note">{{ errors.note }}</FieldError>
        </Field>
      </div>
    </div>

    <!-- Action buttons inside manual request tab (above the modal footer) -->
    <div class="flex items-center justify-end gap-3 pt-4">
      <Button
        type="button"
        variant="primary"
        size="lg"
        :disabled="isOutOfStock || isSubmitting"
        @click="handleSubmit"
        class="inline-flex items-center gap-2"
      >
        <Loader2 v-if="isSubmitting" class="w-4 h-4 animate-spin" />
        {{ isSubmitting ? t('inventory.processing') : t('inventory.submitRequest') }}
      </Button>
    </div>
  </div>
</template>
