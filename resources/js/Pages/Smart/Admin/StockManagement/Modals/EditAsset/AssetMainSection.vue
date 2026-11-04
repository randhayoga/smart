<script setup lang="ts">
/**
 * Main Asset attributes section for EditAssetModal.
 * Matches InputNewStock AssetSection.vue layout, shadcn-vue Field styling, and reactive inputs.
 */
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { ChevronDown, Trash2 } from 'lucide-vue-next';
import { Button } from '@/Components/ui/button';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';
import Combobox from '@/Components/Combobox.vue';
import LocationCombobox from '@/Components/LocationCombobox.vue';
import CurrencyInput from '@/Components/CurrencyInput.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { Field, FieldLabel, FieldContent, FieldError } from '@/Components/ui/field';
import { getQcEditTitle } from '@/lib/qualityControl';

interface Props {
  form: any;
  errors: Record<string, string>;
  isSingle: boolean;
  selectedItem?: any;
  isComp: boolean;
  isVehicle: boolean;
  isSoft?: boolean;
  canShowAssignToLocation: boolean;
  isKondisiDisabled: boolean;
  isBorrowedUnit: boolean;
  locations: any[];
  projectOptions: { id: number; name: string }[];
  hasImage: boolean;
  getStatusLabel: (s: string) => string;
  getConditionLabel: (c: string) => string;
  getClassificationLabel: (c: string) => string;
  getBurdenLabel: (b: string) => string;
  handleAssignToLocationSelect: (val: boolean | null) => void;
  handleFileUpload: (e: any) => void;
  handleDeletePhoto: () => void;
  viewImageInNewTab: () => void;
  triggerFileInput: () => void;
}

const props = defineProps<Props>();

const { t } = useI18n();

const licenseType = computed({
  get: () => props.form.license_type || (props.form.license_exp_date ? 'Subscription' : 'Perpetual'),
  set: (val: 'Subscription' | 'Perpetual') => {
    props.form.license_type = val;
    if (val === 'Perpetual') {
      props.form.license_exp_date = null;
      props.form.renewal_reminder = null;
      if (props.errors.license_exp_date) {
        delete props.errors.license_exp_date;
      }
    }
  }
});

const qcLabel = computed(() => {
  const lastQc = props.isSingle ? (props.selectedItem?.last_qc ?? props.selectedItem?.lq) : null;
  return getQcEditTitle(lastQc, t);
});
</script>

