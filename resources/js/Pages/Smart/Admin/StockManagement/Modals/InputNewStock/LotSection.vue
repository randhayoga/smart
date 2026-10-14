<script setup lang="ts">
/**
 * LOT section of the Input New Stock modal handling existing LOT selection or new LOT creation.
 */
import { ref, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { ChevronDown, Trash2 } from 'lucide-vue-next';
import Combobox from '@/Components/Combobox.vue';
import Checkbox from '@/Components/ui/checkbox/Checkbox.vue';
import { Button } from '@/Components/ui/button';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';
import { Field, FieldLabel, FieldContent, FieldError } from '@/Components/ui/field';

interface OrganizerItem {
  id: number;
  name: string;
}

interface ProjectItem {
  id_project: number;
  project_name: string;
  no_project?: string;
}

interface LotItem {
  id: number;
  number: string;
  model_id: number;
  po_number?: string | null;
  [key: string]: any;
}

const props = defineProps<{
  form: any;
  errors: Record<string, string>;
  lots: LotItem[];
  organizers: OrganizerItem[];
  projects: ProjectItem[];
  activeModelId: number | null;
}>();

const { t } = useI18n();

const memoFileInputRef = ref<HTMLInputElement | null>(null);

const filteredLots = computed(() => {
  if (!props.activeModelId) return [];
  return props.lots
    .filter(l => l.model_id == props.activeModelId)
    .map(l => ({
      id: l.id,
      name: `${l.number} ${l.po_number ? `(${l.po_number})` : ''}`,
    }));
});

const projectOptions = computed(() => {
  return (props.projects || []).map(p => ({
    id: p.id_project,
    name: p.no_project ? `${p.no_project} - ${p.project_name}` : p.project_name,
  }));
});

const onToggleInputLot = (checked: boolean | 'indeterminate') => {
  props.form.input_lot = checked === true;
  if (!props.form.input_lot) {
    props.form.lot_id = null;
    props.form.create_new_lot = false;
    props.form.input_asset = false;
  }
};

const onToggleCreateNewLot = (checked: boolean | 'indeterminate') => {
  props.form.create_new_lot = checked === true;
  if (props.form.create_new_lot) {
    props.form.lot_id = null;
  }
};

const onSelectEntryType = (type: 'Procurement' | 'Hibah') => {
  props.form.entry_type = type;
  if (type === 'Procurement') {
    props.form.memo_number = '';
    props.form.date_of_grant = '';
    props.form.memo_file = null;
    props.form.memo_file_name = '';
  } else {
    props.form.po_number = '';
    props.form.purchase_date = '';
  }
};

const onSelectBurden = (burden: 'Corporate' | 'Project') => {
  props.form.burden = burden;
  if (burden !== 'Project') {
    props.form.project_id = null;
  }
};

const triggerMemoFileInput = () => {
  memoFileInputRef.value?.click();
};

const handleMemoFileUpload = (e: Event) => {
  const target = e.target as HTMLInputElement;
  const file = target.files?.[0];
  if (!file) return;

  const allowedTypes = ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png'];
  if (!allowedTypes.includes(file.type)) {
    alert(t('inventory.invalidDocFormat'));
    target.value = '';
    return;
  }
  if (file.size > 2 * 1024 * 1024) {
    alert(t('inventory.fileTooLarge2Mb'));
    target.value = '';
    return;
  }

  props.form.memo_file = file;
  props.form.memo_file_name = file.name;
};

const handleDeleteMemoFile = () => {
  props.form.memo_file = null;
  props.form.memo_file_name = '';
  if (memoFileInputRef.value) {
    memoFileInputRef.value.value = '';
  }
};

const getOrganizerLabel = (id: number | null) => {
  if (!id) return '';
  const org = props.organizers.find(o => o.id === id);
  return org ? org.name : '';
};
</script>

<template>
  <div class="space-y-6 pt-4 border-t border-border">
    <!-- Input LOT checkmark -->
    <div class="flex items-center space-x-2">
      <Checkbox
        id="input-lot-check"
        :model-value="form.input_lot"
        @update:model-value="onToggleInputLot"
      />
      <label
        for="input-lot-check"
        class="text-sm font-semibold text-foreground cursor-pointer select-none leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70"
      >
        {{ t('inventory.inputLot') }}
      </label>
    </div>

    <!-- LOT Section Content -->
    <div v-if="form.input_lot" class="p-4 rounded-[14px] bg-muted/20 border border-border/80 space-y-6">
      <div class="grid grid-cols-1 md:grid-cols-2 gap-x-12 gap-y-6">
        <!-- Select LOT combobox -->
        <Field :data-invalid="(!form.create_new_lot && !!errors.lot_id) || undefined">
          <FieldLabel for="select-lot">
            <span>{{ t('inventory.selectLot') }}<span v-if="!form.create_new_lot" class="text-rose-500">*</span></span>
          </FieldLabel>
          <FieldContent>
            <Combobox
              :model-value="form.lot_id"
              :options="filteredLots"
              :placeholder="t('inventory.selectLot')"
              :disabled="form.create_new_lot || (!activeModelId && !form.create_new_type)"
              :error="!form.create_new_lot && !!errors.lot_id"
              width-class="w-full"
              @update:model-value="(val) => { form.lot_id = val ? Number(val) : null; }"
            />
          </FieldContent>
          <FieldError v-if="!form.create_new_lot && errors.lot_id">{{ errors.lot_id }}</FieldError>
        </Field>

        <!-- Create New LOT checkmark -->
        <div class="flex items-center space-x-2 md:pt-8">
          <Checkbox
            id="create-new-lot-check"
            :model-value="form.create_new_lot"
            @update:model-value="onToggleCreateNewLot"
          />
          <label
            for="create-new-lot-check"
            class="text-sm font-semibold text-foreground cursor-pointer select-none leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70"
          >
            {{ t('inventory.createNewLotOption') }}
          </label>
        </div>
      </div>

      <!-- New LOT Form -->
      <div v-if="form.create_new_lot" class="space-y-6 pt-4 border-t border-border/60">
        <!-- Entry Type Selection -->
        <Field :data-invalid="!!errors.entry_type || undefined">
          <FieldLabel>
            <span>{{ t('inventory.entryType') }}<span class="text-rose-500">*</span></span>
          </FieldLabel>
          <FieldContent>
            <DropdownMenu>
              <DropdownMenuTrigger asChild>
                <Button
                  variant="outline"
                  class="w-full md:w-1/2 justify-between rounded-[14px] font-normal h-10 px-4"
                  :class="[errors.entry_type ? 'border-destructive' : '']"
                >
                  {{ form.entry_type === 'Procurement' ? t('inventory.procurement') : t('inventory.grant') }}
                  <ChevronDown class="w-4 h-4 opacity-50" />
                </Button>
              </DropdownMenuTrigger>
              <DropdownMenuContent align="start" class="w-(--reka-dropdown-menu-trigger-width) min-w-(--reka-dropdown-menu-trigger-width) rounded-[14px] z-[1001]">
                <DropdownMenuItem @select="onSelectEntryType('Procurement')">{{ t('inventory.procurement') }}</DropdownMenuItem>
                <DropdownMenuItem @select="onSelectEntryType('Hibah')">{{ t('inventory.grant') }}</DropdownMenuItem>
              </DropdownMenuContent>
            </DropdownMenu>
          </FieldContent>
          <FieldError v-if="errors.entry_type">{{ errors.entry_type }}</FieldError>
        </Field>

        <!-- Dynamic Fields by Entry Type -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-12 gap-y-6">
          <!-- Left Column -->
          <div class="space-y-6">
            <!-- Procurement: PO Number & Purchase Date -->
            <template v-if="form.entry_type === 'Procurement'">
              <!-- PO Number -->
              <Field :data-invalid="!!errors.po_number || undefined">
                <FieldLabel for="new-lot-po-number">
                  <span>{{ t('inventory.poNumber') }}<span class="text-rose-500">*</span></span>
                </FieldLabel>
                <FieldContent>
                  <input
                    id="new-lot-po-number"
                    v-model="form.po_number"
                    type="text"
                    maxlength="255"
                    placeholder="PO-001"
                    class="w-full px-4 py-2 border rounded-[14px] bg-background focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors h-10 text-sm"
                    :class="[errors.po_number ? 'border-destructive focus:ring-destructive/20 focus:border-destructive' : 'border-input']"
                  />
                </FieldContent>
                <FieldError v-if="errors.po_number">{{ errors.po_number }}</FieldError>
              </Field>

              <!-- Purchase Date -->
              <Field :data-invalid="!!errors.purchase_date || undefined">
                <FieldLabel for="new-lot-purchase-date">
                  <span>{{ t('inventory.purchaseDate') }}<span class="text-rose-500">*</span></span>
                </FieldLabel>
                <FieldContent>
                  <input
                    id="new-lot-purchase-date"
                    v-model="form.purchase_date"
                    type="date"
                    class="w-full px-4 py-2 border rounded-[14px] bg-background focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors h-10 text-sm"
                    :class="[errors.purchase_date ? 'border-destructive focus:ring-destructive/20 focus:border-destructive' : 'border-input']"
                  />
                </FieldContent>
                <FieldError v-if="errors.purchase_date">{{ errors.purchase_date }}</FieldError>
              </Field>
            </template>

            <!-- Hibah: Memo Number, Date of Grant, Memo Document -->
            <template v-else-if="form.entry_type === 'Hibah'">
              <!-- Memo Number -->
              <Field :data-invalid="!!errors.memo_number || undefined">
                <FieldLabel for="new-lot-memo-number">
                  <span>{{ t('inventory.memoNumber') }}<span class="text-rose-500">*</span></span>
                </FieldLabel>
                <FieldContent>
                  <input
                    id="new-lot-memo-number"
                    v-model="form.memo_number"
                    type="text"
                    maxlength="255"
                    placeholder="MEMO-001"
                    class="w-full px-4 py-2 border rounded-[14px] bg-background focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors h-10 text-sm"
                    :class="[errors.memo_number ? 'border-destructive focus:ring-destructive/20 focus:border-destructive' : 'border-input']"
                  />
                </FieldContent>
                <FieldError v-if="errors.memo_number">{{ errors.memo_number }}</FieldError>
              </Field>

              <!-- Date of Grant -->
              <Field :data-invalid="!!errors.date_of_grant || undefined">
                <FieldLabel for="new-lot-date-of-grant">
                  <span>{{ t('inventory.dateOfGrant') }}<span class="text-rose-500">*</span></span>
                </FieldLabel>
                <FieldContent>
                  <input
                    id="new-lot-date-of-grant"
                    v-model="form.date_of_grant"
                    type="date"
                    class="w-full px-4 py-2 border rounded-[14px] bg-background focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors h-10 text-sm"
                    :class="[errors.date_of_grant ? 'border-destructive focus:ring-destructive/20 focus:border-destructive' : 'border-input']"
                  />
                </FieldContent>
                <FieldError v-if="errors.date_of_grant">{{ errors.date_of_grant }}</FieldError>
              </Field>

              <!-- Memo Document File Upload -->
              <Field :data-invalid="!!errors.memo_file || undefined">
                <FieldLabel>
                  <span>{{ t('inventory.memoDocument') }}</span>
                </FieldLabel>
                <FieldContent>
                  <div class="space-y-1">
                    <div class="flex items-center gap-2">
                      <div class="flex-1 px-3 py-2 text-sm border rounded-[10px] bg-muted/40 text-muted-foreground truncate h-10 flex items-center">
                        {{ form.memo_file_name || t('inventory.noFileSelected') }}
                      </div>
                      <input
                        ref="memoFileInputRef"
                        type="file"
                        class="hidden"
                        accept=".pdf,.jpg,.jpeg,.png"
                        @change="handleMemoFileUpload"
                      />
                      <Button
                        type="button"
                        size="lg"
                        class="shrink-0"
                        @click="triggerMemoFileInput"
                      >
                        {{ t('inventory.chooseFile') }}
                      </Button>
                      <Button
                        type="button"
                        variant="destructive"
                        size="icon"
                        class="w-9 shrink-0"
                        :disabled="!form.memo_file"
                        @click="handleDeleteMemoFile"
                      >
                        <Trash2 class="w-4 h-4" />
                      </Button>
                    </div>
                    <p class="text-[10px] text-muted-foreground ml-1">{{ t('inventory.maxFileSize2Mb') }}</p>
                  </div>
                </FieldContent>
                <FieldError v-if="errors.memo_file">{{ errors.memo_file }}</FieldError>
              </Field>
            </template>
          </div>

          <!-- Right Column: Organizer & Burden (& Project) -->
          <div class="space-y-6">
            <!-- Organizer -->
            <Field :data-invalid="!!errors.organizer_id || undefined">
              <FieldLabel>
                <span>{{ t('inventory.organizer') }}<span class="text-rose-500">*</span></span>
              </FieldLabel>
              <FieldContent>
                <DropdownMenu>
                  <DropdownMenuTrigger asChild>
                    <Button
                      variant="outline"
                      class="w-full justify-between rounded-[14px] font-normal h-10 px-4"
                      :class="[!form.organizer_id ? 'text-muted-foreground' : 'text-foreground', errors.organizer_id ? 'border-destructive' : '']"
                    >
                      {{ getOrganizerLabel(form.organizer_id) || t('inventory.selectOrganizer') }}
                      <ChevronDown class="w-4 h-4 opacity-50" />
                    </Button>
                  </DropdownMenuTrigger>
                  <DropdownMenuContent align="start" class="w-(--reka-dropdown-menu-trigger-width) min-w-(--reka-dropdown-menu-trigger-width) rounded-[14px] z-[1001]">
                    <DropdownMenuItem
                      v-for="org in organizers"
                      :key="org.id"
                      @select="form.organizer_id = org.id"
                    >
                      {{ org.name }}
                    </DropdownMenuItem>
                  </DropdownMenuContent>
                </DropdownMenu>
              </FieldContent>
              <FieldError v-if="errors.organizer_id">{{ errors.organizer_id }}</FieldError>
            </Field>

            <!-- Burden / Pembebanan -->
            <Field :data-invalid="!!errors.burden || undefined">
              <FieldLabel>
                <span>{{ t('inventory.burden') }}<span class="text-rose-500">*</span></span>
              </FieldLabel>
              <FieldContent>
                <DropdownMenu>
                  <DropdownMenuTrigger asChild>
                    <Button
                      variant="outline"
                      class="w-full justify-between rounded-[14px] font-normal h-10 px-4"
                      :class="[errors.burden ? 'border-destructive' : '']"
                    >
                      {{ form.burden === 'Project' ? t('inventory.project') : t('inventory.corporate') }}
                      <ChevronDown class="w-4 h-4 opacity-50" />
                    </Button>
                  </DropdownMenuTrigger>
                  <DropdownMenuContent align="start" class="w-(--reka-dropdown-menu-trigger-width) min-w-(--reka-dropdown-menu-trigger-width) rounded-[14px] z-[1001]">
                    <DropdownMenuItem @select="onSelectBurden('Corporate')">{{ t('inventory.corporate') }}</DropdownMenuItem>
                    <DropdownMenuItem @select="onSelectBurden('Project')">{{ t('inventory.project') }}</DropdownMenuItem>
                  </DropdownMenuContent>
                </DropdownMenu>
              </FieldContent>
              <FieldError v-if="errors.burden">{{ errors.burden }}</FieldError>
            </Field>

            <!-- Project Combobox (if Burden = Project) -->
            <Field v-if="form.burden === 'Project'" :data-invalid="!!errors.project_id || undefined">
              <FieldLabel for="new-lot-project">
                <span>{{ t('inventory.project') }}<span class="text-rose-500">*</span></span>
              </FieldLabel>
              <FieldContent>
                <Combobox
                  :model-value="form.project_id"
                  :options="projectOptions"
                  :placeholder="t('inventory.selectProject')"
                  :error="!!errors.project_id"
                  width-class="w-full"
                  @update:model-value="(val) => { form.project_id = val ? Number(val) : null; }"
                />
              </FieldContent>
              <FieldError v-if="errors.project_id">{{ errors.project_id }}</FieldError>
            </Field>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
