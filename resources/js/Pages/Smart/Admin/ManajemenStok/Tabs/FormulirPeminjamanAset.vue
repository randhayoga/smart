<script setup lang="ts">
/**
 * Formulir Peminjaman Aset tab component allowing administrators to assign, update, and complete asset loans.
 */
import { ref, computed, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { router } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
import { CheckCircle } from 'lucide-vue-next';
import { Button } from '@/Components/ui/button';
import StatusBadge from '@/Components/StatusBadge.vue';
import Combobox from '@/Components/Combobox.vue';
import { Field, FieldLabel, FieldContent, FieldError } from '@/Components/ui/field';

interface Props {
  asset: any;
  users?: { id: number; name: string; employee_id?: string; department?: { id: number; name: string } | null }[];
}

const props = defineProps<Props>();

const { t } = useI18n();

const borrowUserId = ref<number | string | null>(null);
const borrowStartDate = ref('');
const borrowNote = ref('');
const utilization = ref<'corporate' | 'project' | null>(null);
const orgId = ref<number | string | null>(null);
const projectId = ref<number | string | null>(null);
const isBorrowSubmitting = ref(false);
const isFinishSubmitting = ref(false);
const isHydrating = ref(false);

const rawUsersList = ref<{
  id: number;
  name: string;
  employee_id?: string;
  department?: { id: number; name: string } | null;
}[]>([]);
const userDepartments = ref<{ id: number; name: string }[]>([]);
const userProjects = ref<{ id: number; name: string; no_project?: string }[]>([]);
const isLoadingUserOptions = ref(false);

const errors = ref<{
  user_id?: string;
  start_date?: string;
  utilization?: string;
  org_id?: string;
  project_id?: string;
  note?: string;
}>({});

const isBorrowerSelected = computed(() => !!borrowUserId.value);

// Format options to "<employee_id> - <employee_name>"
const usersOptions = computed(() => {
  return rawUsersList.value.map(user => {
    let empId = user.employee_id || '';
    let name = user.name || '';

    if (!empId) {
      const match = name.match(/^(.*?)\s*\((.*?)\)$/);
      if (match) {
        name = match[1].trim();
        empId = match[2].trim();
      }
    } else {
      name = name.replace(/\s*\([^)]*\)\s*$/, '').trim();
    }

    const formattedName = empId && name ? `${empId} - ${name}` : (name || empId || '');

    return {
      id: user.id,
      name: formattedName,
    };
  });
});

const fetchUsers = async () => {
  if (props.users && props.users.length > 0) {
    rawUsersList.value = props.users;
    return;
  }
  try {
    const res = await fetch('/smart/inventory/request-options');
    if (res.ok) {
      const data = await res.json();
      rawUsersList.value = data.users || [];
    }
  } catch (err) {
    console.error('Failed to fetch users:', err);
  }
};

