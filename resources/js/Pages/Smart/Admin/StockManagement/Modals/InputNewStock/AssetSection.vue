<script setup lang="ts">
/**
 * Asset section of the Input New Stock modal handling asset attributes, condition, location, and registration.
 */
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { ChevronDown, Trash2 } from 'lucide-vue-next';
import Checkbox from '@/Components/ui/checkbox/Checkbox.vue';
import { Button } from '@/Components/ui/button';
import LocationCombobox from '@/Components/LocationCombobox.vue';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';
import { Field, FieldLabel, FieldContent, FieldError } from '@/Components/ui/field';

interface LocationItem {
  id: number;
  name: string;
  full_name?: string;
  [key: string]: any;
}

const props = defineProps<{
  form: any;
  errors: Record<string, string>;
  locations: LocationItem[];
  isComp: boolean;
  isKend: boolean;
  lotReady: boolean;
}>();

const { t } = useI18n();

const photoFileInputRef = ref<HTMLInputElement | null>(null);

const onToggleInputAsset = (checked: boolean | 'indeterminate' | null | undefined) => {
  props.form.input_asset = checked === true;
  if (!props.form.input_asset) {
    props.form.asset_specification = '';
    props.form.borrowing_type = 'LT';
    props.form.condition = 'Bagus';
    props.form.location_id = null;
    props.form.price = '';
    props.form.classification = 'Aset';
    props.form.photo = null;
    props.form.photo_name = '';
    props.form.plate_number = '';
    props.form.is_bulk = false;
    props.form.bulk_quantity = '';
  }
};

const onToggleIsBulk = (checked: boolean | 'indeterminate' | null | undefined) => {
  props.form.is_bulk = checked === true;
  if (!props.form.is_bulk) {
    props.form.bulk_quantity = '';
    if (props.errors.bulk_quantity) {
      delete props.errors.bulk_quantity;
    }
  }
};

const triggerPhotoFileInput = () => {
  photoFileInputRef.value?.click();
};

const handlePhotoFileUpload = (e: Event) => {
  const target = e.target as HTMLInputElement;
  const file = target.files?.[0];
  if (!file) return;

  const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
  if (!allowedTypes.includes(file.type)) {
    alert(t('inventory.invalidFileFormat'));
    target.value = '';
    return;
  }
  if (file.size > 1024 * 1024) {
    alert(t('inventory.fileTooLarge1Mb'));
    target.value = '';
    return;
  }

  props.form.photo = file;
  props.form.photo_name = file.name;
};

const handleDeletePhoto = () => {
  props.form.photo = null;
  props.form.photo_name = '';
  if (photoFileInputRef.value) {
    photoFileInputRef.value.value = '';
  }
};

const getConditionLabel = (cond: string) => {
  if (cond === 'Bagus' || cond === 'Good') return t('inventory.conditionGood');
  if (cond === 'Rusak' || cond === 'Damaged') return t('inventory.conditionDamaged');
  return cond || '';
};

const getClassificationLabel = (cls: string) => {
  if (cls === 'Aset') return t('inventory.classificationAsset');
  if (cls === 'Inventaris') return t('inventory.classificationInventory');
  return cls || '';
};
</script>

