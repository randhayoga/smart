<script setup lang="ts">
/**
 * Section 1 of InputManualRequestModal: Request/Borrowing Information.
 * Handles Request Type, Requestor/Borrower, Utilization, Department/Project,
 * DM/PM auto-selection & override, Required Form Upload, Notes/Purpose, and Dynamic Dates.
 */
import { ref, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { ChevronDown, Trash2 } from 'lucide-vue-next';
import { Button } from '@/Components/ui/button';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';
import { Field, FieldLabel, FieldContent, FieldError } from '@/Components/ui/field';
import Combobox from '@/Components/Combobox.vue';

export interface OptionItem {
  id: number;
  name: string;
  employee_id?: string;
  orgchart_id?: number | null;
}

export interface ManualRequestFormState {
  request_type: 'consumable' | 'st' | 'lt' | 'rent';
  user_id: number | null;
  utilization: 'corporate' | 'project';
  org_id: number | null;
  project_id: number | null;
  approver_id: number | null;
  form_file: File | null;
  form_file_name: string;
  reasoning: string;
  date: string;
  start_date: string;
  end_date: string;
}

const props = defineProps<{
  form: ManualRequestFormState;
  errors: Record<string, string>;
  employees: OptionItem[];
  departments: OptionItem[];
  projects: OptionItem[];
  approverCandidates: OptionItem[];
  loadingUserOptions?: boolean;
  loadingApproverOptions?: boolean;
}>();

const emit = defineEmits<{
  (e: 'change-request-type', val: 'consumable' | 'st' | 'lt' | 'rent'): void;
  (e: 'change-user', val: number | null): void;
  (e: 'change-utilization', val: 'corporate' | 'project'): void;
  (e: 'change-department', val: number | null): void;
  (e: 'change-project', val: number | null): void;
  (e: 'change-approver', val: number | null): void;
  (e: 'change-file', file: File | null): void;
}>();

const { t } = useI18n();
const formFileInputRef = ref<HTMLInputElement | null>(null);

const requestTypeLabel = computed(() => {
  if (props.form.request_type === 'rent') return t('fulfillment.manualRequest.typeRent');
  if (props.form.request_type === 'st') return t('fulfillment.manualRequest.typeSt');
  if (props.form.request_type === 'lt') return t('fulfillment.manualRequest.typeLt');
  return t('fulfillment.manualRequest.typeConsumable');
});

const utilizationLabel = computed(() => {
  return props.form.utilization === 'project'
    ? t('fulfillment.manualRequest.utilizationProject')
    : t('fulfillment.manualRequest.utilizationCorporate');
});

const triggerFormFileInput = () => {
  formFileInputRef.value?.click();
};

const handleFormFileChange = (e: Event) => {
  const target = e.target as HTMLInputElement;
  if (!target.files || target.files.length === 0) return;
  const file = target.files[0];

  const validTypes = ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
  if (!validTypes.includes(file.type)) {
    alert(t('fulfillment.manualRequest.invalidFileFormat'));
    target.value = '';
    return;
  }

  if (file.size > 2 * 1024 * 1024) {
    alert(t('fulfillment.manualRequest.fileTooLarge2Mb'));
    target.value = '';
    return;
  }

  emit('change-file', file);
};

const handleDeleteFormFile = () => {
  emit('change-file', null);
  if (formFileInputRef.value) {
    formFileInputRef.value.value = '';
  }
};

const viewFormFileInNewTab = () => {
  if (props.form.form_file) {
    window.open(URL.createObjectURL(props.form.form_file), '_blank');
  }
};
</script>

<template>
  <div>
    <div class="p-4 rounded-[14px] bg-muted/20 border border-border/80">
      <div class="grid grid-cols-1 md:grid-cols-2 gap-x-12 gap-y-5">
        <!-- 1. Request Type -->
        <Field :data-invalid="!!errors.request_type || undefined">
          <FieldLabel>
            <span>{{ t('fulfillment.manualRequest.type') }}<span class="text-rose-500">*</span></span>
          </FieldLabel>
          <FieldContent>
            <DropdownMenu>
              <DropdownMenuTrigger asChild>
                <Button
                  variant="outline"
                  data-testid="request-type-trigger"
                  class="w-full justify-between rounded-[14px] font-normal h-10 px-4 bg-background"
                  :class="[errors.request_type ? 'border-destructive' : '']"
                >
                  {{ requestTypeLabel }}
                  <ChevronDown class="w-4 h-4 opacity-50" />
                </Button>
              </DropdownMenuTrigger>
              <DropdownMenuContent align="start" class="w-(--reka-dropdown-menu-trigger-width) min-w-(--reka-dropdown-menu-trigger-width) rounded-[14px] z-[1001]">
                <DropdownMenuItem @select="emit('change-request-type', 'consumable')">
                  {{ t('fulfillment.manualRequest.typeConsumable') }}
                </DropdownMenuItem>
                <DropdownMenuItem @select="emit('change-request-type', 'st')">
                  {{ t('fulfillment.manualRequest.typeSt') }}
                </DropdownMenuItem>
                <DropdownMenuItem @select="emit('change-request-type', 'rent')">
                  {{ t('fulfillment.manualRequest.typeRent') }}
                </DropdownMenuItem>
                <DropdownMenuItem @select="emit('change-request-type', 'lt')">
                  {{ t('fulfillment.manualRequest.typeLt') }}
                </DropdownMenuItem>
              </DropdownMenuContent>
            </DropdownMenu>
          </FieldContent>
          <FieldError v-if="errors.request_type">{{ errors.request_type }}</FieldError>
        </Field>

        <!-- 2. Requestor / Borrower Name -->
        <Field :data-invalid="!!errors.user_id || undefined">
          <FieldLabel>
            <span>{{ t('fulfillment.manualRequest.requesterName') }}<span class="text-rose-500">*</span></span>
          </FieldLabel>
          <FieldContent>
            <Combobox
              :model-value="form.user_id"
              :options="employees"
              :placeholder="t('fulfillment.manualRequest.selectRequester')"
              :search-placeholder="t('fulfillment.manualRequest.searchEmployee')"
              :default-label="t('fulfillment.manualRequest.selectRequester')"
              :error="!!errors.user_id"
              width-class="w-full"
              bg-class="bg-background h-10"
              :limit="50"
              @update:model-value="(val) => emit('change-user', val ? Number(val) : null)"
            />
          </FieldContent>
          <FieldError v-if="errors.user_id">{{ errors.user_id }}</FieldError>
        </Field>

        <!-- 3. Utilization (Corporate / Project) -->
        <Field :data-invalid="!!errors.utilization || undefined">
          <FieldLabel>
            <span>{{ t('fulfillment.manualRequest.utilization') }}<span class="text-rose-500">*</span></span>
          </FieldLabel>
          <FieldContent>
            <DropdownMenu>
              <DropdownMenuTrigger asChild>
                <Button
                  variant="outline"
                  data-testid="utilization-trigger"
                  class="w-full justify-between rounded-[14px] font-normal h-10 px-4 bg-background"
                  :class="[errors.utilization ? 'border-destructive' : '']"
                >
                  {{ utilizationLabel }}
                  <ChevronDown class="w-4 h-4 opacity-50" />
                </Button>
              </DropdownMenuTrigger>
              <DropdownMenuContent align="start" class="w-(--reka-dropdown-menu-trigger-width) min-w-(--reka-dropdown-menu-trigger-width) rounded-[14px] z-[1001]">
                <DropdownMenuItem @select="emit('change-utilization', 'corporate')">
                  {{ t('fulfillment.manualRequest.utilizationCorporate') }}
                </DropdownMenuItem>
                <DropdownMenuItem @select="emit('change-utilization', 'project')">
                  {{ t('fulfillment.manualRequest.utilizationProject') }}
                </DropdownMenuItem>
              </DropdownMenuContent>
            </DropdownMenu>
          </FieldContent>
          <FieldError v-if="errors.utilization">{{ errors.utilization }}</FieldError>
        </Field>

        <!-- 4. Department (Corporate) or Project (Project) -->
        <Field v-if="form.utilization === 'corporate'" :data-invalid="!!errors.org_id || undefined">
          <FieldLabel>
            <span>{{ t('fulfillment.manualRequest.department') }}<span class="text-rose-500">*</span></span>
          </FieldLabel>
          <FieldContent>
            <Combobox
              :model-value="form.org_id"
              :options="departments"
              :placeholder="t('fulfillment.manualRequest.selectDepartment')"
              :default-label="t('fulfillment.manualRequest.selectDepartment')"
              :disabled="!form.user_id || loadingUserOptions"
              :error="!!errors.org_id"
              width-class="w-full"
              bg-class="bg-background h-10"
              @update:model-value="(val) => emit('change-department', val ? Number(val) : null)"
            />
          </FieldContent>
          <FieldError v-if="errors.org_id">{{ errors.org_id }}</FieldError>
        </Field>

        <Field v-else :data-invalid="!!errors.project_id || undefined">
          <FieldLabel>
            <span>{{ t('fulfillment.manualRequest.project') }}<span class="text-rose-500">*</span></span>
          </FieldLabel>
          <FieldContent>
            <Combobox
              :model-value="form.project_id"
              :options="projects"
              :placeholder="t('fulfillment.manualRequest.selectProject')"
              :default-label="t('fulfillment.manualRequest.selectProject')"
              :disabled="!form.user_id || loadingUserOptions"
              :error="!!errors.project_id"
              width-class="w-full"
              bg-class="bg-background h-10"
              @update:model-value="(val) => emit('change-project', val ? Number(val) : null)"
            />
          </FieldContent>
          <FieldError v-if="errors.project_id">{{ errors.project_id }}</FieldError>
        </Field>

        <!-- 5. DM (Corporate) or PM (Project) -->
        <Field :data-invalid="!!errors.approver_id || undefined">
          <FieldLabel>
            <span>
              {{ form.utilization === 'project' ? t('fulfillment.manualRequest.pm') : t('fulfillment.manualRequest.dm') }}
              <span class="text-rose-500">*</span>
            </span>
          </FieldLabel>
          <FieldContent>
            <Combobox
              :model-value="form.approver_id"
              :options="approverCandidates"
              :placeholder="form.utilization === 'project' ? t('fulfillment.manualRequest.selectPm') : t('fulfillment.manualRequest.selectDm')"
              :search-placeholder="t('fulfillment.manualRequest.searchEmployee')"
              :default-label="form.utilization === 'project' ? t('fulfillment.manualRequest.selectPm') : t('fulfillment.manualRequest.selectDm')"
              :disabled="loadingUserOptions || loadingApproverOptions || (form.utilization === 'corporate' ? !form.org_id : !form.project_id)"
              :error="!!errors.approver_id"
              width-class="w-full"
              bg-class="bg-background h-10"
              :limit="50"
              @update:model-value="(val) => emit('change-approver', val ? Number(val) : null)"
            />
          </FieldContent>
          <FieldError v-if="errors.approver_id">{{ errors.approver_id }}</FieldError>
        </Field>

        <!-- 6. Upload Form (Required, placed before Notes/Purpose) -->
        <Field :data-invalid="!!errors.form_file || undefined">
          <FieldLabel>
            <span>{{ t('fulfillment.manualRequest.uploadForm') }}<span class="text-rose-500">*</span></span>
          </FieldLabel>
          <FieldContent>
            <div class="space-y-1">
              <div class="flex gap-2">
                <div
                  class="flex-grow min-w-0 px-4 py-2 text-sm border rounded-[14px] bg-background truncate flex items-center h-10"
                  :class="[
                    form.form_file
                      ? 'cursor-pointer hover:bg-muted/20 hover:text-primary transition-colors text-foreground font-medium underline decoration-dotted'
                      : 'text-muted-foreground cursor-default',
                    errors.form_file ? 'border-destructive' : 'border-input',
                  ]"
                  @click="form.form_file && viewFormFileInNewTab()"
                >
                  {{ form.form_file_name || t('fulfillment.manualRequest.noFileSelected') }}
                </div>
                <input
                  ref="formFileInputRef"
                  data-testid="manual-request-form-file-input"
                  type="file"
                  class="hidden"
                  accept=".pdf,.jpg,.jpeg,.png,.webp"
                  @change="handleFormFileChange"
                />
                <Button
                  type="button"
                  size="lg"
                  class="shrink-0"
                  @click="triggerFormFileInput"
                >
                  {{ t('fulfillment.manualRequest.chooseFile') }}
                </Button>
                <Button
                  type="button"
                  variant="destructive"
                  size="icon"
                  class="w-9 shrink-0"
                  :disabled="!form.form_file"
                  @click="handleDeleteFormFile"
                >
                  <Trash2 class="w-4 h-4" />
                </Button>
              </div>
              <p class="text-[10px] text-muted-foreground ml-1">{{ t('fulfillment.manualRequest.maxFileSize2Mb') }}</p>
            </div>
          </FieldContent>
          <FieldError v-if="errors.form_file">{{ errors.form_file }}</FieldError>
        </Field>

        <!-- 7. Notes / Purpose -->
        <Field :data-invalid="!!errors.reasoning || undefined">
          <FieldLabel for="manual-request-reasoning">
            <span>{{ t('fulfillment.manualRequest.notesPurpose') }}</span>
          </FieldLabel>
          <FieldContent>
            <textarea
              id="manual-request-reasoning"
              v-model="form.reasoning"
              rows="2"
              maxlength="2000"
              :placeholder="t('fulfillment.manualRequest.notesPurposePlaceholder')"
              class="w-full px-4 py-2 text-sm border rounded-[14px] bg-background focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors resize-none"
              :class="[errors.reasoning ? 'border-destructive focus:ring-destructive/20 focus:border-destructive' : 'border-input']"
            />
          </FieldContent>
          <FieldError v-if="errors.reasoning">{{ errors.reasoning }}</FieldError>
        </Field>

        <!-- 8. Dynamic Date Input(s) -->
        <!-- Consumable: Single Date -->
        <Field v-if="form.request_type === 'consumable'" :data-invalid="!!errors.date || undefined">
          <FieldLabel for="manual-request-date">
            <span>{{ t('fulfillment.manualRequest.date') }}<span class="text-rose-500">*</span></span>
          </FieldLabel>
          <FieldContent>
            <input
              id="manual-request-date"
              v-model="form.date"
              type="date"
              class="w-full px-4 py-2 border rounded-[14px] bg-background focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors h-10 text-sm"
              :class="[errors.date ? 'border-destructive focus:ring-destructive/20 focus:border-destructive' : 'border-input']"
            />
          </FieldContent>
          <FieldError v-if="errors.date">{{ errors.date }}</FieldError>
        </Field>

        <!-- ST & Rent: Start Date & End Date -->
        <div v-else-if="form.request_type === 'st' || form.request_type === 'rent'" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <Field :data-invalid="!!errors.start_date || undefined">
            <FieldLabel for="manual-request-start-date">
              <span>{{ t('fulfillment.manualRequest.startDate') }}<span class="text-rose-500">*</span></span>
            </FieldLabel>
            <FieldContent>
              <input
                id="manual-request-start-date"
                v-model="form.start_date"
                type="date"
                class="w-full px-4 py-2 border rounded-[14px] bg-background focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors h-10 text-sm"
                :class="[errors.start_date ? 'border-destructive focus:ring-destructive/20 focus:border-destructive' : 'border-input']"
              />
            </FieldContent>
            <FieldError v-if="errors.start_date">{{ errors.start_date }}</FieldError>
          </Field>

          <Field :data-invalid="!!errors.end_date || undefined">
            <FieldLabel for="manual-request-end-date">
              <span>{{ t('fulfillment.manualRequest.endDate') }}<span class="text-rose-500">*</span></span>
            </FieldLabel>
            <FieldContent>
              <input
                id="manual-request-end-date"
                v-model="form.end_date"
                type="date"
                :min="form.start_date || undefined"
                class="w-full px-4 py-2 border rounded-[14px] bg-background focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors h-10 text-sm"
                :class="[errors.end_date ? 'border-destructive focus:ring-destructive/20 focus:border-destructive' : 'border-input']"
              />
            </FieldContent>
            <FieldError v-if="errors.end_date">{{ errors.end_date }}</FieldError>
          </Field>
        </div>

        <!-- LT: Start Date only -->
        <Field v-else :data-invalid="!!errors.start_date || undefined">
          <FieldLabel for="manual-request-lt-start-date">
            <span>{{ t('fulfillment.manualRequest.startDate') }}<span class="text-rose-500">*</span></span>
          </FieldLabel>
          <FieldContent>
            <input
              id="manual-request-lt-start-date"
              v-model="form.start_date"
              type="date"
              class="w-full px-4 py-2 border rounded-[14px] bg-background focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors h-10 text-sm"
              :class="[errors.start_date ? 'border-destructive focus:ring-destructive/20 focus:border-destructive' : 'border-input']"
            />
          </FieldContent>
          <FieldError v-if="errors.start_date">{{ errors.start_date }}</FieldError>
        </Field>
      </div>
    </div>
  </div>
</template>