<template>
  <div class="grid grid-cols-1 md:grid-cols-2 gap-x-12 gap-y-6">
    <!-- Left Column: Identifiers, Specification, Borrowing Type, Status, Condition, Location -->
    <div class="space-y-6">
      <!-- Asset Code -->
      <Field data-disabled>
        <FieldLabel>{{ t('inventory.assetCode') }}</FieldLabel>
        <FieldContent>
          <input
            type="text"
            :value="isSingle ? form.number : t('inventory.cannotBeChangedBulk')"
            disabled
            class="w-full px-4 py-2 text-sm border border-input rounded-[14px] bg-muted/30 text-muted-foreground cursor-not-allowed h-10"
          />
        </FieldContent>
      </Field>

      <!-- Legacy Number (Single edit only, if present) -->
      <Field v-if="isSingle && form.legacy_number" data-disabled>
        <FieldLabel>{{ t('inventory.legacyNumber') }}</FieldLabel>
        <FieldContent>
          <input
            type="text"
            :value="form.legacy_number"
            disabled
            class="w-full px-4 py-2 text-sm border border-input rounded-[14px] bg-muted/30 text-muted-foreground cursor-not-allowed h-10"
          />
        </FieldContent>
      </Field>

      <!-- Specification (Only for COMP category) -->
      <Field v-if="isComp" :data-invalid="(isSingle && !!errors.specification) || undefined">
        <FieldLabel for="edit-unit-specification">
          <span>{{ t('inventory.specification') }}<span v-if="isSingle" class="text-rose-500">*</span></span>
        </FieldLabel>
        <FieldContent>
          <textarea
            id="edit-unit-specification"
            name="specification"
            v-model="form.specification"
            rows="3"
            maxlength="1000"
            :placeholder="isSingle ? t('inventory.specificationPlaceholder') : t('inventory.unchanged')"
            class="w-full px-4 py-2 text-sm border rounded-[14px] bg-background focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors resize-none"
            :class="[(isSingle && errors.specification) ? 'border-destructive focus:ring-destructive/20 focus:border-destructive' : 'border-input']"
          />
        </FieldContent>
        <FieldError v-if="isSingle && errors.specification">{{ errors.specification }}</FieldError>
      </Field>

      <!-- Borrowing Type -->
      <Field :data-invalid="(isSingle && !!errors.type) || undefined">
        <FieldLabel>
          <span>{{ t('inventory.borrowingType') }}<span v-if="isSingle" class="text-rose-500">*</span></span>
        </FieldLabel>
        <FieldContent>
          <DropdownMenu>
            <DropdownMenuTrigger asChild>
              <Button
                variant="outline"
                :class="['w-full justify-between rounded-[14px] font-normal h-10 px-4', !form.type ? 'text-muted-foreground' : 'text-foreground', (isSingle && errors.type) ? 'border-destructive' : '']"
              >
                {{ form.type || (isSingle ? t('inventory.selectBorrowingType') : t('inventory.unchanged')) }}
                <ChevronDown class="w-4 h-4 opacity-50" />
              </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="start" class="w-(--reka-dropdown-menu-trigger-width) min-w-(--reka-dropdown-menu-trigger-width) rounded-[14px] z-[1001]">
              <DropdownMenuItem @select="form.type = 'LT'">LT</DropdownMenuItem>
              <DropdownMenuItem @select="form.type = 'ST'">ST</DropdownMenuItem>
              <DropdownMenuItem v-if="!isSingle" @select="form.type = ''">{{ t('inventory.unchanged') }}</DropdownMenuItem>
            </DropdownMenuContent>
          </DropdownMenu>
        </FieldContent>
        <FieldError v-if="isSingle && errors.type">{{ errors.type }}</FieldError>
      </Field>

      <!-- Assign to Location (Only shown when canShowAssignToLocation is true) -->
      <Field v-if="canShowAssignToLocation">
        <FieldLabel>
          <span>{{ t('inventory.isAssignedToLocation') }}</span>
        </FieldLabel>
        <FieldContent>
          <DropdownMenu>
            <DropdownMenuTrigger asChild>
              <Button
                variant="outline"
                class="w-full justify-between rounded-[14px] font-normal h-10 px-4 text-foreground"
              >
                {{
                  form.is_assigned_to_location === true
                    ? t('inventory.assignedToLocationYes')
                    : (form.is_assigned_to_location === false
                        ? t('inventory.assignedToLocationNo')
                        : (isSingle ? t('inventory.assignedToLocationNo') : t('inventory.unchanged')))
                }}
                <ChevronDown class="w-4 h-4 opacity-50" />
              </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="start" class="w-(--reka-dropdown-menu-trigger-width) min-w-(--reka-dropdown-menu-trigger-width) rounded-[14px] z-[1001]">
              <DropdownMenuItem v-if="!isSingle" @select="handleAssignToLocationSelect(null)">
                {{ t('inventory.unchanged') }}
              </DropdownMenuItem>
              <DropdownMenuItem @select="handleAssignToLocationSelect(false)">
                {{ t('inventory.assignedToLocationNo') }}
              </DropdownMenuItem>
              <DropdownMenuItem @select="handleAssignToLocationSelect(true)">
                {{ t('inventory.assignedToLocationYes') }}
              </DropdownMenuItem>
            </DropdownMenuContent>
          </DropdownMenu>
        </FieldContent>
      </Field>

      <!-- Status (Disabled with informative helper message & tooltip) -->
      <Field data-disabled="true">
        <FieldLabel>
          <span>{{ t('inventory.status') }}<span v-if="isSingle" class="text-rose-500">*</span></span>
        </FieldLabel>
        <FieldContent>
          <div
            class="w-full flex items-center justify-between px-4 py-2 text-sm border border-input rounded-[14px] bg-muted/30 text-muted-foreground cursor-not-allowed h-10 select-none"
            :title="t('inventory.statusCannotBeChangedManually')"
          >
            <div v-if="form.status" class="flex items-center gap-1.5 overflow-hidden">
              <StatusBadge
                :status="form.status"
                :approval-status="form.approval_status"
                class="rounded-sm"
              />
            </div>
            <span v-else>{{ isSingle ? t('inventory.selectStatus') : t('inventory.unchanged') }}</span>
            <ChevronDown class="w-4 h-4 opacity-50 ml-auto shrink-0" />
          </div>
        </FieldContent>
        <p class="text-[11px] text-muted-foreground mt-1 ml-0.5">{{ t('inventory.statusCannotBeChangedManually') }}</p>
        <FieldError v-if="isSingle && errors.status">{{ errors.status }}</FieldError>
      </Field>

      <!-- Condition -->
      <Field v-if="!isSoft" :data-invalid="(isSingle && !!errors.condition) || undefined" :data-disabled="isKondisiDisabled || undefined">
        <FieldLabel>
          <span>{{ t('inventory.condition') }}<span v-if="isSingle" class="text-rose-500">*</span></span>
        </FieldLabel>
        <FieldContent>
          <div
            v-if="isKondisiDisabled"
            class="w-full flex items-center justify-between px-4 py-2 text-sm border border-input rounded-[14px] bg-muted/30 text-muted-foreground cursor-not-allowed h-10 select-none"
          >
            <span>{{ getConditionLabel(form.condition) || (isSingle ? t('inventory.selectCondition') : t('inventory.unchanged')) }}</span>
            <ChevronDown class="w-4 h-4 opacity-50" />
          </div>
          <DropdownMenu v-else>
            <DropdownMenuTrigger asChild>
              <Button
                variant="outline"
                :class="['w-full justify-between rounded-[14px] font-normal h-10 px-4', !form.condition ? 'text-muted-foreground' : 'text-foreground']"
              >
                {{ getConditionLabel(form.condition) || (isSingle ? t('inventory.selectCondition') : t('inventory.unchanged')) }}
                <ChevronDown class="w-4 h-4 opacity-50" />
              </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="start" class="w-(--reka-dropdown-menu-trigger-width) min-w-(--reka-dropdown-menu-trigger-width) rounded-[14px] z-[1001]">
              <DropdownMenuItem @select="form.condition = 'Bagus'">{{ t('inventory.conditionGood') }}</DropdownMenuItem>
              <DropdownMenuItem @select="form.condition = 'Rusak'">{{ t('inventory.conditionDamaged') }}</DropdownMenuItem>
              <template v-if="!isBorrowedUnit">
                <DropdownMenuItem @select="form.condition = 'Rusak Total'">{{ t('inventory.conditionTotalDamage') }}</DropdownMenuItem>
                <DropdownMenuItem @select="form.condition = 'Hilang'">{{ t('inventory.conditionLost') }}</DropdownMenuItem>
              </template>
            </DropdownMenuContent>
          </DropdownMenu>
        </FieldContent>
        <FieldError v-if="isSingle && errors.condition">{{ errors.condition }}</FieldError>
      </Field>

      <!-- Quality Control -->
      <Field v-if="!isSoft">
        <FieldLabel>
          <span>{{ qcLabel }}</span>
        </FieldLabel>
        <FieldContent>
          <DropdownMenu>
            <DropdownMenuTrigger asChild>
              <Button
                variant="outline"
                :class="[
                  'w-full justify-between rounded-[14px] font-normal h-10 px-4',
                  form.qc_pass === null || form.qc_pass === 'unchanged'
                    ? 'text-muted-foreground'
                    : (form.qc_pass ? 'text-emerald-600 dark:text-emerald-500 font-semibold' : 'text-rose-600 dark:text-rose-500 font-semibold')
                ]"
              >
                {{
                  form.qc_pass === null || form.qc_pass === 'unchanged'
                    ? t('inventory.unchanged')
                    : (form.qc_pass ? t('inventory.qcPass') : t('inventory.qcFail'))
                }}
                <ChevronDown class="w-4 h-4 opacity-50" />
              </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="start" class="w-(--reka-dropdown-menu-trigger-width) min-w-(--reka-dropdown-menu-trigger-width) rounded-[14px] z-[1001]">
              <DropdownMenuItem @select="form.qc_pass = null">{{ t('inventory.unchanged') }}</DropdownMenuItem>
              <DropdownMenuItem class="text-emerald-600 dark:text-emerald-500 font-semibold" @select="form.qc_pass = true">{{ t('inventory.qcPass') }}</DropdownMenuItem>
              <DropdownMenuItem class="text-rose-600 dark:text-rose-500 font-semibold" @select="form.qc_pass = false">{{ t('inventory.qcFail') }}</DropdownMenuItem>
            </DropdownMenuContent>
          </DropdownMenu>
        </FieldContent>
      </Field>

      <!-- Software License Expiration Date (with integrated License Type Dropdown) -->
      <Field v-if="isSoft" :data-invalid="(isSingle && !!errors.license_exp_date) || undefined">
        <FieldLabel for="edit-asset-exp-date">
          <span>
            {{ t('inventory.licenseExpDate') }}
            <span v-if="isSingle && licenseType === 'Subscription'" class="text-rose-500">*</span>
          </span>
        </FieldLabel>
        <FieldContent>
          <div class="flex items-center gap-2">
            <DropdownMenu>
              <DropdownMenuTrigger asChild>
                <Button
                  variant="outline"
                  class="w-36 shrink-0 justify-between rounded-[14px] font-normal h-10 px-3 text-sm"
                >
                  {{ licenseType === 'Subscription' ? t('inventory.subscription') : t('inventory.perpetual') }}
                  <ChevronDown class="w-4 h-4 opacity-50 ml-1 shrink-0" />
                </Button>
              </DropdownMenuTrigger>
              <DropdownMenuContent align="start" class="w-36 rounded-[14px] z-[1001]">
                <DropdownMenuItem @select="licenseType = 'Subscription'">
                  {{ t('inventory.subscription') }}
                </DropdownMenuItem>
                <DropdownMenuItem @select="licenseType = 'Perpetual'">
                  {{ t('inventory.perpetual') }}
                </DropdownMenuItem>
              </DropdownMenuContent>
            </DropdownMenu>

            <input
              id="edit-asset-exp-date"
              type="date"
              v-model="form.license_exp_date"
              :disabled="licenseType === 'Perpetual'"
              class="w-full px-4 py-2 border rounded-[14px] bg-background focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors h-10 text-sm disabled:opacity-50 disabled:cursor-not-allowed disabled:bg-muted/30"
              :class="[(isSingle && errors.license_exp_date) ? 'border-destructive focus:ring-destructive/20 focus:border-destructive' : 'border-input']"
            />
          </div>
        </FieldContent>
        <FieldError v-if="isSingle && errors.license_exp_date">{{ errors.license_exp_date }}</FieldError>
      </Field>

      <!-- Renewal Reminder (Software only, disabled and erased if Perpetual) -->
      <Field v-if="isSoft" :data-invalid="(isSingle && !!errors.renewal_reminder) || undefined">
        <FieldLabel for="edit-asset-renewal-reminder">
          <span>{{ t('inventory.renewalReminder') }}</span>
        </FieldLabel>
        <FieldContent>
          <div class="space-y-1">
            <div
              class="flex rounded-[14px] border bg-background focus-within:ring-2 transition-colors h-10 overflow-hidden"
              :class="[
                (isSingle && errors.renewal_reminder)
                  ? 'border-destructive focus-within:ring-destructive/20 focus-within:border-destructive'
                  : 'border-input focus-within:ring-primary/20 focus-within:border-primary',
                licenseType === 'Perpetual' ? 'opacity-50 cursor-not-allowed bg-muted/20' : '',
              ]"
            >
              <input
                id="edit-asset-renewal-reminder"
                type="number"
                min="0"
                v-model.number="form.renewal_reminder"
                :disabled="licenseType === 'Perpetual'"
                :placeholder="licenseType === 'Perpetual' ? t('inventory.renewalReminderDisabledPerpetual') : (isSingle ? t('inventory.renewalReminderPlaceholder') : t('inventory.unchanged'))"
                class="flex-1 min-w-0 px-4 py-2 text-sm bg-transparent border-0 focus:outline-none focus:ring-0 transition-colors h-full disabled:cursor-not-allowed"
              />
              <span
                class="inline-flex items-center px-3 bg-muted/10 text-muted-foreground text-sm border-l border-input select-none font-medium shrink-0"
              >
                {{ t('inventory.renewalReminderSuffix') }}
              </span>
            </div>
            <p class="text-[10px] text-muted-foreground ml-1">{{ t('inventory.renewalReminderHint') }}</p>
          </div>
        </FieldContent>
        <FieldError v-if="isSingle && errors.renewal_reminder">{{ errors.renewal_reminder }}</FieldError>
      </Field>
    </div>

    <!-- Right Column: Location, Price, Classification, Burden, Project, Photo, Plate Number -->
    <div class="space-y-6">
      <!-- Location -->
      <Field v-if="!isSoft" :data-invalid="(isSingle && !!errors.location_id) || undefined" :data-disabled="form.is_assigned_to_location === true || undefined">
        <FieldLabel>
          <span>{{ t('inventory.location') }}<span v-if="isSingle" class="text-rose-500">*</span></span>
        </FieldLabel>
        <FieldContent>
          <LocationCombobox
            v-model="form.location_id"
            :locations="locations"
            :placeholder="isSingle ? t('inventory.selectLocation') : t('inventory.unchanged')"
            :error="isSingle && !!errors.location_id"
            :disabled="form.is_assigned_to_location === true"
            :active-only="true"
            :clearable="!isSingle && form.is_assigned_to_location !== true"
          />
        </FieldContent>
        <FieldError v-if="isSingle && errors.location_id">{{ errors.location_id }}</FieldError>
      </Field>

      <!-- Price -->
      <Field :data-invalid="(isSingle && !!errors.price) || undefined">
        <FieldLabel for="edit-asset-price">
          <span>{{ t('inventory.price') }}</span>
        </FieldLabel>
        <FieldContent>
          <CurrencyInput
            id="edit-asset-price"
            v-model="form.price"
            :error="isSingle && !!errors.price"
            :placeholder="isSingle ? '0' : t('inventory.unchanged')"
          />
        </FieldContent>
        <FieldError v-if="isSingle && errors.price">{{ errors.price }}</FieldError>
      </Field>

      <!-- Classification -->
      <Field :data-invalid="(isSingle && !!errors.classification) || undefined">
        <FieldLabel>
          <span>{{ t('inventory.classification') }}<span v-if="isSingle" class="text-rose-500">*</span></span>
        </FieldLabel>
        <FieldContent>
          <DropdownMenu>
            <DropdownMenuTrigger asChild>
              <Button
                variant="outline"
                :class="['w-full justify-between rounded-[14px] font-normal h-10 px-4', !form.classification ? 'text-muted-foreground' : 'text-foreground', (isSingle && errors.classification) ? 'border-destructive' : '']"
              >
                {{ getClassificationLabel(form.classification) || (isSingle ? t('inventory.selectClassification') : t('inventory.unchanged')) }}
                <ChevronDown class="w-4 h-4 opacity-50" />
              </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="start" class="w-(--reka-dropdown-menu-trigger-width) min-w-(--reka-dropdown-menu-trigger-width) rounded-[14px] z-[1001]">
              <DropdownMenuItem @select="form.classification = 'Aset'">{{ t('inventory.classificationAsset') }}</DropdownMenuItem>
              <DropdownMenuItem @select="form.classification = 'Inventaris'">{{ t('inventory.classificationInventory') }}</DropdownMenuItem>
              <DropdownMenuItem v-if="!isSingle" @select="form.classification = ''">{{ t('inventory.unchanged') }}</DropdownMenuItem>
            </DropdownMenuContent>
          </DropdownMenu>
        </FieldContent>
        <FieldError v-if="isSingle && errors.classification">{{ errors.classification }}</FieldError>
      </Field>

      <!-- Burden -->
      <Field :data-invalid="(isSingle && !!errors.burden) || undefined">
        <FieldLabel>
          <span>{{ t('inventory.burden') }}<span v-if="isSingle" class="text-rose-500">*</span></span>
        </FieldLabel>
        <FieldContent>
          <DropdownMenu>
            <DropdownMenuTrigger asChild>
              <Button
                variant="outline"
                class="w-full justify-between rounded-[14px] font-normal h-10 px-4"
                :class="[(!form.burden || form.burden === 'Tidak berubah') ? 'text-muted-foreground' : 'text-foreground', (isSingle && errors.burden) ? 'border-destructive' : '']"
              >
                {{ getBurdenLabel(form.burden) }}
                <ChevronDown class="w-4 h-4 opacity-50" />
              </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="start" class="w-(--reka-dropdown-menu-trigger-width) min-w-(--reka-dropdown-menu-trigger-width) rounded-[14px] z-[1001]">
              <DropdownMenuItem v-if="!isSingle" @select="form.burden = 'Tidak berubah'">{{ t('inventory.unchanged') }}</DropdownMenuItem>
              <DropdownMenuItem @select="form.burden = 'Corporate'">{{ t('inventory.corporate') }}</DropdownMenuItem>
              <DropdownMenuItem @select="form.burden = 'Project'">{{ t('inventory.project') }}</DropdownMenuItem>
            </DropdownMenuContent>
          </DropdownMenu>
        </FieldContent>
        <FieldError v-if="isSingle && errors.burden">{{ errors.burden }}</FieldError>
      </Field>

      <!-- Project -->
      <Field v-if="form.burden === 'Project'" :data-invalid="!!errors.project_id || undefined">
        <FieldLabel>
          <span>{{ t('inventory.project') }}<span class="text-rose-500">*</span></span>
        </FieldLabel>
        <FieldContent>
          <Combobox
            v-model="form.project_id"
            :options="projectOptions"
            :search-placeholder="t('inventory.searchProjectPlaceholder')"
            :default-label="t('inventory.selectProject')"
            width-class="w-full h-10 px-4"
            :error="!!errors.project_id"
          />
        </FieldContent>
        <FieldError v-if="errors.project_id">{{ errors.project_id }}</FieldError>
      </Field>

      <!-- Photo -->
      <Field :data-invalid="(isSingle && !!errors.image_url) || undefined">
        <FieldLabel>
          <span>{{ t('inventory.photo') }}</span>
        </FieldLabel>
        <FieldContent>
          <div class="space-y-1">
            <div class="flex items-center gap-2">
              <div
                class="flex-1 px-3 py-2 text-sm border rounded-[10px] bg-muted/40 truncate h-10 flex items-center"
                :class="[
                  hasImage
                    ? 'cursor-pointer hover:bg-muted/60 hover:text-primary transition-colors text-foreground font-medium underline decoration-dotted'
                    : 'text-muted-foreground cursor-default'
                ]"
                @click="hasImage && viewImageInNewTab()"
              >
                {{ form.image_url_name || (isSingle ? t('inventory.noPhotoSelected') : t('inventory.unchanged')) }}
              </div>
              <input
                type="file"
                id="edit-asset-photo-upload"
                class="hidden"
                accept=".jpg,.jpeg,.png,.webp"
                @change="handleFileUpload"
              />
              <Button
                type="button"
                @click="triggerFileInput"
                size="lg"
                class="shrink-0"
              >
                {{ t('inventory.chooseFile') }}
              </Button>
              <Button
                v-if="isSingle"
                type="button"
                variant="destructive"
                size="icon"
                class="w-9 shrink-0"
                :disabled="!hasImage"
                @click="handleDeletePhoto"
                :title="t('inventory.deletePhoto')"
              >
                <Trash2 class="w-4 h-4" />
              </Button>
            </div>
            <p class="text-[10px] text-muted-foreground ml-1">{{ t('inventory.maxFileSize1Mb') }}</p>
          </div>
        </FieldContent>
        <FieldError v-if="isSingle && errors.image_url">{{ errors.image_url }}</FieldError>
      </Field>

      <!-- Plate Number / TNKB (Vehicles only) -->
      <Field v-if="isVehicle" :data-invalid="(isSingle && !!errors.vehicle_registration) || undefined" :data-disabled="(!isSingle) || undefined">
        <FieldLabel>
          <span>{{ t('inventory.plateNumber') }}<span v-if="isSingle" class="text-rose-500">*</span></span>
        </FieldLabel>
        <FieldContent>
          <input
            type="text"
            v-model="form.vehicle_registration"
            :disabled="!isSingle"
            :placeholder="!isSingle ? t('inventory.cannotBeChangedBulk') : t('inventory.nopolPlaceholder')"
            class="w-full px-4 py-2 text-sm border border-input rounded-[14px] bg-background focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors h-10 disabled:bg-muted/30 disabled:text-muted-foreground disabled:cursor-not-allowed uppercase"
          />
        </FieldContent>
        <FieldError v-if="isSingle && errors.vehicle_registration">{{ errors.vehicle_registration }}</FieldError>
      </Field>
    </div>
  </div>
</template>