const fetchUserOptions = async (
  targetUserId: number | string,
  preselectOrgId?: number | string | null,
  preselectProjectId?: number | string | null
) => {
  isLoadingUserOptions.value = true;
  try {
    const res = await fetch(`/smart/inventory/request-options?user_id=${targetUserId}`);
    if (res.ok) {
      const data = await res.json();
      userDepartments.value = data.departments || [];
      userProjects.value = data.projects || [];

      // Auto-default department to the borrower's department
      if (preselectOrgId !== undefined && preselectOrgId !== null) {
        orgId.value = preselectOrgId;
      } else if (userDepartments.value.length > 0) {
        orgId.value = userDepartments.value[0].id;
      } else {
        orgId.value = null;
      }

      // Auto-default project if borrower is assigned to exactly 1 project, or preselect
      if (preselectProjectId !== undefined && preselectProjectId !== null) {
        projectId.value = preselectProjectId;
      } else if (userProjects.value.length === 1) {
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

// When Borrower changes or is cleared
watch(borrowUserId, (newVal) => {
  if (isHydrating.value) return;
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
      if (utilization.value === 'corporate') {
        orgId.value = selectedUser.department.id;
      }
    }
    fetchUserOptions(newVal);
  }
});

// When Utilization changes, clear opposing target selection
watch(utilization, (newVal) => {
  if (isHydrating.value) return;
  if (newVal === 'corporate') {
    projectId.value = null;
    if (userDepartments.value.length > 0 && !orgId.value) {
      orgId.value = userDepartments.value[0].id;
    }
  } else if (newVal === 'project') {
    orgId.value = null;
    if (userProjects.value.length === 1 && !projectId.value) {
      projectId.value = userProjects.value[0].id;
    }
  }
});

watch(() => props.asset, async (newAsset) => {
  isHydrating.value = true;
  await fetchUsers();
  errors.value = {};
  if (newAsset?.status === 'Dipinjam' && newAsset?.active_borrowing) {
    const ab = newAsset.active_borrowing;
    borrowUserId.value = ab.user_id;
    borrowStartDate.value = ab.start_date || '';
    borrowNote.value = ab.note || '';
    utilization.value = ab.utilization || 'corporate';
    orgId.value = ab.org_id || null;
    projectId.value = ab.project_id || null;
    if (ab.user_id) {
      await fetchUserOptions(ab.user_id, ab.org_id, ab.project_id);
    }
  } else {
    borrowUserId.value = null;
    borrowStartDate.value = new Date().toISOString().split('T')[0];
    borrowNote.value = '';
    utilization.value = null;
    orgId.value = null;
    projectId.value = null;
    userDepartments.value = [];
    userProjects.value = [];
  }
  isHydrating.value = false;
}, { immediate: true, deep: true });

const handleSaveBorrow = () => {
  if (!props.asset) return;
  errors.value = {};

  if (!borrowUserId.value) {
    errors.value.user_id = t('inventory.borrowerRequired');
  }
  if (!borrowStartDate.value) {
    errors.value.start_date = t('inventory.borrowStartDateRequired');
  }
  if (!utilization.value) {
    errors.value.utilization = t('inventory.utilizationRequired');
  } else if (utilization.value === 'corporate' && !orgId.value) {
    errors.value.org_id = t('inventory.departmentRequired');
  } else if (utilization.value === 'project' && !projectId.value) {
    errors.value.project_id = t('inventory.projectRequired');
  }

  if (Object.keys(errors.value).length > 0) {
    toast.error(t('inventory.borrowCompleteRequired'));
    return;
  }

  isBorrowSubmitting.value = true;
  router.post(
    `/smart/inventory/units/${props.asset.id}/borrow`,
    {
      user_id: borrowUserId.value,
      start_date: borrowStartDate.value,
      utilization: utilization.value,
      org_id: utilization.value === 'corporate' ? orgId.value : null,
      project_id: utilization.value === 'project' ? projectId.value : null,
      note: borrowNote.value,
    },
    {
      preserveScroll: true,
      onSuccess: () => {
        isBorrowSubmitting.value = false;
        errors.value = {};
      },
      onError: (serverErrors: any) => {
        isBorrowSubmitting.value = false;
        errors.value = serverErrors || {};
        if (serverErrors.user_id) toast.error(serverErrors.user_id);
        else if (serverErrors.start_date) toast.error(serverErrors.start_date);
        else if (serverErrors.utilization) toast.error(serverErrors.utilization);
        else if (serverErrors.org_id) toast.error(serverErrors.org_id);
        else if (serverErrors.project_id) toast.error(serverErrors.project_id);
        else if (serverErrors.note) toast.error(serverErrors.note);
        else toast.error(t('inventory.borrowSaveFailed'));
      },
      onFinish: () => {
        isBorrowSubmitting.value = false;
      }
    }
  );
};

const handleFinishBorrow = () => {
  if (!props.asset) return;
  isFinishSubmitting.value = true;
  router.post(
    `/smart/inventory/units/${props.asset.id}/finish-borrow`,
    {},
    {
      preserveScroll: true,
      onSuccess: () => {
        isFinishSubmitting.value = false;
        errors.value = {};
        borrowUserId.value = null;
        borrowStartDate.value = new Date().toISOString().split('T')[0];
        borrowNote.value = '';
        utilization.value = null;
        orgId.value = null;
        projectId.value = null;
      },
      onError: () => {
        isFinishSubmitting.value = false;
        toast.error(t('inventory.borrowFinishFailed'));
      },
      onFinish: () => {
        isFinishSubmitting.value = false;
      }
    }
  );
};
</script>

<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between pb-3">
      <div>
        <h4 class="text-lg font-bold text-foreground">
          {{ asset?.status === 'Dipinjam' ? t('inventory.activeBorrowInfo') : t('inventory.assetBorrowForm') }}
        </h4>
        <p class="text-sm text-muted-foreground mt-0.5">
          {{ asset?.status === 'Dipinjam' 
            ? t('inventory.activeBorrowSubtitle') 
            : t('inventory.newBorrowSubtitle') 
          }}
        </p>
      </div>
      <StatusBadge :status="asset?.status" class="rounded-sm" />
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
      <!-- Row 1, Col 1: Borrower -->
      <Field :data-invalid="!!errors.user_id || undefined">
        <FieldLabel>
          <span>{{ t('inventory.borrower') }}<span class="text-rose-500">*</span></span>
        </FieldLabel>
        <FieldContent>
          <Combobox
            v-model="borrowUserId"
            :options="usersOptions"
            :search-placeholder="t('inventory.searchBorrowerPlaceholder')"
            :default-label="t('inventory.selectBorrower')"
            width-class="w-full h-10 px-4"
            :error="!!errors.user_id"
            :disabled="isBorrowSubmitting"
          />
        </FieldContent>
        <FieldError v-if="errors.user_id">{{ errors.user_id }}</FieldError>
      </Field>

      <!-- Row 1, Col 2: Start Date -->
      <Field :data-invalid="!!errors.start_date || undefined">
        <FieldLabel>
          <span>{{ t('inventory.borrowStartDate') }}<span class="text-rose-500">*</span></span>
        </FieldLabel>
        <FieldContent>
          <input
            type="date"
            v-model="borrowStartDate"
            :disabled="isBorrowSubmitting"
            :class="[
              'w-full px-4 py-2 text-sm border rounded-[14px] bg-background focus:outline-none focus:ring-2 transition-colors h-10 text-foreground',
              errors.start_date 
                ? 'border-destructive focus:ring-destructive/20 focus:border-destructive' 
                : 'border-input focus:ring-primary/20 focus:border-primary'
            ]"
          />
        </FieldContent>
        <FieldError v-if="errors.start_date">{{ errors.start_date }}</FieldError>
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
                !isBorrowerSelected || isBorrowSubmitting
                  ? 'border-input bg-muted/40 text-muted-foreground/50 cursor-not-allowed'
                  : utilization === 'corporate'
                    ? 'border-primary bg-primary/10 text-primary font-semibold cursor-pointer'
                    : 'border-input hover:bg-muted/50 text-muted-foreground cursor-pointer'
              ]"
              :disabled="!isBorrowerSelected || isBorrowSubmitting"
            >
              <span>{{ t('inventory.corporate') }}</span>
            </button>
            <button
              type="button"
              @click="utilization = 'project'"
              :class="[
                'h-10 px-4 rounded-[14px] border text-sm font-medium transition-colors flex items-center justify-center gap-2',
                !isBorrowerSelected || isBorrowSubmitting
                  ? 'border-input bg-muted/40 text-muted-foreground/50 cursor-not-allowed'
                  : utilization === 'project'
                    ? 'border-primary bg-primary/10 text-primary font-semibold cursor-pointer'
                    : 'border-input hover:bg-muted/50 text-muted-foreground cursor-pointer'
              ]"
              :disabled="!isBorrowerSelected || isBorrowSubmitting"
            >
              <span>{{ t('inventory.project') }}</span>
            </button>
          </div>
        </FieldContent>
        <FieldError v-if="errors.utilization">{{ errors.utilization }}</FieldError>
      </Field>

      <!-- Row 2, Col 2: Dept / Project Selection -->
      <div>
        <!-- Inactive when no borrower or no utilization selected -->
        <Field v-if="!isBorrowerSelected || !utilization">
          <FieldLabel>
            <span>{{ t('inventory.department') }} / {{ t('inventory.project') }}<span class="text-rose-500">*</span></span>
          </FieldLabel>
          <FieldContent>
            <div class="h-10 px-4 rounded-[14px] border border-input bg-muted/30 flex items-center text-sm text-muted-foreground cursor-not-allowed">
              <span>{{ t('inventory.selectBorrower') }}</span>
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
              :disabled="!isBorrowerSelected || isBorrowSubmitting || isLoadingUserOptions"
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
              :disabled="!isBorrowerSelected || isBorrowSubmitting || isLoadingUserOptions || userProjects.length === 0"
            />
          </FieldContent>
          <FieldError v-if="errors.project_id">{{ errors.project_id }}</FieldError>
        </Field>
      </div>

      <!-- Row 3: Note -->
      <div class="md:col-span-2">
        <Field :data-invalid="!!errors.note || undefined">
          <FieldLabel>
            <span>{{ t('inventory.borrowNotes') }}</span>
          </FieldLabel>
          <FieldContent>
            <textarea
              v-model="borrowNote"
              rows="4"
              :placeholder="t('inventory.borrowNotesPlaceholder')"
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

    <!-- Action buttons inside manual borrowing tab (above the modal footer) -->
    <div class="flex items-center justify-end gap-3 pt-4">
      <Button
        v-if="asset?.status === 'Dipinjam'"
        type="button"
        variant="success"
        size="lg"
        :disabled="isFinishSubmitting || isBorrowSubmitting"
        @click="handleFinishBorrow"
        class="inline-flex items-center gap-2"
      >
        <CheckCircle class="w-4 h-4" />
        {{ isFinishSubmitting ? t('inventory.processing') : t('inventory.finishBorrow') }}
      </Button>

      <Button
        type="button"
        variant="primary"
        size="lg"
        :disabled="isBorrowSubmitting || isFinishSubmitting"
        @click="handleSaveBorrow"
        class="inline-flex items-center gap-2"
      >
        {{ isBorrowSubmitting ? t('inventory.processing') : (asset?.status === 'Dipinjam' ? t('inventory.saveChanges') : t('inventory.saveBorrow')) }}
      </Button>
    </div>
  </div>
</template>