<template>
  <div v-if="lotReady" class="space-y-6 pt-4 border-t border-border">
    <!-- Input Aset dengan LOT di atas checkmark -->
    <div class="flex items-center space-x-2">
      <Checkbox
        id="input-asset-check"
        :model-value="form.input_asset"
        @update:model-value="onToggleInputAsset"
      />
      <label
        for="input-asset-check"
        class="text-sm font-semibold text-foreground cursor-pointer select-none leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70"
      >
        {{ t('inventory.inputAssetWithLotAbove') }}
      </label>
    </div>

    <!-- Asset Form -->
    <div v-if="form.input_asset" class="p-4 rounded-[14px] bg-muted/20 border border-border/80 space-y-6">
      <div class="grid grid-cols-1 md:grid-cols-2 gap-x-12 gap-y-6">
        <!-- Left Column -->
        <div class="space-y-6">
          <!-- Specification (longtext) -> if the category is COMP -->
          <Field v-if="isComp" :data-invalid="!!errors.asset_specification || undefined">
            <FieldLabel for="new-asset-specification">
              <span>{{ t('inventory.specification') }}<span class="text-rose-500">*</span></span>
            </FieldLabel>
            <FieldContent>
              <textarea
                id="new-asset-specification"
                v-model="form.asset_specification"
                rows="3"
                maxlength="1000"
                :placeholder="t('inventory.specificationPlaceholder')"
                class="w-full px-4 py-2 border rounded-[14px] bg-background focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors text-sm resize-none"
                :class="[errors.asset_specification ? 'border-destructive focus:ring-destructive/20 focus:border-destructive' : 'border-input']"
              />
            </FieldContent>
            <FieldError v-if="errors.asset_specification">{{ errors.asset_specification }}</FieldError>
          </Field>

          <!-- Type / Borrowing Type (LT, ST) -->
          <Field :data-invalid="!!errors.borrowing_type || undefined">
            <FieldLabel>
              <span>{{ t('inventory.borrowingType') }}<span class="text-rose-500">*</span></span>
            </FieldLabel>
            <FieldContent>
              <DropdownMenu>
                <DropdownMenuTrigger asChild>
                  <Button
                    variant="outline"
                    class="w-full justify-between rounded-[14px] font-normal h-10 px-4"
                    :class="[!form.borrowing_type ? 'text-muted-foreground' : 'text-foreground', errors.borrowing_type ? 'border-destructive' : '']"
                  >
                    {{ form.borrowing_type || t('inventory.selectBorrowingType') }}
                    <ChevronDown class="w-4 h-4 opacity-50" />
                  </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="start" class="w-(--reka-dropdown-menu-trigger-width) min-w-(--reka-dropdown-menu-trigger-width) rounded-[14px] z-[1001]">
                  <DropdownMenuItem @select="form.borrowing_type = 'LT'">LT</DropdownMenuItem>
                  <DropdownMenuItem @select="form.borrowing_type = 'ST'">ST</DropdownMenuItem>
                </DropdownMenuContent>
              </DropdownMenu>
            </FieldContent>
            <FieldError v-if="errors.borrowing_type">{{ errors.borrowing_type }}</FieldError>
          </Field>

          <!-- Condition (Good/Bagus, Damaged/Rusak) -->
          <Field :data-invalid="!!errors.condition || undefined">
            <FieldLabel>
              <span>{{ t('inventory.condition') }}<span class="text-rose-500">*</span></span>
            </FieldLabel>
            <FieldContent>
              <DropdownMenu>
                <DropdownMenuTrigger asChild>
                  <Button
                    variant="outline"
                    class="w-full justify-between rounded-[14px] font-normal h-10 px-4"
                    :class="[!form.condition ? 'text-muted-foreground' : 'text-foreground', errors.condition ? 'border-destructive' : '']"
                  >
                    {{ getConditionLabel(form.condition) || t('inventory.selectCondition') }}
                    <ChevronDown class="w-4 h-4 opacity-50" />
                  </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="start" class="w-(--reka-dropdown-menu-trigger-width) min-w-(--reka-dropdown-menu-trigger-width) rounded-[14px] z-[1001]">
                  <DropdownMenuItem @select="form.condition = 'Bagus'">{{ t('inventory.conditionGood') }}</DropdownMenuItem>
                  <DropdownMenuItem @select="form.condition = 'Rusak'">{{ t('inventory.conditionDamaged') }}</DropdownMenuItem>
                </DropdownMenuContent>
              </DropdownMenu>
            </FieldContent>
            <FieldError v-if="errors.condition">{{ errors.condition }}</FieldError>
          </Field>

          <!-- Location (combobox list all active location) -->
          <Field :data-invalid="!!errors.location_id || undefined">
            <FieldLabel for="new-asset-location">
              <span>{{ t('inventory.location') }}<span class="text-rose-500">*</span></span>
            </FieldLabel>
            <FieldContent>
              <LocationCombobox
                v-model="form.location_id"
                :locations="locations"
                :placeholder="t('inventory.selectLocation')"
                :error="!!errors.location_id"
                :active-only="true"
              />
            </FieldContent>
            <FieldError v-if="errors.location_id">{{ errors.location_id }}</FieldError>
          </Field>

          <!-- Bulk Creation (Only when not vehicle category) -->
          <Field v-if="!isKend" :data-invalid="!!errors.bulk_quantity || undefined">
            <FieldContent>
              <div class="flex items-center gap-2 w-full pt-2">
                <Checkbox
                  id="auto-create-checkbox"
                  :model-value="form.is_bulk"
                  @update:model-value="onToggleIsBulk"
                />
                <label for="auto-create-checkbox" class="cursor-pointer select-none text-sm font-medium text-foreground">
                  {{ t('inventory.autoCreateLabelPrefix') }}
                </label>
                <input
                  type="number"
                  v-model="form.bulk_quantity"
                  placeholder="..."
                  min="1"
                  :disabled="!form.is_bulk"
                  class="w-16 px-2 py-1 text-sm border rounded-[10px] bg-background focus:outline-none focus:ring-2 transition-colors h-8 disabled:opacity-50 disabled:cursor-not-allowed mx-1"
                  :class="[errors.bulk_quantity ? 'border-destructive' : 'border-input']"
                />
                <span class="text-sm font-medium text-foreground">{{ t('inventory.autoCreateLabelSuffix') }}</span>
              </div>
            </FieldContent>
            <FieldError v-if="errors.bulk_quantity" class="pl-6">{{ errors.bulk_quantity }}</FieldError>
          </Field>
        </div>

        <!-- Right Column -->
        <div class="space-y-6">
          <!-- Price / Harga (freetext, numeric only) -->
          <Field :data-invalid="!!errors.price || undefined">
            <FieldLabel for="new-asset-price">
              <span>{{ t('inventory.price') }}</span>
            </FieldLabel>
            <FieldContent>
              <div
                class="flex rounded-[14px] border border-input bg-background focus-within:ring-2 focus-within:ring-primary/20 focus-within:border-primary transition-colors h-10 overflow-hidden"
                :class="[errors.price ? 'border-destructive focus-within:ring-destructive/20 focus-within:border-destructive' : '']"
              >
                <span class="inline-flex items-center px-3 bg-muted/10 text-muted-foreground text-sm border-r border-input select-none font-medium">Rp</span>
                <input
                  id="new-asset-price"
                  v-model="form.price"
                  type="number"
                  min="0"
                  step="any"
                  placeholder="0"
                  class="flex-1 min-w-0 px-4 py-2 text-sm bg-transparent border-0 focus:outline-none focus:ring-0 transition-colors h-full"
                />
              </div>
            </FieldContent>
            <FieldError v-if="errors.price">{{ errors.price }}</FieldError>
          </Field>

          <!-- Classification (selection: Aset, Inventaris - without decide button) -->
          <Field :data-invalid="!!errors.classification || undefined">
            <FieldLabel>
              <span>{{ t('inventory.classification') }}<span class="text-rose-500">*</span></span>
            </FieldLabel>
            <FieldContent>
              <DropdownMenu>
                <DropdownMenuTrigger asChild>
                  <Button
                    variant="outline"
                    class="w-full justify-between rounded-[14px] font-normal h-10 px-4"
                    :class="[!form.classification ? 'text-muted-foreground' : 'text-foreground', errors.classification ? 'border-destructive' : '']"
                  >
                    {{ getClassificationLabel(form.classification) || t('inventory.selectClassification') }}
                    <ChevronDown class="w-4 h-4 opacity-50" />
                  </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="start" class="w-(--reka-dropdown-menu-trigger-width) min-w-(--reka-dropdown-menu-trigger-width) rounded-[14px] z-[1001]">
                  <DropdownMenuItem @select="form.classification = 'Aset'">{{ t('inventory.classificationAsset') }}</DropdownMenuItem>
                  <DropdownMenuItem @select="form.classification = 'Inventaris'">{{ t('inventory.classificationInventory') }}</DropdownMenuItem>
                </DropdownMenuContent>
              </DropdownMenu>
            </FieldContent>
            <FieldError v-if="errors.classification">{{ errors.classification }}</FieldError>
          </Field>

          <!-- Photo (file upload + deleteIconButton) -->
          <Field :data-invalid="!!errors.photo || undefined">
            <FieldLabel>
              <span>{{ t('inventory.photo') }}</span>
            </FieldLabel>
            <FieldContent>
              <div class="space-y-1">
                <div class="flex items-center gap-2">
                  <div class="flex-1 px-3 py-2 text-sm border rounded-[10px] bg-muted/40 text-muted-foreground truncate h-10 flex items-center">
                    {{ form.photo_name || t('inventory.noPhotoSelected') }}
                  </div>
                  <input
                    ref="photoFileInputRef"
                    type="file"
                    class="hidden"
                    accept=".jpg,.jpeg,.png,.webp"
                    @change="handlePhotoFileUpload"
                  />
                  <Button
                    type="button"
                    size="lg"
                    class="shrink-0"
                    @click="triggerPhotoFileInput"
                  >
                    {{ t('inventory.chooseFile') }}
                  </Button>
                  <Button
                    type="button"
                    variant="destructive"
                    size="icon"
                    class="w-9 shrink-0"
                    :disabled="!form.photo"
                    @click="handleDeletePhoto"
                  >
                    <Trash2 class="w-4 h-4" />
                  </Button>
                </div>
                <p class="text-[10px] text-muted-foreground ml-1">{{ t('inventory.maxFileSize1Mb') }}</p>
              </div>
            </FieldContent>
            <FieldError v-if="errors.photo">{{ errors.photo }}</FieldError>
          </Field>

          <!-- Plate Number / Nopol* (only if category.code === 'KEND') -->
          <Field v-if="isKend" :data-invalid="!!errors.plate_number || undefined">
            <FieldLabel for="new-asset-plate-number">
              <span>{{ t('inventory.plateNumber') }}<span class="text-rose-500">*</span></span>
            </FieldLabel>
            <FieldContent>
              <input
                id="new-asset-plate-number"
                v-model="form.plate_number"
                type="text"
                maxlength="20"
                placeholder="B 1234 ABC"
                class="w-full px-4 py-2 border rounded-[14px] bg-background focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors h-10 text-sm uppercase"
                :class="[errors.plate_number ? 'border-destructive focus:ring-destructive/20 focus:border-destructive' : 'border-input']"
              />
            </FieldContent>
            <FieldError v-if="errors.plate_number">{{ errors.plate_number }}</FieldError>
          </Field>
        </div>
      </div>
    </div>
  </div>
</template>
