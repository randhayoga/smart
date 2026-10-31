<script setup lang="ts">
/**
 * Unified Input Manual Borrowing / Request Modal (95vw width).
 * Integrates Section 1 (Request/Borrowing Info), Section 2 (Item/Asset Selection),
 * and Section 3 (Added Items List) into a single high-performance scrollable modal.
 */
import { ref, reactive, computed, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { router } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
import { X, Loader2 } from 'lucide-vue-next';
import { useModalLock } from '@/composables/useModalLock';
import { Button } from '@/Components/ui/button';
import RequestInfoSection, {
  type OptionItem,
  type ManualRequestFormState,
} from './RequestInfoSection.vue';
import ItemSelectionSection, {
  type CatalogModelItem,
  type CatalogAssetItem,
  type AddedManualItem,
} from './ItemSelectionSection.vue';
import AddedItemsListSection from './AddedItemsListSection.vue';

const props = defineProps<{
  open: boolean;
}>();

const emit = defineEmits<{
  (e: 'update:open', value: boolean): void;
  (e: 'success'): void;
}>();

const { t } = useI18n();

useModalLock(computed(() => props.open));

const getTodayIsoDate = (): string => {
  const now = new Date();
  const year = now.getFullYear();
  const month = String(now.getMonth() + 1).padStart(2, '0');
  const day = String(now.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
};

const form = reactive<ManualRequestFormState>({
  request_type: 'consumable',
  user_id: null,
  utilization: 'corporate',
  org_id: null,
  project_id: null,
  approver_id: null,
  form_file: null,
  form_file_name: '',
  reasoning: '',
  date: getTodayIsoDate(),
  start_date: getTodayIsoDate(),
  end_date: '',
});

const errors = ref<Record<string, string>>({});
const isSubmitting = ref(false);

const employees = ref<OptionItem[]>([]);
const departments = ref<OptionItem[]>([]);
const projects = ref<OptionItem[]>([]);
const approverCandidates = ref<OptionItem[]>([]);
const cachedCorporateApprover = ref<{
  auto_approver_id: number | null;
  approver_candidates: OptionItem[];
}>({
  auto_approver_id: null,
  approver_candidates: [],
});

const loadingUserOptions = ref(false);
const loadingApproverOptions = ref(false);
const loadingCatalog = ref(false);

const catalogModels = ref<CatalogModelItem[]>([]);
const catalogAssetsSt = ref<CatalogAssetItem[]>([]);
const catalogAssetsLt = ref<CatalogAssetItem[]>([]);
const catalogAssetsRent = ref<CatalogAssetItem[]>([]);
const catalogLoaded = reactive<{ consumable: boolean; st: boolean; lt: boolean; rent: boolean }>({
  consumable: false,
  st: false,
  lt: false,
  rent: false,
});

const addedItems = ref<AddedManualItem[]>([]);

const activeCatalogAssets = computed(() => {
  if (form.request_type === 'rent') return catalogAssetsRent.value;
  return form.request_type === 'st' ? catalogAssetsSt.value : catalogAssetsLt.value;
});

const resetState = () => {
  const today = getTodayIsoDate();
  form.request_type = 'consumable';
  form.user_id = null;
  form.utilization = 'corporate';
  form.org_id = null;
  form.project_id = null;
  form.approver_id = null;
  form.form_file = null;
  form.form_file_name = '';
  form.reasoning = '';
  form.date = today;
  form.start_date = today;
  form.end_date = '';
  errors.value = {};
  departments.value = [];
  projects.value = [];
  approverCandidates.value = [];
  cachedCorporateApprover.value = { auto_approver_id: null, approver_candidates: [] };
  addedItems.value = [];
};

const closeModal = () => {
  if (isSubmitting.value) return;
  emit('update:open', false);
};

const fetchEmployeesIfNeeded = async () => {
  if (employees.value.length > 0) return;
  try {
    const res = await fetch('/smart/requests/manual-options', {
      headers: { Accept: 'application/json' },
    });
    if (res.ok) {
      const data = await res.json();
      employees.value = data.employees || [];
    }
  } catch (err) {
    console.error('Failed to fetch manual request employees:', err);
  }
};

const fetchCatalogForType = async (type: 'consumable' | 'st' | 'lt' | 'rent', force = false) => {
  if (!force && catalogLoaded[type]) return;
  loadingCatalog.value = true;
  try {
    const res = await fetch(`/smart/requests/manual-catalog?type=${type}`, {
      headers: { Accept: 'application/json' },
    });
    if (res.ok) {
      const data = await res.json();
      if (type === 'consumable') {
        catalogModels.value = data.models || data.items || [];
      } else if (type === 'st') {
        catalogAssetsSt.value = data.assets || data.items || [];
      } else if (type === 'rent') {
        catalogAssetsRent.value = data.assets || data.items || [];
      } else {
        catalogAssetsLt.value = data.assets || data.items || [];
      }
      catalogLoaded[type] = true;
    }
  } catch (err) {
    console.error('Failed to fetch manual request catalog:', err);
  } finally {
    loadingCatalog.value = false;
  }
};

watch(
  () => props.open,
  (isOpen) => {
    if (isOpen) {
      resetState();
      catalogLoaded.consumable = false;
      catalogLoaded.st = false;
      catalogLoaded.lt = false;
      catalogLoaded.rent = false;
      fetchEmployeesIfNeeded();
      fetchCatalogForType('consumable', true);
    }
  },
  { immediate: true }
);

const fetchDepartmentApprovers = async (orgId: number) => {
  loadingApproverOptions.value = true;
  try {
    const res = await fetch(`/smart/requests/manual-options?org_id=${orgId}`, {
      headers: { Accept: 'application/json' },
    });
    if (res.ok) {
      const data = await res.json();
      approverCandidates.value = data.approver_candidates || [];
      form.approver_id = data.auto_approver_id ?? null;
    }
  } catch (err) {
    console.error('Failed to fetch department approvers:', err);
  } finally {
    loadingApproverOptions.value = false;
  }
};

const fetchProjectApprovers = async (projectId: number) => {
  loadingApproverOptions.value = true;
  try {
    const res = await fetch(`/smart/requests/manual-options?project_id=${projectId}`, {
      headers: { Accept: 'application/json' },
    });
    if (res.ok) {
      const data = await res.json();
      approverCandidates.value = data.approver_candidates || [];
      form.approver_id = data.auto_approver_id ?? null;
    }
  } catch (err) {
    console.error('Failed to fetch project approvers:', err);
  } finally {
    loadingApproverOptions.value = false;
  }
};

const handleRequestTypeChange = (newType: 'consumable' | 'st' | 'lt' | 'rent') => {
  if (form.request_type === newType) return;
  form.request_type = newType;
  addedItems.value = [];
  delete errors.value.items;
  delete errors.value.date;
  delete errors.value.start_date;
  delete errors.value.end_date;
  fetchCatalogForType(newType);
};

const handleUserChange = async (userId: number | null) => {
  form.user_id = userId;
  form.org_id = null;
  form.project_id = null;
  form.approver_id = null;
  departments.value = [];
  projects.value = [];
  approverCandidates.value = [];
  cachedCorporateApprover.value = { auto_approver_id: null, approver_candidates: [] };
  delete errors.value.user_id;

  if (!userId) return;

  loadingUserOptions.value = true;
  try {
    const res = await fetch(`/smart/requests/manual-options?user_id=${userId}`, {
      headers: { Accept: 'application/json' },
    });
    if (res.ok) {
      const data = await res.json();
      departments.value = data.departments || [];
      projects.value = data.projects || [];
      cachedCorporateApprover.value = {
        auto_approver_id: data.auto_approver_id ?? null,
        approver_candidates: data.approver_candidates || [],
      };

      if (form.utilization === 'corporate') {
        if (departments.value.length === 1) {
          form.org_id = departments.value[0].id;
          delete errors.value.org_id;
          approverCandidates.value = cachedCorporateApprover.value.approver_candidates;
          form.approver_id = cachedCorporateApprover.value.auto_approver_id;
        }
      } else {
        if (projects.value.length === 1) {
          form.project_id = projects.value[0].id;
          delete errors.value.project_id;
          await fetchProjectApprovers(projects.value[0].id);
        }
      }
    }
  } catch (err) {
    console.error('Failed to fetch user options:', err);
  } finally {
    loadingUserOptions.value = false;
  }
};

const handleUtilizationChange = (newUtil: 'corporate' | 'project') => {
  if (form.utilization === newUtil) return;
  form.utilization = newUtil;
  form.org_id = null;
  form.project_id = null;
  form.approver_id = null;
  approverCandidates.value = [];
  delete errors.value.org_id;
  delete errors.value.project_id;
  delete errors.value.approver_id;

  if (newUtil === 'corporate') {
    if (departments.value.length === 1) {
      form.org_id = departments.value[0].id;
      approverCandidates.value = cachedCorporateApprover.value.approver_candidates;
      form.approver_id = cachedCorporateApprover.value.auto_approver_id;
    }
  } else {
    if (projects.value.length === 1) {
      form.project_id = projects.value[0].id;
      fetchProjectApprovers(projects.value[0].id);
    }
  }
};

const handleDepartmentChange = (orgId: number | null) => {
  form.org_id = orgId;
  form.approver_id = null;
  approverCandidates.value = [];
  delete errors.value.org_id;
  delete errors.value.approver_id;

  if (!orgId) return;

  if (
    departments.value.length === 1 &&
    departments.value[0].id === orgId &&
    cachedCorporateApprover.value.approver_candidates.length > 0
  ) {
    approverCandidates.value = cachedCorporateApprover.value.approver_candidates;
    form.approver_id = cachedCorporateApprover.value.auto_approver_id;
    return;
  }

  fetchDepartmentApprovers(orgId);
};

const handleProjectChange = (projectId: number | null) => {
  form.project_id = projectId;
  form.approver_id = null;
  approverCandidates.value = [];
  delete errors.value.project_id;
  delete errors.value.approver_id;

  if (!projectId) return;
  fetchProjectApprovers(projectId);
};

const handleApproverChange = (approverId: number | null) => {
  form.approver_id = approverId;
  delete errors.value.approver_id;
};

const handleFileChange = (file: File | null) => {
  form.form_file = file;
  form.form_file_name = file ? file.name : '';
  delete errors.value.form_file;
};

watch(() => form.date, (val) => {
  if (val && errors.value.date) {
    delete errors.value.date;
  }
});

watch(() => form.start_date, (val) => {
  if (val && errors.value.start_date) {
    delete errors.value.start_date;
  }
  if (errors.value.end_date && form.end_date && form.end_date >= val) {
    delete errors.value.end_date;
  }
});

watch(() => form.end_date, (val) => {
  if (val && errors.value.end_date) {
    if (!form.start_date || val >= form.start_date) {
      delete errors.value.end_date;
    }
  }
});

watch(() => form.reasoning, () => {
  if (errors.value.reasoning) {
    delete errors.value.reasoning;
  }
});

const handleAddItem = (item: AddedManualItem) => {
  addedItems.value.push(item);
  delete errors.value.items;
};

const handleUpdateQuantity = (payload: { key: string; quantity: number }) => {
  const target = addedItems.value.find(i => i.key === payload.key);
  if (target) {
    target.quantity = payload.quantity;
  }
};

const handleRemoveItem = (key: string) => {
  addedItems.value = addedItems.value.filter(i => i.key !== key);
};

const validateForm = (): boolean => {
  const nextErrors: Record<string, string> = {};

  if (!form.user_id) {
    nextErrors.user_id = t('fulfillment.manualRequest.requesterRequired');
  }
  if (form.utilization === 'corporate' && !form.org_id) {
    nextErrors.org_id = t('fulfillment.manualRequest.departmentRequired');
  }
  if (form.utilization === 'project' && !form.project_id) {
    nextErrors.project_id = t('fulfillment.manualRequest.projectRequired');
  }
  if (!form.approver_id) {
    nextErrors.approver_id =
      form.utilization === 'project'
        ? t('fulfillment.manualRequest.pmRequired')
        : t('fulfillment.manualRequest.dmRequired');
  }
  if (!form.form_file) {
    nextErrors.form_file = t('fulfillment.manualRequest.formFileRequired');
  }

  if (form.request_type === 'consumable') {
    if (!form.date) {
      nextErrors.date = t('fulfillment.manualRequest.dateRequired');
    }
  } else if (form.request_type === 'st' || form.request_type === 'rent') {
    if (!form.start_date) {
      nextErrors.start_date = t('fulfillment.manualRequest.startDateRequired');
    }
    if (!form.end_date) {
      nextErrors.end_date = t('fulfillment.manualRequest.endDateRequired');
    } else if (form.start_date && form.end_date < form.start_date) {
      nextErrors.end_date = t('fulfillment.manualRequest.endDateAfterStart');
    }
  } else if (form.request_type === 'lt') {
    if (!form.start_date) {
      nextErrors.start_date = t('fulfillment.manualRequest.startDateRequired');
    }
  }

  if (addedItems.value.length === 0) {
    nextErrors.items = t('fulfillment.manualRequest.itemsRequired');
  }

  errors.value = nextErrors;
  return Object.keys(nextErrors).length === 0;
};

const handleSubmit = () => {
  if (isSubmitting.value) return;
  if (!validateForm()) return;

  const payload: Record<string, any> = {
    request_type: form.request_type,
    user_id: form.user_id,
    approver_id: form.approver_id,
    utilization: form.utilization,
    org_id: form.utilization === 'corporate' ? form.org_id : null,
    project_id: form.utilization === 'project' ? form.project_id : null,
    reasoning: form.reasoning.trim() || null,
    form_file: form.form_file,
    items: addedItems.value.map(item =>
      form.request_type === 'consumable'
        ? {
            model_id: item.model_id,
            quantity: item.quantity,
            reasoning: item.reasoning || null,
          }
        : {
            asset_id: item.asset_id,
            reasoning: item.reasoning || null,
          }
    ),
  };

  if (form.request_type === 'consumable') {
    payload.date = form.date;
  } else if (form.request_type === 'st' || form.request_type === 'rent') {
    payload.start_date = form.start_date;
    payload.end_date = form.end_date;
  } else {
    payload.start_date = form.start_date;
  }

  isSubmitting.value = true;
  router.post('/smart/requests/manual', payload, {
    forceFormData: true,
    preserveScroll: true,
    onFinish: () => {
      isSubmitting.value = false;
    },
    onSuccess: () => {
      toast.success(t('fulfillment.manualRequest.submitSuccess'));
      emit('update:open', false);
      emit('success');
    },
    onError: (backendErrors: Record<string, string>) => {
      errors.value = { ...errors.value, ...backendErrors };
      const firstKey = Object.keys(backendErrors)[0];
      toast.error(backendErrors[firstKey] || t('fulfillment.manualRequest.submitFailed'));
    },
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
            data-testid="input-manual-request-modal"
            class="bg-card w-[95vw] max-w-[95vw] rounded-[14px] shadow-2xl overflow-hidden flex flex-col max-h-[90vh]"
            @click.stop
          >
            <!-- Modal Header -->
            <div class="flex items-center justify-between pt-3 pb-2 px-4 border-b border-border shrink-0">
              <h3 class="text-lg font-bold text-foreground">
                {{ t('fulfillment.manualRequest.modalTitle') }}
              </h3>
              <button
                type="button"
                :disabled="isSubmitting"
                class="p-2 hover:bg-muted rounded-full transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                @click="closeModal"
              >
                <X class="w-5 h-5 text-muted-foreground cursor-pointer" />
              </button>
            </div>

            <!-- Modal Body (Scrollable) -->
            <div class="p-6 overflow-y-auto flex-1 space-y-6 overscroll-contain">
              <!-- Section 1: Request / Borrowing Info -->
              <RequestInfoSection
                :form="form"
                :errors="errors"
                :employees="employees"
                :departments="departments"
                :projects="projects"
                :approver-candidates="approverCandidates"
                :loading-user-options="loadingUserOptions"
                :loading-approver-options="loadingApproverOptions"
                @change-request-type="handleRequestTypeChange"
                @change-user="handleUserChange"
                @change-utilization="handleUtilizationChange"
                @change-department="handleDepartmentChange"
                @change-project="handleProjectChange"
                @change-approver="handleApproverChange"
                @change-file="handleFileChange"
              />

              <!-- Section 2: Item / Asset Selection -->
              <ItemSelectionSection
                :request-type="form.request_type"
                :catalog-models="catalogModels"
                :catalog-assets="activeCatalogAssets"
                :added-items="addedItems"
                :loading-catalog="loadingCatalog"
                :items-error="errors.items"
                @add-item="handleAddItem"
              />

              <!-- Section 3: Added Items List -->
              <AddedItemsListSection
                :items="addedItems"
                :disabled="isSubmitting"
                @update-quantity="handleUpdateQuantity"
                @remove-item="handleRemoveItem"
              />
            </div>

            <!-- Modal Footer -->
            <div class="py-3 px-4 border-t border-border flex items-center justify-between shrink-0">
              <p class="text-sm text-rose-500 italic font-medium">
                {{ t('fulfillment.manualRequest.requiredMarker') }}
              </p>
              <div class="flex items-center gap-3">
                <Button
                  type="button"
                  variant="white"
                  size="xl"
                  :disabled="isSubmitting"
                  @click="closeModal"
                >
                  {{ t('common.cancel') }}
                </Button>
                <Button
                  type="button"
                  data-testid="submit-manual-request-btn"
                  variant="primary"
                  size="xl"
                  class="relative"
                  :disabled="isSubmitting"
                  @click="handleSubmit"
                >
                  <Loader2 v-if="isSubmitting" class="absolute inset-0 m-auto h-5 w-5 animate-spin" />
                  <span :class="{ 'opacity-0': isSubmitting }">
                    {{ t('fulfillment.manualRequest.submitButton') }}
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
